<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;


use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use Aws\Connect\ConnectClient;

use Aws\Iam\IamClient;
use Aws\Kms\KmsClient;

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

use App\AWS\IAM;

class DeployConnectSamlAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:deploy-connect-saml-auth-azure-ad {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy SAML Authentication with Azure AD for Conenct Instance';

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
        /*
            Command to setup SAML Integration with Azure AD based on this Blog. 
            https://aws.amazon.com/blogs/contact-center/configure-single-sign-on-using-microsoft-azure-active-directory-for-amazon-connect/
        */

        $this->application = "awc";

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
            
        
        //print $this->company_id.PHP_EOL;
        //print $this->account_number.PHP_EOL; 

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

        $instance_array = [];
        $instance_names = [];

        foreach($regions as $region){
            $client = new ConnectClient([
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => [
                    'key'    => $this->app_key,
                    'secret' => $this->app_secret,
                ],
            ]);
            
            try{
                $result = $client->listInstances();
                //print_r($result);
            }catch(Exception $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }

            //print_r($result);

            foreach($result['InstanceSummaryList'] as $instance){
                $instance_array[$region][$instance['InstanceAlias']] = $instance;
                $instance_names[] = $instance['InstanceAlias'];
            }
        }

        //print_r($instance_array);

        $this->instance_alias = $this->choice('Instance Name?', $instance_names);

        $splitalias = explode("-", $this->instance_alias);

        print_r($splitalias);

        $this->instance_name = end($splitalias);

        echo $this->instance_name; 


        foreach($instance_array as $region => $instances){

            if(key_exists($this->instance_alias, $instances)){
                $this->region = $region;
                
                $this->instance = $instances[$this->instance_alias];
            }
        }

        
        $this->shortregion = str_replace("-","",$this->region);


        
        echo "###################################################".PHP_EOL;
        echo "                      Deploy SAML                  ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        print "Deploy SAML for Instance: ".$this->instance['InstanceAlias'].PHP_EOL;
        print "Region: ".$this->region.PHP_EOL;

        $ConnectClient = new ConnectClient([
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
                'Value' => strtoupper($this->costcode),
            ],
        ];

        print_r($this->instance);

        $IAM = new IAM($this->account_number, $this->region);

        $IamClient = new IamClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);


        echo "###################################################".PHP_EOL;
        echo "       Creating Azure Federation Policy            ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        // Build JSON Policy Document
        $policydoc = [
                $IAM->getConnectFederationPolicy($this->instance['Arn'])
        ];


        $json = json_encode($policydoc,JSON_UNESCAPED_SLASHES);

        $jsonperms = <<<END
{
"Version":"2012-10-17",
"Statement":$json
}
END;
        print_r($jsonperms);

        //$IamClient = $this->IamClient($this->region);

        print "Creating Policy.".PHP_EOL;



        $policy_name = "iampolicy-$this->environment-$this->shortregion-$this->application-$this->instance_name-azure-federation";

        $policies = $IamClient->listPolicies();
        //print_r($policies);
        
        $policyfound = false;
        foreach($policies['Policies'] as $p)
        {
            if(isset($p['PolicyName']) && $policy_name == $p['PolicyName'])
            {
                $policyfound = true;
                $federationArn = $p['Arn'];
                print "Found Policy already exists..".PHP_EOL;
                //print_r($arn);
                break;

            }
        }
        
        if(!$policyfound)
        {
            
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
            //print_r($policies);

            foreach($policies['Policies'] as $p)
            {
                if(isset($p['PolicyName']) && $policy_name == $p['PolicyName'])
                {
                    $federationArn = $p['Arn'];
                    print "Found Policy Arn: $arn".PHP_EOL;
                    //print_r($arn);
                    break;
                }
            }

        }else{
            print "Policy already exists with name $policy_name".PHP_EOL;
        }



        echo "###################################################".PHP_EOL;
        echo "             Creating Azure CLI Policy            ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        // Build JSON Policy Document
        $policydoc = [
                $IAM->getAzureAdAccessPolicy()
        ];


        $json = json_encode($policydoc,JSON_UNESCAPED_SLASHES);

        $jsonperms = <<<END
{
"Version":"2012-10-17",
"Statement":$json
}
END;
        print_r($jsonperms);

        //$IamClient = $this->IamClient($this->region);

        print "Creating Policy.".PHP_EOL;



        $policy_name = "iampolicy-$this->environment-$this->shortregion-$this->application-$this->instance_name-azure-cli";

        $policies = $IamClient->listPolicies();
        //print_r($policies);
        
        $policyfound = false;
        foreach($policies['Policies'] as $p)
        {
            if(isset($p['PolicyName']) && $policy_name == $p['PolicyName'])
            {
                $policyfound = true;
                $cliPolicyArn = $p['Arn'];
                print "Found Policy already exists..".PHP_EOL;
                //print_r($arn);
                break;

            }
        }
        
        if(!$policyfound)
        {
            
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
                    $cliPolicyArn = $p['Arn'];
                    print "Found Policy Arn: $arn".PHP_EOL;
                    //print_r($arn);
                    break;
                }
            }

        }else{
            print "Policy already exists with name $policy_name".PHP_EOL;
        }

        echo "###################################################".PHP_EOL;
        echo "             Creating Azure CLI User               ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        $username = "iamuser-$this->environment-$this->shortregion-$this->application-$this->instance_name-azure-cli";

        $users = $IamClient->listUsers();

        print_r($users); 

        $foundCliUser = false;
        
        


        if(isset($users['Users'])){
            foreach($users['Users'] as $u)
            {
                if(isset($u['UserName']) && $username == $u['UserName'])
                {
                    $cliUserArn = $u['Arn'];
                    print "Found User Arn: $cliUserArn".PHP_EOL;
                    $foundCliUser = true;
                    //print_r($arn);
                    break;
                }
            }
        }else{
            print "Unexpected response from AWS... ";
            return;
        }

        
        
        
        
        $cliUserAppKey = false;

        if(!$foundCliUser){
            $result = $IamClient->createUser([
                'Path' => '/',
                //'PermissionsBoundary' => '<string>',
                'Tags' => $this->tags,
                'UserName' => $username, // REQUIRED
            ]);

            print_r($result);

            $users = $IamClient->listUsers();

            print_r($users); 

            $foundCliUser = false;
           
            if(isset($users['Users'])){
                foreach($users['Users'] as $u)
                {
                    if(isset($u['UserName']) && $username == $u['UserName'])
                    {
                        $cliUserArn = $u['Arn'];
                        print "Found User Arn: $cliUserArn".PHP_EOL;
                        //print_r($arn);
    
                        // Tag user
                        $result = $IamClient->tagUser([
                            'Tags' => $this->tags,
                            'UserName' => $username, // REQUIRED
                        ]);

                        // Create access key and secret. Save as variable for later. 
                        $cliUserAppKey = $IamClient->createAccessKey([
                            'UserName' => $username, // REQUIRED
                        ]);

                        // Attach policy

                        $result = $IamClient->attachUserPolicy([
                            'PolicyArn' => $cliPolicyArn, // REQUIRED
                            'UserName' => $username, // REQUIRED
                        ]);
            
                        print_r($result);
    
                        break;
                    }
                }
            }else{
                print "Unexpected response from AWS... ";
                return;
            }
        }

        
        

        echo "###################################################".PHP_EOL;
        echo "      Create the Azure CLI User in Azure AD        ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        if($cliUserAppKey){
            print_r($cliUserAppKey);
        }else{

            $keys = $IamClient->listAccessKeys([
                'UserName' => $username,
            ]);

            print_r($keys);
            
            $array = [];
            foreach($keys['AccessKeyMetadata'] as $key){
                $array[] = $key['AccessKeyId'];
            }

            $cliUserAppKey = $this->choice("Select App Key for user: $username", $array);

            $cliUserAppKey = $this->secret("What is the App Secret for User: $username?");
        }

    


        print "Create the Azure AD AWS SAML application in Azure AD using the creds above!!!".PHP_EOL; 

        /* ##############################################################################
                Future??? Add section in Azure CLI that does this automatically??? 
           ##############################################################################
        */ 


        echo "###################################################".PHP_EOL;
        echo "  Create the SAML Provider for Agents in Azure AD   ".PHP_EOL;
        echo "###################################################".PHP_EOL;


        $providerName = "iamsamlprovider-$this->environment-$this->shortregion-$this->application-$this->instance_name-agents";

        $samlProviders = $IamClient->listSAMLProviders();

        $foundAgentSamlProvider = false;

        $regex = "/$providerName$/";

        if(isset($samlProviders['SAMLProviderList'])){
            foreach($samlProviders['SAMLProviderList'] as $u)
            {
                if(isset($u['Arn']) && preg_match($regex, $u['Arn']))
                {
                    $agentSamlProivderArn = $u['Arn'];
                    print "Found Policy Arn: $agentSamlProivderArn".PHP_EOL;
                    $foundAgentSamlProvider = true;
                    //print_r($arn);
                    break;
                }
            }
        }else{
            print "Unexpected response from AWS... ";
            return;
        }

        if(!$foundAgentSamlProvider){
            $metadata = $this->ask('Paste in the SAML Metadata Document from Azure AD to Continue...');

            $result = $IamClient->createSAMLProvider([
                'Name' => $providerName, // REQUIRED
                'SAMLMetadataDocument' => $metadata, // REQUIRED
                'Tags' => $this->tags
            ]);

            $samlProviders = $IamClient->listSAMLProviders();

            $foundAgentSamlProvider = false;

            $regex = "/$providerName$/";

            if(isset($samlProviders['SAMLProviderList'])){
                foreach($samlProviders['SAMLProviderList'] as $u)
                {
                    if(isset($u['Arn']) && preg_match($regex, $u['Arn']))
                    {
                        $agentSamlProivderArn = $u['Arn'];
                        print "Found Saml Arn: $agentSamlProivderArn".PHP_EOL;
                        $foundAgentSamlProvider = true;
                        //print_r($arn);
                        break;
                    }
                }
            }else{
                print "Unexpected response from AWS... ";
                return;
            }
        }

        
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

        $this->costcode = strtoupper($this->ask('What is the Costcode?', "KTGT"));


        //$this->instance_name = strtolower($this->instance_name);

        $environments = [
            "lab",
            "prod"
        ];

        $this->environment = $this->choice('What environment would you like to deploy this in?', $environments);
    }
}
