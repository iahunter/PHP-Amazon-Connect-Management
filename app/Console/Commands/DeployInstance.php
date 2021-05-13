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

        $instance = $this->get_instance_info();


        // Add Connect Creation Here. 

        //$ConnectClient = $this->ConnectClient($this->region);

        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);


        
        /*Removed for testing*/
        try{
            $new_instance = $this->create_instance($instance); 

            print_r($new_instance);
            $this->instance_id = $new_instance['Instance']['Id']; 
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

        $bucket = "s3-amazonconnect-{$this->instance_name}";

        /* Removed for Testing*/
        $newbucket = $S3Client->createBucket(['Bucket' => $bucket]);

        print_r($newbucket); 

        

        sleep(10);

        $bucketlist = $S3->listBucketsAndRegions();

        print_r($bucketlist);
        
        if(key_exists($bucket, $bucketlist)){
            //$newbucket = $S3Client->describeBucket(['Bucket' => $bucket]);
            print_r($newbucket);
            print "Bucket {$bucket} Created Successfully...".PHP_EOL;
        }
        
        
        $recordtypes = [
            "ctr",
            "agent-events",
        ];

        foreach($recordtypes as $type)
        {

            

            echo "###################################################".PHP_EOL;
            echo "         Deploy Connect Kinesis Streams            ".PHP_EOL;
            echo "###################################################".PHP_EOL;

            

            print "Building Kinesis Stream...".PHP_EOL;

            

            $KinesisClient = $this->KinesisClient($this->region);

            $streams = $KinesisClient->listStreams();

            print_r($streams);

            $stream = [
                'ShardCount' => 10, // REQUIRED
                'StreamName' => "ks-$this->instance_name-$type", // REQUIRED
            ];

            /* Removed for Testing*/ 
            $result = $KinesisClient->createStream($stream);

            print_r($result);
            

            sleep(5);
            
            echo "Checking Stream List for new Stream Name: {$stream['StreamName']}".PHP_EOL;
            
            $streams = $KinesisClient->listStreams();

            print_r($streams);

            if(in_array($stream['StreamName'], $streams['StreamNames'])){
                print "Stream: {$stream['StreamName']} Created Successfully".PHP_EOL; 
                $result = $KinesisClient->describeStreamSummary(['StreamName' => $stream['StreamName']]);
                $streamname = $result['StreamDescriptionSummary']['StreamName'];
                $streamarn = $result['StreamDescriptionSummary']['StreamARN'];
                print_r($result); 
            }

            sleep(5);
        
            
            echo "###################################################".PHP_EOL;
            echo "            Deploy IAM Policy for Firehose         ".PHP_EOL;
            echo "###################################################".PHP_EOL;


            $firehose_name = "fh-$this->instance_name-$type";

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

            $policy_name = "KinesisFirehoseServicePolicy-$this->instance_name-$this->region-$firehose_name";
            
            /* Removed for Testing*/
            $policy = $IamClient->createPolicy([
                //'Description' => '<string>',
                //'Path' => '<string>',
                'PolicyDocument' => $jsonperms, // REQUIRED
                'PolicyName' => $policy_name, // REQUIRED
                'Tags' => [
                    [
                        'Key' => 'connect', // REQUIRED
                        'Value' => $this->instance_name, // REQUIRED
                    ],
                    
                ],
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

            sleep(10);


            echo "###################################################".PHP_EOL;
            echo "            Deploy IAM Roles for Firehose          ".PHP_EOL;
            echo "###################################################".PHP_EOL;

            $role_name = "FirehoseServiceRole-$this->instance_name-$this->region-$firehose_name";

            if(strlen($role_name) > 64)
            {
                $role_name = substr($role_name,0,63);
            }

            print $role_name.PHP_EOL; 

            //sleep(10);
            

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

            /* Removed for Testing*/
            $result = $IamClient->createRole([
                'AssumeRolePolicyDocument' => $assumeroledoc, // REQUIRED
                'Description' => 'Allows Kinesis Firehose to transform and deliver data to your destinations using CloudWatch Logs, Lambda, and S3 on your behalf.',
                //'MaxSessionDuration' => <integer>,
                'Path' => "/service-role/",
                //'PermissionsBoundary' => '<string>',
                'RoleName' => $role_name, // REQUIRED
                'Tags' => [
                    [
                        'Key' => 'connect', // REQUIRED
                        'Value' => $this->instance_name, // REQUIRED
                    ],
                    // ...
                ],
            ]);

            print_r($result);

            
            

            
            sleep(10);

            print "Attaching Policy $arn to Role $role_name.".PHP_EOL;

            /* Removed for Testing*/
            $result = $IamClient->attachRolePolicy([
                'PolicyArn' => $arn, // REQUIRED
                'RoleName' => $role_name, // REQUIRED
            ]);

            print_r($result);
            

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

            print_r($policies);

            foreach($policies['AttachedPolicies'] as $policy){
                print_r($policy);
                $versions = $IamClient->listPolicyVersions(['PolicyArn' => $policy['PolicyArn']]); // Gets policy versions

                $p = $IamClient->getPolicyVersion(['PolicyArn' => $policy['PolicyArn'],'VersionId'=>"v1"]); // Gets policy version 

                $json = urldecode($p['PolicyVersion']['Document']); // Decode the urlencoded string into json.

                //$p = $IamClient->listEntitiesForPolicy(['PolicyArn' => $policy['PolicyArn']]); // Gets list of attached groups,users,roles for policy
                //$p = $IamClient->listPoliciesGrantingServiceAccess(['PolicyName' => $policy['PolicyName'],'RoleName' => $rolename]); // Gets policy versions
                print_r($json);
            }

        
            echo "###################################################".PHP_EOL;
            echo "            Deploy Connect Firehose                ".PHP_EOL;
            echo "###################################################".PHP_EOL;   


            $FirehoseClient = $this->FirehoseClient($this->region);

            $fhs = $FirehoseClient->listDeliveryStreams();

            print_r($fhs);

            foreach($fhs['DeliveryStreamNames'] as $fh)
            {
                $firehose = $FirehoseClient->describeDeliveryStream(['DeliveryStreamName' => $fh]);

                print_r($firehose);
            }

            $firehose = new Firehose($this->account_number,$this->region);

            $fh = $firehose->generateKinesisStreamToS3Firehose($this->instance_name, $streamarn, $firehose_name, $bucket, $role_arn, $type);

            $newfh = $FirehoseClient->createDeliveryStream($fh);

            print_r($newfh);

            

            if($type == "ctr"){
                $storage_type = "CONTACT_TRACE_RECORDS";
            }if($type == "agent-events"){
                $storage_type = "AGENT_EVENTS";
            }

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


        $storage_types = [  'CHAT_TRANSCRIPTS',
                            'CALL_RECORDINGS',
                            'SCHEDULED_REPORTS',
                            //'MEDIA_STREAMS',
        ];

        foreach($storage_types as $storage_type){

            $storage = [
                'InstanceId' => $this->instance_id, // REQUIRED
                'ResourceType' => $storage_type, // REQUIRED
                'StorageConfig' => [ // REQUIRED
                    'S3Config' => [
                        'BucketName' => $bucket, // REQUIRED
                        'BucketPrefix' => $storage_type, // REQUIRED
                        /*'EncryptionConfig' => [
                            'EncryptionType' => 'KMS', // REQUIRED
                            'KeyId' => '<string>', // REQUIRED
                        ],*/
                    ],
                    'StorageType' => 'S3', // REQUIRED
                ],
            ];

            $result = $this->ConnectClient->associateInstanceStorageConfig($storage);

            print_r($result);
        }


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

        echo $time . " Creating Instance ID: {$instance_id} with name: {$this->instance_name}".PHP_EOL;
        
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
        
        $this->instance_name = strtolower($this->ask('What is the name of the instance?'));

        //$this->instance_name = strtolower($this->instance_name);

        $instance_array = [
            "IdentityManagementType" => "SAML",
            "InboundCallsEnabled" => true,
            "InstanceAlias" => $this->instance_name,
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
