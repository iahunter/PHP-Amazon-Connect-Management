<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use Aws\Connect\ConnectClient;
use Aws\Kinesis\KinesisClient;
use Aws\Firehose\FirehoseClient;
use Aws\S3\S3Client;
use Aws\S3\S3MultiRegionClient;
use Aws\Iam\IamClient;
use Aws\Kms\KmsClient;

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

use App\AWS\IAM;
use App\AWS\S3;
use App\AWS\Firehose;

class DeployInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:deploy-new-instance {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy New Connect Instance to AWS';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        
        echo "###################################################".PHP_EOL;
        echo "                Deploy Amazon Connect              ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        $this->starttime = Carbon::now();
        echo $this->starttime.PHP_EOL;

        // Application Code
        $this->application = "awc";

        $instance = $this->get_instance_info();


        // Add Connect Creation Here. 

        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        $this->tags = [
            [
                'Key' => 'ConnectInstance', // REQUIRED
                'Value' => $this->instance_alias,
            ],
            [
                'Key' => 'Application', // REQUIRED
                'Value' => strtoupper($this->application),
            ],
            [
                'Key' => 'CostRecovery', // REQUIRED
                'Value' => $this->costcode,
            ],
        ];
        
        /*Removed for testing*/
        try{
            $new_instance = $this->create_instance($instance); 

            print_r($new_instance);
            $this->instance_id = $new_instance['Instance']['Id']; 
            $this->instance_arn = $new_instance['Instance']['Arn'];
        }catch(Exception $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }
        


        
        echo "###################################################".PHP_EOL;
        echo "            Deploy Connect S3 Buckets              ".PHP_EOL;
        echo "###################################################".PHP_EOL;


        $S3 = new S3(   
                        $this->region,
                        $this->app_key,
                        $this->app_secret
                    );
        
        $IAM = new IAM($this->account_number, $this->region);

        
        $S3Client = $this->S3Client($this->region); 

        $bucket = "s3-$this->environment-$this->shortregion-$this->application-$this->instance_name";

        $bucketlist = $S3->listBucketsAndRegions();
        if(key_exists($bucket, $bucketlist)){
            print "Bucket $bucket already exists.".PHP_EOL;
        }else{
            /* Removed for Testing*/
            $newbucket = $S3Client->createBucket(['Bucket' => $bucket]);

            print_r($newbucket); 

            sleep(10);

            $S3Client->putBucketTagging(
                ['Bucket' => $bucket,
                'Tagging' => [ // REQUIRED
                    'TagSet' => $this->tags
                ],
            ]);

            $bucketlist = $S3->listBucketsAndRegions();

            print_r($bucketlist);
            
            if(key_exists($bucket, $bucketlist)){
                //$newbucket = $S3Client->describeBucket(['Bucket' => $bucket]);
                print_r($newbucket);
                print "Bucket {$bucket} Created Successfully...".PHP_EOL;
            }
        }
        
        
        $recordtypes = [
            "ctr",
            "agentevents",
        ];

        // Loop thru the record types and create stream, policy, role, and firehouse and attach them to connect instance. 
        foreach($recordtypes as $type)
        {

            echo "###################################################".PHP_EOL;
            echo "         Deploy Connect Kinesis Streams            ".PHP_EOL;
            echo "###################################################".PHP_EOL;

            

            print "Building Kinesis Stream...".PHP_EOL;

            

            $KinesisClient = $this->KinesisClient($this->region);

            $streams = $KinesisClient->listStreams();

            print_r($streams);

            $stream_name = "kin-$this->environment-$this->shortregion-$this->application-$this->instance_name-$type";

            if(!in_array($stream_name, $streams['StreamNames'])){
                $stream = [
                    'ShardCount' => 10, // REQUIRED
                    'StreamName' => $stream_name, // REQUIRED
                ];

                /* Removed for Testing*/ 
                $result = $KinesisClient->createStream($stream);

                print_r($result);

                sleep(5);
            
                echo "Checking Stream List for new Stream Name: {$stream['StreamName']}".PHP_EOL;
                
                $streams = $KinesisClient->listStreams();

                //print_r($streams);

                if(in_array($stream_name, $streams['StreamNames'])){
                    print "Stream: {$stream_name} Created Successfully".PHP_EOL; 
                    $result = $KinesisClient->describeStreamSummary(['StreamName' => $stream_name]);
                    $streamname = $result['StreamDescriptionSummary']['StreamName'];
                    $streamarn = $result['StreamDescriptionSummary']['StreamARN'];
                    print_r($result); 
                }

                sleep(5);
            }else{
                print "Stream already exists. No need to recreate it. ".PHP_EOL;

                $result = $KinesisClient->describeStreamSummary(['StreamName' => $stream_name]);
                    $streamname = $result['StreamDescriptionSummary']['StreamName'];
                    $streamarn = $result['StreamDescriptionSummary']['StreamARN'];
            }


            echo "###################################################".PHP_EOL;
            echo "            Check Tags on Kinesis Stream           ".PHP_EOL;
            echo "###################################################".PHP_EOL;

            
            $kinesistags = [
                    'ConnectInstance' => $this->instance_alias,
                    'Application' => strtoupper($this->application),
                    'CostRecovery' => $this->costcode,
                ];
        

            $result = $KinesisClient->listTagsForStream([
                //'ExclusiveStartTagKey' => '<string>',
                //'Limit' => <integer>,
                'StreamName' => $stream_name, // REQUIRED
            ]);

            print_r($result);


            echo "###################################################".PHP_EOL;
            echo "            Apply Tags to Kinesis Stream           ".PHP_EOL;
            echo "###################################################".PHP_EOL;
            $result = $KinesisClient->addTagsToStream([
                'StreamName' => $stream_name, // REQUIRED
                'Tags' => $kinesistags, // REQUIRED
            ]);


            $result = $KinesisClient->listTagsForStream([
                //'ExclusiveStartTagKey' => '<string>',
                //'Limit' => <integer>,
                'StreamName' => $stream_name, // REQUIRED
            ]);

            print_r($result);
        

            echo "###################################################".PHP_EOL;
            echo "            Deploy IAM Policy for Firehose         ".PHP_EOL;
            echo "###################################################".PHP_EOL;


            $firehose_name = "fhds-$this->environment-$this->shortregion-$this->application-$this->instance_name-$type";

            $FirehoseClient = $this->FirehoseClient($this->region);

            
            $fhs = $FirehoseClient->listDeliveryStreams();

            // if the fireshose already exists no need to create all of this so skip it. 
            if(!in_array($firehose_name, $fhs['DeliveryStreamNames'])){
                $firehoseperms = [  $IAM->allowDatabase(),
                                    $IAM->allowS3($bucket),
                                    $IAM->allowLambda(),
                                    $IAM->allowLogs($firehose_name),
                                    $IAM->allowDecryptS3(),
                                    $IAM->allowKinesisStreams([$streamarn]),
                                    $IAM->allowDecryptKinesis(),
                ];


                $json = json_encode($firehoseperms,JSON_UNESCAPED_SLASHES);

                $jsonperms = <<<END
{
    "Version":"2012-10-17",
    "Statement":$json
}
END;
                print_r($jsonperms);

                $IamClient = $this->IamClient($this->region);

                //$roles = $IamClient->listRoles();
                //print_r($roles);
                

                sleep(10);

                print "Creating Policy.".PHP_EOL;

                

                $policy_name = "iampolicy-$this->environment-$this->shortregion-$this->application-$this->instance_name-$type";

                $policies = $IamClient->listPolicies();
                print_r($policies);
                
                $policyfound = false;
                foreach($policies['Policies'] as $p)
                {
                    if(isset($p['PolicyName']) && $policy_name == $p['PolicyName'])
                    {
                        $policyfound = true;
                        $arn = $p['Arn'];
                        print "Found Policy already exists..".PHP_EOL;
                        //print_r($arn);
                        break;

                    }
                }
                
                if(!$policyfound)
                {
                    /* Removed for Testing*/
                    $policy = $IamClient->createPolicy([
                        //'Description' => '<string>',
                        //'Path' => '<string>',
                        'PolicyDocument' => $jsonperms, // REQUIRED
                        'PolicyName' => $policy_name, // REQUIRED
                        'Tags' => $this->tags,
                    ]);

                    print_r($policy);
            
                    sleep(10);

                    $policies = $IamClient->listPolicies();
                    print_r($policies);

                    foreach($policies['Policies'] as $p)
                    {
                        if(isset($p['PolicyName']) && $policy_name == $p['PolicyName'])
                        {
                            $arn = $p['Arn'];
                            print "Found Policy Arn: $arn".PHP_EOL;
                            //print_r($arn);
                            break;
                        }
                    }

                }else{
                    print "Policy already exists with name $policy_name".PHP_EOL;
                }
                

                sleep(10);


                echo "###################################################".PHP_EOL;
                echo "            Deploy IAM Roles for Firehose          ".PHP_EOL;
                echo "###################################################".PHP_EOL;

                $role_name = "iamrole-$this->environment-$this->shortregion-$this->application-$this->instance_name-$type";

                if(strlen($role_name) > 64)
                {
                    $role_name = substr($role_name,0,63);
                }

                print $role_name.PHP_EOL; 

                $roles = $IamClient->listRoles();
                print_r($roles);
                $rolefound = false;
                foreach($roles['Roles'] as $role){
                    if($role['RoleName'] == $role_name)
                    {
                        print "Role alredy exists with name $role_name".PHP_EOL;
                        $rolefound = true;
                        break;
                    }
                }

                if(!$rolefound){
                

                    $assumeroledoc = <<<END
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": "firehose.amazonaws.com"
            },
            "Action": "sts:AssumeRole"
        }
    ]
}
END;


                    sleep(10);

                    /* Removed for Testing*/
                    $result = $IamClient->createRole([
                        'AssumeRolePolicyDocument' => $assumeroledoc, // REQUIRED
                        'Description' => 'Allows Kinesis Firehose to transform and deliver data to your destinations using CloudWatch Logs, Lambda, and S3 on your behalf.',
                        //'MaxSessionDuration' => <integer>,
                        'Path' => "/service-role/",
                        //'PermissionsBoundary' => '<string>',
                        'RoleName' => $role_name, // REQUIRED
                        'Tags' => $this->tags,
                    ]);

                    print_r($result);

                    sleep(10);

                    $policies = $IamClient->ListAttachedRolePolicies([
                        'RoleName' => $role_name, // REQUIRED
                    ]);

                    print "Attaching Policy $arn to Role $role_name.".PHP_EOL;

                    /* Removed for Testing*/
                    $result = $IamClient->attachRolePolicy([
                        'PolicyArn' => $arn, // REQUIRED
                        'RoleName' => $role_name, // REQUIRED
                    ]);

                    print_r($result);
                }


                sleep(10);

                $result = $IamClient->getRole([
                    'RoleName' => $role_name, // REQUIRED
                ]);

                print_r($result);

                $role_arn = $result['Role']['Arn'];
                
                sleep(10);

                
                $policies = $IamClient->ListAttachedRolePolicies([
                    'RoleName' => $role_name, // REQUIRED
                ]);

                $policyfound = false;
                foreach($policies['AttachedPolicies'] as $policy){
                    print_r($policy);
                    if($policy['PolicyArn'] == $arn){
                        $policyfound = true;
                        print "Found Policy ARN $arn attached to $role_name".PHP_EOL;
                    }
                }

                if(!$policyfound){
                    /* Removed for Testing*/
                    $result = $IamClient->attachRolePolicy([
                        'PolicyArn' => $arn, // REQUIRED
                        'RoleName' => $role_name, // REQUIRED
                    ]);

                    print_r($result);
                }
                //print_r($policies);

                /*
                Not needed. 
                foreach($policies['AttachedPolicies'] as $policy){
                    //print_r($policy);
                    $versions = $IamClient->listPolicyVersions(['PolicyArn' => $policy['PolicyArn']]); // Gets policy versions

                    $p = $IamClient->getPolicyVersion(['PolicyArn' => $policy['PolicyArn'],'VersionId'=>$versions['Versions'][0]['VersionId']]); // Gets policy version 

                    $json = urldecode($p['PolicyVersion']['Document']); // Decode the urlencoded string into json.

                    //print_r($json);
                }

                */

                sleep(10);
            
                echo "###################################################".PHP_EOL;
                echo "            Deploy Connect Firehose                ".PHP_EOL;
                echo "###################################################".PHP_EOL;   


                
                $FirehoseClient = $this->FirehoseClient($this->region);

                
                $fhs = $FirehoseClient->listDeliveryStreams();

                /*
                print_r($fhs);

                foreach($fhs['DeliveryStreamNames'] as $fh)
                {
                    $firehose = $FirehoseClient->describeDeliveryStream(['DeliveryStreamName' => $fh]);

                    print_r($firehose);
                }
                */
                

                $firehose = new Firehose($this->account_number,$this->region);

                $fh = $firehose->generateKinesisStreamToS3Firehose($streamarn, $firehose_name, $bucket, $role_arn, $type, $this->tags);

                $newfh = $FirehoseClient->createDeliveryStream($fh);

                print_r($newfh);
            }else{
                print "Firehose $firehose_name already exists. No need to create it.".PHP_EOL;
                sleep(5);
            }

            

            

            if($type == "ctr"){
                $storage_type = "CONTACT_TRACE_RECORDS";
            }if($type == "agentevents"){
                $storage_type = "AGENT_EVENTS";
            }

            // Check to see if anything is already associated. 
            $storageconfigs = $this->ConnectClient->listInstanceStorageConfigs(['InstanceId' => $this->instance_id, 'ResourceType' => $storage_type]);

            if(empty($storageconfigs['StorageConfigs'])){

                
                echo "###################################################".PHP_EOL;
                echo "        Attach Kinesis to Connect Instance         ".PHP_EOL;
                echo "###################################################".PHP_EOL;  

                $storage = [
                                'InstanceId' => $this->instance_id, // REQUIRED
                                'ResourceType' => $storage_type, // REQUIRED
                                'StorageConfig' => [ // REQUIRED
                                    'KinesisStreamConfig' => [
                                        'StreamArn' => $streamarn, // REQUIRED
                                    ],
                                    'StorageType' => 'KINESIS_STREAM', // REQUIRED
                                ],
                            ];
                
                $result = $this->ConnectClient->associateInstanceStorageConfig($storage);

                print_r($result);
            }
        }

        $KmsClient = new KmsClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        $aliases = $KmsClient->listAliases();

        print_r($aliases);
        foreach($aliases['Aliases'] as $alias)
        {
            if($alias["AliasName"] == "alias/aws/connect"){
                print_r($alias);

                if(isset($alias["TargetKeyId"])){
                    $keyid = $alias["TargetKeyId"];

                    $keys = $KmsClient->listKeys();
                    foreach($keys['Keys'] as $k){
                        if($k['KeyId'] == $keyid){
                            $key = $k['KeyArn'];
                        }
                    }
                    break;
                }
            }else{
                $key = null;
            }
        }

        sleep(10); 

        echo "###################################################".PHP_EOL;
        echo "     Attach S3 Storage to Connect Instance         ".PHP_EOL;
        echo "###################################################".PHP_EOL;  

        $storage_types = [  'CHAT_TRANSCRIPTS',
                            'CALL_RECORDINGS',
                            'SCHEDULED_REPORTS',
                            //'MEDIA_STREAMS',
        ];

        foreach($storage_types as $storage_type){

            // Check to see if anything is already associated. 
            $storageconfigs = $this->ConnectClient->listInstanceStorageConfigs(['InstanceId' => $this->instance_id, 'ResourceType' => $storage_type]);

            if(empty($storageconfigs['StorageConfigs'])){

                $storage = [
                    'InstanceId' => $this->instance_id, // REQUIRED
                    'ResourceType' => $storage_type, // REQUIRED
                    'StorageConfig' => [ // REQUIRED
                        'S3Config' => [
                            'BucketName' => $bucket, // REQUIRED
                            'BucketPrefix' => $storage_type."-", // REQUIRED
                            /*'EncryptionConfig' => [
                                'EncryptionType' => 'KMS', // REQUIRED
                                'KeyId' => $key, // REQUIRED
                            ],*/
                        ],
                        'StorageType' => 'S3', // REQUIRED
                    ],
                ];

                print $key;
                if($key){
                    
                    $encryption = [
                        'EncryptionType' => 'KMS', // REQUIRED
                        'KeyId' => $key, // REQUIRED
                    ];
                    print "Key $key Found... Appling key...".PHP_EOL;
                    $storage['StorageConfig']['S3Config']['EncryptionConfig'] = $encryption; 
                }else{
                    print "No key found".PHP_EOL;
                }

                sleep(10);

                $result = $this->ConnectClient->associateInstanceStorageConfig($storage);

                print_r($result);
            }else{
                print "Storage $storage_type is all ready assigned.".PHP_EOL;
                print_r($storageconfigs);
            }
        }

        
        /*
        echo "###################################################".PHP_EOL;
        echo "          Apply Tags to Connect Instance           ".PHP_EOL;
        echo "###################################################".PHP_EOL;  

        $tags = $this->ConnectClient->listTagsForResource([
            'resourceArn' => $this->instance_arn, // REQUIRED
        ]);


        foreach($tags as $tag){

        }

        $result = $this->ConnectClient->tagResource([
            'resourceArn' => $this->instance_arn, // REQUIRED
            'tags' => $this->tags, // REQUIRED
        ]);
        */



        $time = Carbon::now();
        echo "Start Time: ". $this->starttime.PHP_EOL; 
        echo "End Time: ". $time.PHP_EOL;
    }

    public function ConnectClient($region)
    {
        $client = new ConnectClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        return $client;
    }

    public function S3Client($region)
    {
        $client = new S3Client([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        return $client;
    }

    public function KinesisClient($region)
    {
        $client = new KinesisClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        return $client;
    }

    public function FirehoseClient($region)
    {
        $client = new FirehoseClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        return $client;
    }

    public function IamClient($region)
    {
        $client = new IamClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        return $client;
    }


    public function create_instance($instance)
    {
        $result = $this->ConnectClient->listInstances();
        $instances = $result['InstanceSummaryList'];

        foreach($instances as $i){
            if($i['InstanceAlias'] == $this->instance_alias){
                print "Connect already exists with alias $this->instance_alias".PHP_EOL;
                $instance_id = $i['Id'];
                $instance = $this->ConnectClient->describeInstance(['InstanceId' => $instance_id]);
                //die();
                return $instance; 
            } 
        }

        //print_r($instance);
        try{
            $result = $this->ConnectClient->createInstance($instance);
        } catch (\Exception $e) {
            echo $e->getMessage().PHP_EOL;
            return $e;
        }

        //print_r($result);

        $instance_id = $result['Id'];

        sleep(5);

        $creating = true;

        $time = Carbon::now();
        $timeout = Carbon::now()->addMinutes(20);

        echo $time . " Creating Instance ID: {$instance_id} with name: {$this->instance_alias}".PHP_EOL;
        
        while($creating == true && $time <= $timeout){
            $result = $this->ConnectClient->listInstances();
            $instances = $result['InstanceSummaryList'];

            //print_r($instances);
            $time = Carbon::now();
            foreach($instances as $instance){
                if($instance['Id'] == $instance_id){
                    if($instance['InstanceStatus'] == "ACTIVE"){
                        print "Instance created successfully...".PHP_EOL;
                        //print_r($instance); 
                        $creating = false;
                    }else{
                        echo $time." Status: " . $instance['InstanceStatus'] ." : Please Wait...".PHP_EOL;
                        sleep(30);
                    }
                }
            }
        }

        $instance = $this->ConnectClient->describeInstance(['InstanceId' => $instance_id]);

        return $instance;
    }

    public function get_instance_info()
    {
        if(!$this->argument('company')){
            $this->company_id = null;
        }else{
            $this->company_id = $this->argument('company'); 
        }

        if(!$this->argument('account_number')){
            $this->account_number = null;
        }else{
            $this->account_number = $this->argument('account_number'); 
        }


        if(!$this->company_id || !$this->account_number){
            $this->prompt(); 
        }
            
        
        print $this->company_id.PHP_EOL;
        print $this->account_number.PHP_EOL; 

        $account = Account::where('account_number', $this->account_number)->first(); 

        if($account->account_app_key){
            $this->app_key = Crypt::decryptString($account->account_app_key);
        }else{
            $this->app_key = env('AMAZON_KEY'); 
        }
        if($account->account_app_secret){
            $this->app_secret = Crypt::decryptString($account->account_app_secret);
        }else{
            $this->app_secret = env('AMAZON_SECRET'); 
        }

        $regions = [
            'us-east-1',
            'us-west-2',
        ]; 
        
        $this->region = $this->choice('What Region would you like to use?', $regions);

        $this->shortregion = str_replace("-","",$this->region);
        
        $this->instance_name = strtolower($this->ask('What is the name of the instance?', "kss"));

        $this->costcode = strtoupper($this->ask('What is the CostCode?', "KTGT"));

        //$this->instance_name = strtolower($this->instance_name);

        $environments = [
            "lab",
            "prod"
        ];

        $this->environment = $this->choice('What environment would you like to deploy this in?', $environments);

        $this->instance_alias = "connect-$this->environment-$this->shortregion-$this->application-$this->instance_name";

        $instance_array = [
            "IdentityManagementType" => "SAML",
            "InboundCallsEnabled" => true,
            "InstanceAlias" => $this->instance_alias,
            "OutboundCallsEnabled" => true
        ];
        
        //print_r($instance_array);

        return $instance_array;
    }

    public function prompt()
    {
        if(!$this->company_id){
            $names = Company::names();

            //print_r($names);

            //$companies = Company::where('name' = )
            if (empty($this->company_name)) {
                $this->company_name = $this->choice('What is the Company Name?', $names,);
                $company = Company::where('name', $this->company_name)->first();
                $this->company_id = $company->id;
            }
        }
        
        if(!$this->account_number){
            $accounts = Account::where('company_id' , $this->company_id)->get();
            //print_r($accounts);
            $account_ids = []; 
            foreach($accounts as $account){
                $account_ids[] = $account['account_number']; 
            }
            $this->account_number = $this->choice('What is the account ID Name?', $account_ids);
            $account = Account::where('account_number', $this->account_number)->first();
            $this->account_number = $account->account_number;
        }
    }
}
