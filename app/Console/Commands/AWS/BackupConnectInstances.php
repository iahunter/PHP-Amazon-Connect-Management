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
                    $storage = $ConnectInstance->backupStorageConfigs($instance); 
                    $instanceAttributes = $ConnectInstance->backupInstanceAttributes($instance); 
                    $numbers = $ConnectInstance->backupPhoneNumbers($instance); 
                    $flows = $ConnectInstance->backupContactFlows($instance);
                    $users = $ConnectInstance->backupUsers($instance);
                    $uhgs = $ConnectInstance->backupUserHierarchyGroups($instance);
                    $structure = $ConnectInstance->backupUserHierarchyStructure($instance);
                    $routingProfiles = $ConnectInstance->backupRoutingProfiles($instance);
                    $queues = $ConnectInstance->backupQueues($instance);
                    $hours = $ConnectInstance->backupHoursOfOperations($instance);
                    $quickConnects = $ConnectInstance->backupQuickConnects($instance);
                    
                    $backup['Instance'] = $instance;
                    $backup['StorageConfigs'] = $storage; 
                    $backup['InstanceAttributes'] = $instanceAttributes;
                    $backup['PhoneNumbers'] = $numbers;
                    $backup['ContactFlows'] = $flows;
                    $backup['Users'] = $users;
                    $backup['UserHierarchyGroups'] = $uhgs;
                    $backup['UserHierarchyStructure'] = $structure;
                    $backup['RoutingProfiles'] = $routingProfiles;
                    $backup['Queues'] = $queues;
                    $backup['HoursOfOperations'] = $hours;
                    $backup['QuickConnects'] = $quickConnects;

                    print_r($backup);

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
                }
            }
                
        }
    }
}
