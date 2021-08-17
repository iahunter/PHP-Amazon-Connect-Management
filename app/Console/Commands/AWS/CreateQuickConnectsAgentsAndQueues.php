<?php

namespace App\Console\Commands\AWS;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use Aws\Connect\ConnectClient;

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

use Illuminate\Console\Command;

class CreateQuickConnectsAgentsAndQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:create-agent-quickconnects {company?} {account_number?} {region?} {instance?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates an Agent Quick Connect for Every User and Assigns to ALL Queues';

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
            $ConnectClient = new ConnectClient([
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => [
                    'key'    => $this->app_key,
                    'secret' => $this->app_secret,
                ],
            ]);
            
            try{
                $result = $ConnectClient->listInstances();
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

        $instance = null; 

        $this->instance_alias = $this->choice('Instance Name?', $instance_names);

        $splitalias = explode("-", $this->instance_alias);

        //print_r($splitalias);

        $this->instance_name = end($splitalias);

        //echo $this->instance_name; 


        foreach($instance_array as $region => $instances){

            if(key_exists($this->instance_alias, $instances)){
                $this->region = $region;
                
                $this->instance = $instances[$this->instance_alias];
            }
        }

        
        $this->shortregion = str_replace("-","",$this->region);


        $this->instance_id = $this->instance['Id']; 

        print_r($this->instance_id); 

        $starttime = Carbon::now(); 

        $ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);
        
        // Get Custom Flows and Store in the Database
        $users = $ConnectClient->listUsers([
            'InstanceId' => $this->instance_id,
        ]);

        print_r($users);
        
        // Get Quick Connects. 

        

        $list = [];
        try{
            $qconnects = $ConnectClient->listQuickConnects([
                'InstanceId' => $this->instance_id,
            ]);
            print_r($qconnects); 
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        if(isset($qconnects['QuickConnectSummaryList']) && count($qconnects['QuickConnectSummaryList'])){
            foreach($qconnects['QuickConnectSummaryList'] as $i){
                // Need to possibly queue these jobs in case they error out. 
                try{
                    $getresult = $ConnectClient->describeQuickConnect([
                        'QuickConnectId' => $i['Id'],
                        'InstanceId' => $this->instance_id,
                    ]);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    continue;
                }
                
                
                //print_r($getresult);
                $list[] = $getresult['QuickConnect'];

                sleep(1);
            }
        }

        print_r($list); 

        

        

        // Get Custom Flows and Store in the Database
        $flows = $ConnectClient->listContactFlows([
            'InstanceId' => $this->instance_id,
        ]);
        
        //print_r($flows); 

        foreach($flows['ContactFlowSummaryList'] as $flow){
            //print_r($flow); 
            if($flow['Name'] == "Default agent transfer" && $flow['ContactFlowType'] == "AGENT_TRANSFER"){
                $agent_xfr_flow = $flow; 

                break;
            }
        }

        $nameupdates = []; 
        $qqarraybyuserid = []; 
        foreach($list as $qq){
            if($qq['QuickConnectConfig']['QuickConnectType'] ==  'USER'){
                $userid = $qq['QuickConnectConfig']['UserConfig']['UserId']; 

                $qqarraybyuserid[$userid] = $qq; 

                if($qq['Name'] != $qq['QuickConnectConfig']['UserConfig']['UserId']){
                    $nameupdates[$userid] = $qq; 
                }
            }
        }

        print_r($qqarraybyuserid); 

        $userlist = []; 
        foreach($users['UserSummaryList'] as $user){
            $userlist[$user['Id']] = $user; 
        }

        print_r($userlist); 

        $list = null;
        

        print_r($agent_xfr_flow);

        $additions = array_diff_key($userlist, $qqarraybyuserid); 
        $deletetions = array_diff_key($qqarraybyuserid, $userlist); 

        print_r($additions);
        
        foreach($additions as $newqq){

            // Apparently can't use special characters in Name field
            $userarray = explode("@", $newqq['Username']); 
            $qqname = $userarray[0]; 

            $new_object = [
                'Description' => "Agent Transfer Quick Connect",
                'InstanceId' => $this->instance_id, // REQUIRED
                'Name' => $newqq['Username'], // REQUIRED
                'QuickConnectConfig' => [ // REQUIRED
                    'QuickConnectType' => 'USER', // REQUIRED
                    'UserConfig' => [
                        'ContactFlowId' => $agent_xfr_flow['Id'], // REQUIRED
                        'UserId' => $newqq['Id'], // REQUIRED
                    ],
                ],
                //'Tags' => ['<string>', ...],
            ]; 

            try{
                $result = $ConnectClient->createQuickConnect($new_object);
                print_r($result);
                print "Created QuickConnect {$new_object['Name']}!!!".PHP_EOL; 
            }catch(ConnectException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                die();
                return;

            }
        }

        $list = [];
        try{
            $qconnects = $ConnectClient->listQuickConnects([
                'InstanceId' => $this->instance_id,
            ]);
            print_r($qconnects); 
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }

        

        $qqlist = []; 
        foreach($qconnects['QuickConnectSummaryList'] as $qq){
            $qqlist[$qq['Id']] = $qq; 

        }

        print_r($qqlist);
        

        // Get all the queues and check their assigned Quick Connects
        $queues = $ConnectClient->listQueues([
            'InstanceId' => $this->instance_id,
        ]);

        $list = [];

        if(isset($queues['QueueSummaryList']) && count($queues['QueueSummaryList'])){
            foreach($queues['QueueSummaryList'] as $i){

                $getresult = $i;
                // Need to possibly queue these jobs in case they error out. 
                if(isset($i['Name'])){
                    try{
                        $quickconnects = $ConnectClient->listQueueQuickConnects([
                            'QueueId' => $i['Id'],
                            'InstanceId' => $this->instance_id,
                        ]);
                    }catch(AwsException $e){
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        //sleep(1);
                        continue;
                    }

                    $queue_id = $i['Id']; 


                    $queue_quick_connects = []; 
                    if(isset($quickconnects['QuickConnectSummaryList'])){
                        foreach($quickconnects['QuickConnectSummaryList'] as $quickc){
                            $queue_quick_connects[$quickc['Id']] = $quickc; 
                        }
                    }
                    
                    $additions = array_diff_key($qqlist, $queue_quick_connects); 
                    $deletetions = array_diff_key($queue_quick_connects, $qqlist); 

                    print "These will be added to the queue.".PHP_EOL; 
                    print_r($additions); 

                    foreach($additions as $addition){
                        try{
                            $result = $ConnectClient->associateQueueQuickConnects([
                                'InstanceId' => $this->instance_id, // REQUIRED
                                'QueueId' => $queue_id, // REQUIRED
                                'QuickConnectIds' => [$addition['Id']], // REQUIRED
                            ]);
                            //print_r($result);
                            print "Associated QuickConnect {$addition['Name']} to $queue_id!!!".PHP_EOL; 
                            //die(); 
                            sleep(1); 
                        }catch(ConnectException $e){
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                            die();
                            return;
        
                        }
                    }


                    print "These will be deleted from the queue ID $queue_id.".PHP_EOL; 
                    print_r($deletetions); 

                    foreach($deletetions as $deletetion){
                        try{
                            $result = $ConnectClient->disassociateQueueQuickConnects([
                                'InstanceId' => $this->instance_id, // REQUIRED
                                'QueueId' => $queue_id, // REQUIRED
                                'QuickConnectIds' => [$deletetion['Id']], // REQUIRED
                            ]);
                            //print_r($result);
                            print "DisAssociated QuickConnect {$deletetion['Name']} to $queue_id!!!".PHP_EOL; 
                            //die(); 
                            sleep(1); 
                        }catch(ConnectException $e){
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                            die();
                            return;
        
                        }
                    }
                }else{
                    //print_r($i); 
                    //print_r($getresult);
                    
                    $list[] = $getresult;

                    sleep(1);
                }
                
                
            }
        }

        print "Update these Quick Connect Names to Username".PHP_EOL; 
        foreach($nameupdates as $udpate){
            //print_r($udpate); 

            $userid = $udpate['QuickConnectConfig']['UserConfig']['UserId']; 
            $currentname = $udpate['Name']; 
            $newname = $userlist[$userid]['Username']; 
            

            if($newname == $currentname){
                continue; 
            }

            

            try{
                $result = $ConnectClient->updateQuickConnectName([
                    //'Description' => '<string>',
                    'InstanceId' => $this->instance_id, // REQUIRED
                    'Name' => $newname,
                    'QuickConnectId' => $udpate['QuickConnectId'], // REQUIRED
                ]);
                print "Quick Connect $currentname name changed to $newname".PHP_EOL; 
                sleep(1); 
            }catch(ConnectException $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                die();
                return;
            }
            
        }


        $endtime = Carbon::now(); 

        print "Start Time: ". $starttime.PHP_EOL; 
        print "End Time: ". $endtime.PHP_EOL; 
        //print_r($deletetions); 

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
