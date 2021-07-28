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
        $this->restore_hours_of_operation();  // This must be done manually because the AWS API Doesn't support creating. 
        $this->restore_queues(); 
        $this->restore_contact_flow_names();
        $this->restore_contact_flow_content();
        $this->restore_routing_profiles();
        $this->restore_users(); 
        $this->restore_quickconnects();
        $this->restore_queue_quickconnects();


        // Jobs that have to be done manually via the GUI because of lack of API support.
        print_r($this->manual); 
        

    }

    public function restore_queue_quickconnects(){
        
        try{
            $result = $this->ConnectClient->listQueues([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_queues = $result['QueueSummaryList']; 

        print_r($instance_queues); 

        $queues = []; 
        foreach($this->backup->Queues as $queue){
            $queue = json_decode(json_encode($queue), true); 
            //print_r($queue); 
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["source"] = $queue; 
        }

        foreach($instance_queues as $queue){
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["destination"] = $queue; 
        }

        print_r($queues); 

        // Get Quick Connects. 
        try{
            $result = $this->ConnectClient->ListQuickConnects([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_quick_connects = $result['QuickConnectSummaryList']; 

        print_r($instance_quick_connects); 

        $quickconnects = []; 
        foreach($this->backup->QuickConnects as $i){
            $i = json_decode(json_encode($i), true); 
            //print_r($queue); 
            if(!isset($i['Name'])){
                continue;
            }
            if(!array_key_exists($i['Name'], $quickconnects)){
                $quickconnects[$i['Name']] = []; 
            }
            
            $quickconnects[$i['Name']]["source"] = $i; 
        }

        foreach($instance_quick_connects as $i){
            if(!isset($i['Name'])){
                continue;
            }
            if(!array_key_exists($i['Name'], $quickconnects)){
                $quickconnects[$i['Name']] = []; 
            }
            
            $quickconnects[$i['Name']]["destination"] = $i; 
        }

        print_r($quickconnects); 


        print_r($queues); 

        foreach($this->backup->Queues as $object){

            $array = json_decode(json_encode($object), true); 

            if(!isset($array['Name'])){
                continue; 
            }

            $string = json_encode($object); 
            $count = 0; 
            foreach($quickconnects as $i){

                if(!isset($i['source'])){
                    continue;
                }

                if(!$count){
                    $newcontent = str_replace($i['source']['QuickConnectARN'], $i['destination']['Arn'], $string);
                }else{
                    $newcontent = str_replace($i['source']['QuickConnectARN'], $i['destination']['Arn'], $newcontent);
                }
                $count++; 
                $newcontent = str_replace($i['source']['QuickConnectId'], $i['destination']['Id'], $newcontent);
            }

            print_r($object);
            $newcontent = json_decode($newcontent); 

            //print_r($newcontent); 

            $new_array = json_decode(json_encode($newcontent), true); 

            print_r($new_array); 

           

            $queue_quick_connects_backup = $new_array['QuickConnectSummaryList']; 
            
            $queue_id = $queues[$new_array['Name']]["destination"]['Id'];

            try{
                $result = $this->ConnectClient->listQueueQuickConnects([
                    'InstanceId' => $this->instance_id, // REQUIRED
                    'QueueId' => $queue_id, // REQUIRED
                ]);
                print_r($result);
            }catch(ConnectException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                die();
                return;
            }

            $queue_quick_connects_current = $result['QuickConnectSummaryList']; 

            print_r($queue_quick_connects_backup); 
            print_r($queue_quick_connects_current);
            
            // Rekey the array so we can search by name. 
            $backup_qqs = []; 
            foreach($queue_quick_connects_backup as $i){
                print_r($i); 
                $backup_qqs[$i['Name']] = $i; 
            }

            $current_qqs = []; 
            foreach($queue_quick_connects_current as $i){
                print_r($i); 
                $current_qqs[$i['Name']] = $i;  
            }


            foreach($backup_qqs as $qq){
                if(!array_key_exists($qq['Name'], $current_qqs)){
                    try{
                        $result = $this->ConnectClient->associateQueueQuickConnects([
                            'InstanceId' => $this->instance_id, // REQUIRED
                            'QueueId' => $queue_id, // REQUIRED
                            'QuickConnectIds' => [$qq['Id']], // REQUIRED
                        ]);
                        print_r($result);
                        print "Associated QuickConnect {$qq['Name']} to $queue_id!!!".PHP_EOL; 
                    }catch(ConnectException $e){
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        die();
                        return;
    
                    }
                }
            }

            foreach($current_qqs as $qq){
                if(!array_key_exists($qq['Name'], $backup_qqs)){
                    try{
                        $result = $this->ConnectClient->disassociateQueueQuickConnects([
                            'InstanceId' => $this->instance_id, // REQUIRED
                            'QueueId' => $queue_id, // REQUIRED
                            'QuickConnectIds' => [$qq['Id']], // REQUIRED
                        ]);
                        print_r($result);
                        print "Disassociated QuickConnect {$qq['Name']} from $queue_id!!!".PHP_EOL; 
                    }catch(ConnectException $e){
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        die();
                        return;
    
                    }
                }
            }

            try{
                $result = $this->ConnectClient->listQueueQuickConnects([
                    'InstanceId' => $this->instance_id, // REQUIRED
                    'QueueId' => $queue_id, // REQUIRED
                ]);
                print_r($result);
            }catch(ConnectException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                die();
                return;
            }

            sleep(2);

        }
    }

    public function restore_quickconnects(){
        
        try{
            $result = $this->ConnectClient->listQueues([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_queues = $result['QueueSummaryList']; 

        print_r($instance_queues); 

        $queues = []; 
        foreach($this->backup->Queues as $queue){
            $queue = json_decode(json_encode($queue), true); 
            //print_r($queue); 
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["source"] = $queue; 
        }

        foreach($instance_queues as $queue){
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["destination"] = $queue; 
        }

        print_r($queues); 

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

        $flows = []; 

        // Get Source Flow
        foreach($ContactFlows as $flow){
            $flow = json_decode(json_encode($flow), true); 
            print_r($flow); 
            if(!array_key_exists($flow['Name'], $flows)){
                $flows[$flow['Name']] = []; 
            }
            
            $flows[$flow['Name']]["source"] = $flow; 
        }

        // Get Destination Flow
        foreach($instance_flows as $flow){
            if(!array_key_exists($flow['Name'], $flows)){
                $flows[$flow['Name']] = []; 
            }
            
            $flows[$flow['Name']]["destination"] = $flow; 
        }

        //print_r($flows); 

        $QuickConnects = $this->backup->QuickConnects;

        try{
            $result = $this->ConnectClient->listQuickConnects([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_users = $result['QuickConnectSummaryList']; 

        foreach($QuickConnects as $object){

             

            $string = json_encode($object); 
            $count = 0; 
            foreach($queues as $i){

                if(!isset($i['source'])){
                    continue;
                }

                if(!$count){
                    $newcontent = str_replace($i['source']['QueueArn'], $i['destination']['Arn'], $string);
                }else{
                    $newcontent = str_replace($i['source']['QueueArn'], $i['destination']['Arn'], $newcontent);
                }
                $count++; 
                $newcontent = str_replace($i['source']['QueueId'], $i['destination']['Id'], $newcontent);
            }

            foreach($flows as $i){
                if(!isset($i['source'])){
                    continue;
                }
                print_r($i); 
                $newcontent = str_replace($i['source']['Arn'], $i['destination']['Arn'], $newcontent);
                $newcontent = str_replace($i['source']['Id'], $i['destination']['Id'], $newcontent);
            }
            print_r($object);
            $newcontent = json_decode($newcontent); 

            //print_r($newcontent); 

            $new_object = json_decode(json_encode($newcontent), true); 

            //print_r($new_object); 

            //die();

            

            // set array keys to queue name. 
            $current_users = []; 
            foreach($instance_users as $u){
                $current_users[$u['Name']] = $u; 
            }

            unset($new_object['QuickConnectId']);
            unset($new_object['QuickConnectARN']);
            $new_object['InstanceId'] = $this->instance_id; 
            

            if(!array_key_exists($new_object['Name'], $current_users)){

                //print_r($object); 

                $this->manual[] = $new_object; 

                print_r($new_object); 

               

                try{
                    $result = $this->ConnectClient->createQuickConnect($new_object);
                    print_r($result);
                    print "Created QuickConnect {$new_object['Name']}!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;

                }

            }else{
                print "QuickConnect {$new_object['Name']} exists... Need to update the object to reflect backup configuration".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }

            sleep(2);

        }
    }

    public function restore_users(){
        
        // Get Routing Profiles from backup and compare Instnace Routing Profiles
        try{
            $result = $this->ConnectClient->listRoutingProfiles([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_routing_profiles = $result['RoutingProfileSummaryList']; 

        $routing_profiles = []; 
        foreach($this->backup->RoutingProfiles as $profile){
            $profile = json_decode(json_encode($profile), true); 
            //print_r($profile); 
            if(!array_key_exists($profile['Name'], $routing_profiles)){
                $routing_profiles[$profile['Name']] = []; 
            }
            
            $routing_profiles[$profile['Name']]["source"] = $profile; 
        }

        foreach($instance_routing_profiles as $profile){
            if(!array_key_exists($profile['Name'], $routing_profiles)){
                $routing_profiles[$profile['Name']] = []; 
            }
            
            $routing_profiles[$profile['Name']]["destination"] = $profile; 
        }

        print_r($routing_profiles); 

        

        // Get Routing Profiles from backup and compare Instnace Routing Profiles
        try{
            $result = $this->ConnectClient->listSecurityProfiles([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_security_profiles = $result['SecurityProfileSummaryList']; 

        $security_profiles = []; 
        foreach($this->backup->SecurityProfiles as $profile){
            $profile = json_decode(json_encode($profile), true); 
            //print_r($profile); 
            if(!array_key_exists($profile['Name'], $security_profiles)){
                $security_profiles[$profile['Name']] = []; 
            }
            
            $security_profiles[$profile['Name']]["source"] = $profile; 
        }

        foreach($instance_security_profiles as $profile){
            if(!array_key_exists($profile['Name'], $security_profiles)){
                $security_profiles[$profile['Name']] = []; 
            }
            
            $security_profiles[$profile['Name']]["destination"] = $profile; 
        }

        print_r($security_profiles); 

        


        $Users = $this->backup->Users;

        foreach($Users as $object){

            $string = json_encode($object); 
            $count = 0; 
            foreach($routing_profiles as $i){

                //print_r($queue); 

                if(!$count){
                    $newcontent = str_replace($i['source']['RoutingProfileArn'], $i['destination']['Arn'], $string);
                }else{
                    $newcontent = str_replace($i['source']['RoutingProfileArn'], $i['destination']['Arn'], $newcontent);
                }
                $count++; 
                $newcontent = str_replace($i['source']['RoutingProfileId'], $i['destination']['Id'], $newcontent);
            }

            foreach($security_profiles as $i){
                $newcontent = str_replace($i['source']['Arn'], $i['destination']['Arn'], $newcontent);
                $newcontent = str_replace($i['source']['Id'], $i['destination']['Id'], $newcontent);
            }

            $newcontent = json_decode($newcontent); 

            print_r($newcontent); 

            $new_user = json_decode(json_encode($newcontent), true); 

            print_r($new_user); 

            try{
                $result = $this->ConnectClient->listUsers([
                    'InstanceId' => $this->instance_id, // REQUIRED
                ]);
                print_r($result);
            }catch(AwsException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }

            $instance_users = $result['UserSummaryList']; 

            // set array keys to queue name. 
            $current_users = []; 
            foreach($instance_users as $u){
                $current_users[$u['Username']] = $u; 
            }

            unset($new_user['Id']);
            unset($new_user['Arn']);
            unset($new_user['DirectoryUserId']);
            unset($new_user['PhoneConfig']['DeskPhoneNumber']);

            $new_user['InstanceId'] = $this->instance_id; 

            $new_user['InstanceId'] = $this->instance_id; 
            
            print_r($new_user); 


            if(!array_key_exists($new_user['Username'], $current_users)){

                //print_r($object); 

                $this->manual[] = $new_user; 

                print_r($new_user); 

               

                try{
                    $result = $this->ConnectClient->createUser($new_user);
                    print_r($result);
                    print "Created User {$new_user['Username']}!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;

                }

                sleep(2);

            }else{
                print "User {$new_user['Username']} exists... Need to update the object to reflect backup configuration".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }

        }
    
    }

    public function restore_routing_profiles(){
        
        try{
            $result = $this->ConnectClient->listQueues([
                'InstanceId' => $this->instance_id, // REQUIRED
            ]);
            //print_r($result);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        $instance_queues = $result['QueueSummaryList']; 

        $queues = []; 
        foreach($this->backup->Queues as $queue){
            $queue = json_decode(json_encode($queue), true); 
            //print_r($queue); 
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["source"] = $queue; 
        }

        foreach($instance_queues as $queue){
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["destination"] = $queue; 
        }

        //print_r($queues); 


        $RoutingProfiles = $this->backup->RoutingProfiles;

        foreach($RoutingProfiles as $object){

            
            $array = $object->RoutingProfileQueueConfigSummaryList;
            $set = 0; 
            $new_array = []; 
            foreach($array as $q){
                $string = json_encode($q); 
                
                $count = 0; 
                $newcontent = ""; 

                $string = stripslashes($string);
                
                foreach($queues as $queue){

                    //print_r($queue); 

                    if(!$count){
                        $newcontent = str_replace($queue['source']['QueueArn'], $queue['destination']['Arn'], $string);
                    }else{
                        $newcontent = str_replace($queue['source']['QueueArn'], $queue['destination']['Arn'], $newcontent);
                    }
                    $count++; 
                    $newcontent = str_replace($queue['source']['QueueId'], $queue['destination']['Id'], $newcontent);

                    if(!$set){
                        print $object->DefaultOutboundQueueId.PHP_EOL; 
                        $defaultOutboundQueueId = str_replace($queue['source']['QueueId'], $queue['destination']['Id'], $object->DefaultOutboundQueueId);
                        print $defaultOutboundQueueId.PHP_EOL; 
                        if($defaultOutboundQueueId != $object->DefaultOutboundQueueId){
                            $set = 1; 
                        }
                    }
                }

                $newcontent = json_decode($newcontent); 

                $new_array[] = $newcontent; 
            }
            
            print_r($new_array);

            print "Default Queue ID: ".$defaultOutboundQueueId.PHP_EOL; 


            // Build Queue Member Array
            $qs = []; 
            foreach($new_array as $array){
                $queue = [
                            'Delay' => $array->Delay, // REQUIRED
                            'Priority' => $array->Priority, // REQUIRED
                            'QueueReference' => [ // REQUIRED
                                'Channel' => $array->Channel, // REQUIRED
                                'QueueId' => $array->QueueId, // REQUIRED
                            ],
                        ]; 

                $qs[] = $queue; 
            }
            $queueConfigs = $qs; 

            $medias = []; 
            foreach($object->MediaConcurrencies as $i){
                $i->Concurrency = 1; 
                $array = json_decode(json_encode($i), true);
                $medias[] = $array; 
            }

            $mediaConcurrencies = json_decode(json_encode($object->MediaConcurrencies), true);

            print_r($mediaConcurrencies); 
            
            // Build Routing Profile and assign Queue Members
            $profile = [
                'DefaultOutboundQueueId' => $defaultOutboundQueueId, // REQUIRED
                'Description' => $object->Description, // REQUIRED
                'InstanceId' => $this->instance_id, // REQUIRED
                'MediaConcurrencies' => $medias, 
                'Name' => $object->Name, // REQUIRED
                'QueueConfigs' => $queueConfigs,
                //'Tags' => ['<string>', ...],
            ]; 

            try{
                $result = $this->ConnectClient->listRoutingProfiles([
                    'InstanceId' => $this->instance_id, // REQUIRED
                ]);
                print_r($result);
            }catch(AwsException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }

            $instance_routing_profiles = $result['RoutingProfileSummaryList']; 

            // set array keys to queue name. 
            $current_profiles = []; 
            foreach($instance_routing_profiles as $p){
                $current_profiles[$p['Name']] = $p; 
            }

            if(!array_key_exists($object->Name, $current_profiles)){

                //print_r($object); 

                $this->manual[] = $object; 

                print_r($profile); 

               

                try{
                    $result = $this->ConnectClient->createRoutingProfile($profile);
                    print_r($result);
                    print "Created Routing Profile $object->Name!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;

                }

                sleep(2);

            }else{
                print "ContactFlow $object->Name exists... Need to update the object to reflect backup configuration".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }

            try{
                $result = $this->ConnectClient->listRoutingProfiles([
                    'InstanceId' => $this->instance_id, // REQUIRED
                ]);
                print_r($result);
            }catch(AwsException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }

            

            $instance_routing_profiles = $result['RoutingProfileSummaryList']; 

            // set array keys to queue name. 
            $current_profiles = []; 
            foreach($instance_routing_profiles as $p){
                $current_profiles[$p['Name']] = $p; 
            }

            print_r($current_profiles); 

            if(isset($current_profiles[$object->Name])){
                $routingProfile = $current_profiles[$object->Name];
                print_r($routingProfile); 
                
                $update = [
                    'InstanceId' => $this->instance_id, // REQUIRED
                    'MediaConcurrencies' => $mediaConcurrencies,
                    'RoutingProfileId' => $routingProfile['Id'], // REQUIRED
                ]; 
    
                
                // Update the concurrency limits for the routing profile. 
                try{
                    $result = $this->ConnectClient->updateRoutingProfileConcurrency($update);
                    print_r($result);
                    print "Updated Routing Profile $object->Name!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;
    
                }

                sleep(2);
            }
        }
    }

    public function restore_contact_flow_names(){
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

        print_r($instance_flows); 
        //die();

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

            foreach($instance_flows as $flow){
                if($flow['ContactFlowType'] == $type){
                    try{
                        $result = $this->ConnectClient->describeContactFlow([
                            'ContactFlowId' => $flow['Id'], // REQUIRED
                            'InstanceId' => $this->instance['Id'], // REQUIRED
                        ]);
                        print_r($result);
                        sleep(2);
                    }catch(AwsException $e){
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        return;
                    }
                    break;
                }
            }

            $content = $result['ContactFlow']['Content']; 

            print_r($content); 
            //$blank_content = ""; 

            //$default_content = json_decode($blank_content); 
            //die();

            $flow = [
                'Content' => $content, // REQUIRED
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

                sleep(2);

            }else{
                print "ContactFlow $name exists... update to the backup state".PHP_EOL; 
                
                /* TODO - ADD Overwrite functionality */
            }
        }

        return;
    }

    public function restore_contact_flow_content(){
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

        $flows = []; 

        // Get Source Flow
        foreach($ContactFlows as $flow){
            $flow = json_decode(json_encode($flow), true); 
            print_r($flow); 
            if(!array_key_exists($flow['Name'], $flows)){
                $flows[$flow['Name']] = []; 
            }
            
            $flows[$flow['Name']]["source"] = $flow; 
        }

        // Get Destination Flow
        foreach($instance_flows as $flow){
            if(!array_key_exists($flow['Name'], $flows)){
                $flows[$flow['Name']] = []; 
            }
            
            $flows[$flow['Name']]["destination"] = $flow; 
        }

        print_r($flows); 
        
        //die();
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
        
        $hours = []; 
        foreach($this->backup->HoursOfOperations as $hour){
            $hour = json_decode(json_encode($hour), true); 
            print_r($hour); 
            if(!array_key_exists($hour['Name'], $hours)){
                $hours[$hour['Name']] = []; 
            }
            
            $hours[$hour['Name']]["source"] = $hour; 
        }

        foreach($instance_hours as $hour){
            if(!array_key_exists($hour['Name'], $hours)){
                $hours[$hour['Name']] = []; 
            }
            
            $hours[$hour['Name']]["destination"] = $hour; 
        }

        print_r($hours); 


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

        $queues = []; 
        foreach($this->backup->Queues as $queue){
            $queue = json_decode(json_encode($queue), true); 
            print_r($queue); 
            if(!isset($queue['Name'])){
                continue;
            }
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["source"] = $queue; 
        }

        foreach($instance_queues as $queue){
            if(!array_key_exists($queue['Name'], $queues)){
                $queues[$queue['Name']] = []; 
            }
            
            $queues[$queue['Name']]["destination"] = $queue; 
        }

        print_r($queues); 
        

        $ContactFlows = $this->backup->ContactFlows;

        foreach($ContactFlows as $object){

            print_r($object); 

            // Extract the variables we need to build the queue from backup. 
            $name = $object->Name; 
            $type = $object->Type; 

            $content = $object->Content; 
            $newcontent = ""; 
            $count = 0; 

            print_r($flows); 

            //die();
            
            foreach($flows as $flow){

                if(isset($flow['source'])){
                    if(!$count){
                        $newcontent = str_replace($flow['source']['Arn'], $flow['destination']['Arn'],$content);
                    }else{
                        $newcontent = str_replace($flow['source']['Arn'], $flow['destination']['Arn'],$newcontent);
                    }
                    $count++; 
                    
                    $newcontent = str_replace($flow['source']['Id'], $flow['destination']['Id'], $newcontent);
                }
            }
            

            //print_r($flows[$name]); 

            // Fix Flow Info
            //$newcontent = str_replace($flows[$name]['source']['Arn'], $flows[$name]['destination']['Arn'], $content);
            //$newcontent = str_replace($flows[$name]['source']['Id'], $flows[$name]['destination']['Id'], $newcontent);

            // Fix Prompt Info
            foreach($prompts as $prompt){
                if(!$count){
                    $newcontent = str_replace($prompt['source']['Arn'], $prompt['destination']['Arn'],$newcontent);
                }else{
                    $newcontent = str_replace($prompt['source']['Arn'], $prompt['destination']['Arn'],$newcontent);
                }
                $count++; 
                
                $newcontent = str_replace($prompt['source']['Id'], $prompt['destination']['Id'], $newcontent);
            }

            // Fix Hours of Operation Info

            // Fix Prompt Info
            foreach($hours as $hour){
                print_r($hour); 
                $newcontent = str_replace($hour['source']['HoursOfOperationArn'], $hour['destination']['Arn'], $newcontent);
                $newcontent = str_replace($hour['source']['HoursOfOperationId'], $hour['destination']['Id'], $newcontent);
            }

            // Fix Queue Info
            foreach($queues as $queue){
                print_r($queue); 
                $newcontent = str_replace($queue['source']['QueueArn'], $queue['destination']['Arn'], $newcontent);
                $newcontent = str_replace($queue['source']['QueueId'], $queue['destination']['Id'], $newcontent);
            }

            // Fix Hours of Instance Info
            $newcontent = str_replace($this->backup->Instance->Arn, $this->instance['Arn'], $newcontent); 
            $newcontent = str_replace($this->backup->Instance->Id, $this->instance['Id'], $newcontent); 
            
            
            print_r($newcontent);

            //die();

            $flow = [
                'ContactFlowId' => $flows[$name]['destination']['Id'], // REQUIRED
                'Content' => $newcontent, // REQUIRED
                'InstanceId' => $this->instance_id, // REQUIRED
            ]; 

            if(array_key_exists($name, $current_flows)){

                //print_r($object); 

                $this->manual[] = $object; 

                print_r($flow); 

               

                try{
                    $result = $this->ConnectClient->updateContactFlowContent($flow);
                    print_r($result);
                    print "Created Flow $name!!!".PHP_EOL; 
                }catch(ConnectException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                    return;

                }

            }else{
                print "ContactFlow $name doesn't exist... Please Create it prior to updating".PHP_EOL; 
                return; 
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
            if(!isset($array['Name'])){
                continue; 
            }
            $current_queues[$queue['Name']] = $queue; 
        }

        $Queues = $this->backup->Queues;

        foreach($Queues as $object){

            $array = json_decode(json_encode($object), true); 
            // Extract the variables we need to build the queue from backup. 

            if(!isset($array['Name'])){
                continue; 
            }
            $name = $array['Name']; 
            $description = $array['Description']; 
            $hours_id = $array['HoursOfOperationId']; 
            
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

                print_r($array); 

                $this->manual[] = $array; 

                

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

            print $name.PHP_EOL;

            if(!array_key_exists($name, $current_hours)){

                print_r($hours);

                print "Need to create manually Hours of operation for $name.".PHP_EOL; 

                print "Amazon doesn't have an API for Hours of Operation!!! Ugh... ".PHP_EOL; 

                $choices = ["yes","no"]; 
                
                $added = $this->choice('Have you manually added this?', $choices);

                if($added == "no"){
                    print "Please add manually and try again. Good Bye..." ; 
                    die(); 
                }

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
