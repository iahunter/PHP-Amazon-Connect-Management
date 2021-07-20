<?php

namespace App\Console\Commands\AWS;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

use App\Models\Company;
use App\Models\Account;
use Aws\Connect\ConnectClient;

use Aws\Exception\AwsException; 
use Aws\Connect\Exception\ConnectException; 


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


        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ]
        ]); 

        try{
            $result = $this->ConnectClient->listInstances();
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

        $this->instance_id = $keyindex[$instance]['Id']; 
        $this->instance = $keyindex[$instance]; 

        $this->manual = [];
        

        // Jobs to restore
        //$this->restore_hours_of_operation(); 
        //$this->restore_queues(); 
        $this->restore_contact_flows();

        // Jobs that have to be done manually via the GUI because of lack of API support.
        print_r($this->manual); 
        

    }

    public function restore_contact_flows(){
        try{
            $result = $this->ConnectClient->listContactFlows([
                'InstanceId' => $this->instance['Id'], // REQUIRED
            ]);
            print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_flows = $result['ContactFlowSummaryList']; 

        // set array keys to queue name. 
        $current_flows = []; 
        foreach($instance_flows as $flow){
            $current_flows[$flow['Name']] = $flow; 
        }

        $ContactFlows = $this->backup->ContactFlows;

        // Get prompts to replace 
        try{
            $result = $this->ConnectClient->listPrompts([
                'InstanceId' => $this->instance['Id'], // REQUIRED
            ]);
            print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $prompts = []; 

        // Get Source Prompts
        $backup_prompts = $this->backup->Prompts;

        foreach($backup_prompts as $prompt){
            $prompt = json_decode(json_encode($prompt), true); 
            print_r($prompt); 
            if(!array_key_exists($prompt['Name'], $prompts)){
                $prompts[$prompt['Name']] = []; 
            }
            
            $prompts[$prompt['Name']]["source"] = $prompt; 
        }

        // Get Destination Prompts
        foreach($result['PromptSummaryList'] as $prompt){
            if(!array_key_exists($prompt['Name'], $prompts)){
                $prompts[$prompt['Name']] = []; 
            }
            
            $prompts[$prompt['Name']]["destination"] = $prompt; 
        }

        print_r($prompts); 

        

        // set array keys to queue name. 
        $current_flows = []; 
        foreach($instance_flows as $flow){
            $current_flows[$flow['Name']] = $flow; 
        }

        $ContactFlows = $this->backup->ContactFlows;

        foreach($ContactFlows as $object){

            // Extract the variables we need to build the queue from backup. 
            $name = $object->Name; 
            $type = $object->Type; 

            $content = $object->Content; 
            $newcontent = ""; 
            $count = 0; 
            foreach($prompts as $prompt){
                if(!$count){
                    $newcontent = str_replace($prompt['source']['Arn'], $prompt['destination']['Arn'],$content);
                }else{
                    $newcontent = str_replace($prompt['source']['Arn'], $prompt['destination']['Arn'],$newcontent);
                }
                $count++; 
                
                $newcontent = str_replace($prompt['source']['Id'], $prompt['destination']['Id'], $newcontent);
            }

            $newcontent = str_replace($this->backup->Instance->Arn, $this->instance['Arn'], $newcontent); 
            $newcontent = str_replace($this->backup->Instance->Id, $this->instance['Id'], $newcontent); 
            
            print $this->backup->Instance->Id.PHP_EOL; 
            print $this->instance['Arn'].PHP_EOL; 

            print_r($newcontent);

            //die();

            $flow = [
                'Content' => $newcontent, // REQUIRED
                //'Description' => '<string>',
                'InstanceId' => $this->instance_id, // REQUIRED
                'Name' => $name, // REQUIRED
                //'Tags' => ['<string>', ...],
                'Type' => $type, // REQUIRED
            ]; 

            if(property_exists($object, "Description")){
                $flow['Description'] = $object->Description; 
            }


            if(!array_key_exists($name, $current_flows)){

                //print_r($object); 

                $this->manual[] = $object; 

                print_r($flow); 

               

                try{
                    $result = $this->ConnectClient->createContactFlow($flow);
                    print_r($result);
                    print "Created Flow $name!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;

                }

            }else{
                print "ContactFlow $name exists... update to the backup state".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }
        }

        return;
    }

    public function restore_queues(){
        try{
            $result = $this->ConnectClient->listQueues([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_queues = $result['QueueSummaryList']; 

        // set array keys to queue name. 
        $current_queues = []; 
        foreach($instance_queues as $queue){
            $current_queues[$queue['Name']] = $queue; 
        }

        $Queues = $this->backup->Queues;



        foreach($Queues as $object){

            // Extract the variables we need to build the queue from backup. 
            $name = $object->Name; 
            $description = $object->Description; 
            $hours_id = $object->HoursOfOperationId; 
            
            // Loop thru and try to find the Hours of Operation ID Name so we can try to find the new one that was created. 
            
            try{
                $result = $this->ConnectClient->listHoursOfOperations([
                    'InstanceId' => $this->instance_id, // REQUIRED
                ]);
                print_r($result);
            }catch(AwsException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }
    
            $instance_hours = $result['HoursOfOperationSummaryList']; 
            
            foreach($this->backup->HoursOfOperations as $i){
                if($i->HoursOfOperationId ==  $hours_id){
                    $hours_name = $i->Name; 
                }
            }

            foreach($instance_hours as $hours){
                if($hours['Name'] == $hours_name){
                    $hours_id = $hours['Id']; 
                }
            }

            $queue = [
                'InstanceId' => $this->instance_id, // REQUIRED
                'Description' => $description,
                'HoursOfOperationId' => $hours_id, // REQUIRED
                //'MaxContacts' => '<integer>',
                'Name' => $name, // REQUIRED
                
                //'QuickConnectIds' => ['<string>'],
                //'Tags' => ['<string>'],
            ]; 

            if(isset($object->OutboundCallerConfig)){
                $callerid_name = $object->OutboundCallerConfig->OutboundCallerIdName; 
                $queue['OutboundCallerConfig'] = [
                    'OutboundCallerIdName' => $callerid_name,
                    //'OutboundCallerIdNumberId' => '<string>',
                    //'OutboundFlowId' => '<string>',
                ];
            }

            if(!array_key_exists($name, $current_queues)){

                print_r($object); 

                $this->manual[] = $object; 

                

                try{
                    $result = $this->ConnectClient->createQueue($queue);
                    print_r($result);
                    print "Created Queue $name!!!".PHP_EOL; 
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    return;
                }

            }else{
                print "Queue $name exists... update to the backup state".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }
        }

        return;
    }


    public function restore_hours_of_operation(){
        try{
            $result = $this->ConnectClient->listHoursOfOperations([
                'InstanceId' => $this->instance_id, // REQUIRED
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

                $this->manual[] = $hours; 

            }
        }

        return;
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
