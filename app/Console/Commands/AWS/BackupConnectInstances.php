<?php

namespace App\Console\Commands\AWS;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

use Carbon\Carbon;

use Aws\Connect\ConnectClient;
use Aws\Exception\AwsException; 

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

use App\AWS\Connect;

class BackupConnectInstances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:backup-instances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup all Amazon Connect Instances for all Accounts';

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
        $accountnumbers = [];

        $accounts = Account::all(); 

        foreach($accounts as $account){
            $accountnumbers[] = $account->account_number;
        }


        foreach($accountnumbers as $account){
            $this->account_number = $account;

            $account = Account::where('account_number', $this->account_number)->first(); 
            $this->company_id = $account->company_id; 

            $company = Company::find($account->company_id); 

            $this->company_name = $company->name; 

            print $this->company_name.PHP_EOL;
            print $this->account_number.PHP_EOL; 

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
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    return;
                }
                
                foreach($result['InstanceSummaryList'] as $instance){

                    print $instance['Id'].PHP_EOL;

                    if(!Storage::exists('/backups')) {

                        Storage::makeDirectory('/backups', 0775, true); //creates directory
                    }

                    
                    if(!Storage::exists("/backups/$this->company_name/")) {

                        Storage::makeDirectory("/backups/$this->company_name", 0775, true); //creates directory
                    }

                    if(!Storage::exists("/backups/$this->company_name/$this->account_number")) {

                        Storage::makeDirectory("/backups/$this->company_name/$this->account_number", 0775, true); //creates directory
                    }

                    //print_r($instance);
                    $alias = $instance['InstanceAlias']; 

                    if(!Storage::exists("/backups/$this->company_name/$this->account_number/$alias")) {

                        Storage::makeDirectory("/backups/$this->company_name/$this->account_number/$alias", 0775, true); //creates directory
                    }


                    $ConnectInstance = new Connect($region, $this->app_key, $this->app_secret); 
                    $backup = [];

                    //print_r($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up Storage Accounts".PHP_EOL; 
                    $storage = $ConnectInstance->backupStorageConfigs($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up instanceAttributes".PHP_EOL; 
                    $instanceAttributes = $ConnectInstance->backupInstanceAttributes($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up Approved Origins".PHP_EOL; 
                    $instanceApprovedOrigins = $ConnectInstance->backupInstanceApprovedOrigins($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up Lambda Functions".PHP_EOL; 
                    $instanceLambdaFunctions = $ConnectInstance->backupInstanceLambdaFunctions($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up numbers".PHP_EOL; 
                    $numbers = $ConnectInstance->backupPhoneNumbers($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up prompts".PHP_EOL; 
                    $prompts = $ConnectInstance->backupPrompts($instance);

                    print $instance['InstanceAlias']. " | ".  "Backing up flows".PHP_EOL; 
                    $flows = $ConnectInstance->backupContactFlows($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up users".PHP_EOL; 
                    $users = $ConnectInstance->backupUsers($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up statuses".PHP_EOL; 
                    $statuses = $ConnectInstance->backupAgentStatus($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up uhgs".PHP_EOL;
                    $uhgs = $ConnectInstance->backupUserHierarchyGroups($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up structure".PHP_EOL;
                    $structure = $ConnectInstance->backupUserHierarchyStructure($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up routingProfiles".PHP_EOL;
                    $routingProfiles = $ConnectInstance->backupRoutingProfiles($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up securityProfiles".PHP_EOL;
                    $securityProfiles = $ConnectInstance->backupSecurityProfiles($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up queues".PHP_EOL;
                    $queues = $ConnectInstance->backupQueues($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up hours".PHP_EOL;
                    $hours = $ConnectInstance->backupHoursOfOperations($instance);

                    print $instance['InstanceAlias']. " | ". "Backing up quickConnects".PHP_EOL;
                    $quickConnects = $ConnectInstance->backupQuickConnects($instance);
                    print count($quickConnects); 
                    
                    $backup['Instance'] = $instance;
                    $backup['StorageConfigs'] = $storage;


                    $backup['InstanceAttributes'] = $instanceAttributes;

                    // Added 20240208
                    $backup['InstanceApprovedOrigins'] =  $instanceApprovedOrigins; 
                    $backup['InstanceLambdaFunctions'] =  $instanceLambdaFunctions; 

                    $backup['PhoneNumbers'] = $numbers;
                    $backup['Prompts'] = $prompts;
                    $backup['ContactFlows'] = $flows;
                    $backup['AgentStatus'] = $statuses;
                    $backup['Users'] = $users;
                    $backup['UserHierarchyGroups'] = $uhgs;
                    $backup['UserHierarchyStructure'] = $structure;
                    $backup['RoutingProfiles'] = $routingProfiles;
                    $backup['SecurityProfiles'] = $securityProfiles;
                    $backup['Queues'] = $queues;
                    $backup['HoursOfOperations'] = $hours;
                    $backup['QuickConnects'] = $quickConnects;

                    //print_r($backup);

                    $json = json_encode($backup, JSON_PRETTY_PRINT); 
                    //print_r($json); 

                    

                    $data = Instance::updateOrCreate(['name' => $instance['InstanceAlias'], 'instance_id' => $instance['Id'], 'account_id' => $this->account_number], ['region' => $region, 'json' => $json]);

                    //print_r($data);
                    
                    $time = Carbon::now();
                    echo $time.PHP_EOL;

                    //print_r($time);
        
                    //$time = explode(' ', $time);

                    $time = $time->toDateTimeLocalString(); 

                    $time = str_replace(":","-", $time);

                    $filename = storage_path("app/backups/$this->company_name/$this->account_number/$alias/$time.json");

                    touch($filename); 
                    //Storage::put("backups/$this->company_name/$this->account_number/$alias/$time[0]_$time[1].json", $json);
                    file_put_contents($filename, $json);
                    
                    //print_r($queues); 
                    //$storage = $Instance->backupStorageConfigs($instance); 

                    $backupname = $backup['Instance']['InstanceAlias'].".json"; 

                    print "Backup Complete: $backupname".PHP_EOL; 

                    $backuppath = storage_path("app/backups/amazon-connect/$backupname"); 

                    touch($backuppath);

                    file_put_contents($backuppath, $json);

                }
            }
                
        }
    }
}
