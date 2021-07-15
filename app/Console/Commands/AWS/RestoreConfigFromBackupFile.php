<?php

namespace App\Console\Commands\AWS;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

use App\Models\Company;
use App\Models\Account;
use Aws\Connect\ConnectClient;


use Illuminate\Console\Command;

class RestoreConfigFromBackupFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:restore-instance-from-backup-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push Config from backup to Instance';

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

        $this->company_id = null; 
        $this->company_name = null; 
        $this->account_number = null; 

        $this->prompt(); 
    
        $backuppath = storage_path("app/backups/$this->company_name/$this->account_number/"); 

        $instances = array_diff(scandir($backuppath), array('.', '..'));

        print_r($instances);

        $instance = $this->choice('Plese choose the instance of backup file to use.', $instances);

        $files = array_diff(scandir($backuppath.$instance), array('.', '..'));

        print_r($files);

        $file = $this->choice('Plese choose backup file to use.', $files);

        $filepath = $backuppath.$instance."/".$file; 

        $backup = file_get_contents($filepath);

        $this->backup = json_decode($backup); 

        print_r($this->backup); 

        /*
        foreach($array as $type => $objects){
            print_r($type); 
            print_r($objects); 

        }
        */

        echo "###################################################".PHP_EOL;
        echo "                Restore to Instance                ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        print "Select where to restore to... ".PHP_EOL;
        $this->company_id = null;
        $this->company_name = null;
        $this->account_number = null;

        $this->prompt(); 

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

        $region = $this->choice('Plese choose the region of the instance to restore.', $regions);


        $client = new ConnectClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ]
        ]); 

        try{
            $result = $client->listInstances();
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        if($result && isset($result['InstanceSummaryList'])){
            $instances = $result['InstanceSummaryList']; 
        }else{
            print "No instances returned from AWS... "; 
            return; 
        }

        print_r($instances); 

        $keyindex = []; 
        $instance_alias = []; 
        foreach($instances as $instance){
            $keyindex[$instance['InstanceAlias']] = $instance;
            $instance_alias[] = $instance['InstanceAlias']; 
        }

        print_r($keyindex); 

        $instance = $this->choice('Plese choose the Instance to restore the backup to.', $instance_alias);
        print_r($instance); 

        print_r($keyindex[$instance]); 

        $instace_id = $keyindex[$instance]['Id']; 

        try{
            $result = $client->listHoursOfOperations([
                'InstanceId' => $instace_id, // REQUIRED
            ]);
            print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_hours = $result['HoursOfOperationSummaryList']; 
        $current_hours = []; 
        foreach($instance_hours as $hours){
            $current_hours[$hours['Name']] = $hours; 
        }

        $hourOfOperation = $this->backup->HoursOfOperations; 

        foreach($hourOfOperation as $hours){
            $name = $hours->Name; 

            if(!array_key_exists($name, $current_hours)){

                print "Need to create manually Hours of operation for $name.".PHP_EOL; 

                print "Amazon doesn't have an API for Hours of Operation!!! Ugh... ".PHP_EOL; 

                print_r($hours); 

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
    }

}
