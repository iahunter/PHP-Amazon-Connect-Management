<?php

namespace App\Console\Commands\AWS;

use Illuminate\Console\Command;

use Aws\Kinesis\KinesisClient;
use Aws\Connect\ConnectClient;
use Aws\Exception\AwsException; 

use Aws\Kinesis\Exception\KinesisException; 

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\AWS\Connect;

use App\Models\AmazonConnect\Agent;
use App\Models\Company;
use App\Models\Account;

class AgentEventMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:agentevents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribes to all instance Agent Streams and Updates Database with agent status';

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

        $scriptstart = Carbon::now(); 

        $stream_monitor_list = []; 

        $accountnumbers = [];

        $accounts = Account::all(); 

        foreach($accounts as $account){
            $accountnumbers[] = $account->account_number;
        }

        $instance_data = []; 

        foreach($accountnumbers as $number){

            //print_r($number);

            $account = Account::where('account_number', $number)->first(); 
            $this->company_id = $account->company_id; 

            $company = Company::find($account->company_id); 

            $instance_data[$number]['company'] = $company->name; 
            $instance_data[$number]['account'] = $account; 

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

            //print_r($instance_data);



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

                $instances = []; 
                foreach($result['InstanceSummaryList'] as $instance){
                    //print_r($instance);

                    $getresult = $client->listInstanceStorageConfigs([
                        'InstanceId' => $instance['Id'],
                        'ResourceType' => 'AGENT_EVENTS',
                    ]);

                    
                    //print_r($getresult);

                    if(!empty($getresult['StorageConfigs'])){
                        
                    

                        $stream = $getresult['StorageConfigs'][0]; 
                        $stream_arn = $stream['KinesisStreamConfig']['StreamArn']; 
                        $array = explode("/", $stream_arn); 

                        $stream_name = $array[1]; 

                        print "Found Agent Stream: $stream_name".PHP_EOL;
                        $instances[$instance['Id']] = $stream_name;

                        $stream_monitor_list[$stream_name]['accountid'] = $number; 
                        $stream_monitor_list[$stream_name]['app_key'] = $this->app_key; 
                        $stream_monitor_list[$stream_name]['app_secret'] = $this->app_secret; 
                        $stream_monitor_list[$stream_name]['region'] = $region; 
                        $stream_monitor_list[$stream_name]['instance'] = $instance['Id']; 


                    
                    }else{
                        $instances[$instance['Id']] = null;
                    }

                    //die(); 


                    
                }

                

                $instance_data[$number]['regions'][$region] = $instances;

                //print_r($stream_monitor_list);
                
            }
        
        }

        print_r($stream_monitor_list);

       
        /*************************************************** */
        // Need to get a list of all accounts with account login info. Each Region will have Connect Instnaces with Kinesis enabled and fetch the streams. 
        /*************************************************** */

        foreach($stream_monitor_list as $streamname => $array){

            $KinesisClient = new KinesisClient([
                'version'     => 'latest',
                'region'      => $array['region'],
                'credentials' => [
                    'key'    => $array['app_key'],
                    'secret' => $array['app_secret'],
                ],
            ]);

            $shards = $KinesisClient->listShards([
                /*'ExclusiveStartShardId' => '<string>',
                'MaxResults' => <integer>,
                'NextToken' => '<string>',
                'StreamCreationTimestamp' => <integer || string || DateTime>,*/
                'StreamName' => $streamname,
            ]);

            foreach($shards['Shards'] as $shard){
                $stream_monitor_list[$streamname]['shards'][$shard['ShardId']] = $shard; 
                
                $currentIterator = $KinesisClient->getShardIterator([
                    'ShardId' => $shard['ShardId'], // REQUIRED
                    'ShardIteratorType' => 'LATEST', // REQUIRED
                    //'StartingSequenceNumber' => '<string>',
                    'StreamName' => $streamname, // REQUIRED
                    //'Timestamp' => <integer || string || DateTime>,
                ]);

                $stream_monitor_list[$streamname]['shards'][$shard['ShardId']]['ShardIterator'] = $currentIterator['ShardIterator']; 
            }
            //$stream_monitor_list[$streamname]['shards'] = $shards['Shards'];
        }

        print_r($stream_monitor_list);


        //print_r($streamsharditerators);
        print "Starting Steam Monitoring for the following Streams: ".PHP_EOL;
        foreach($stream_monitor_list as $streamname => $stream){
            $shardcount = count($stream['shards']); 
            print "$streamname with $shardcount shards".PHP_EOL; 
        }

        while(true){
            foreach($stream_monitor_list as $streamname => $stream){
                //print $streamname.PHP_EOL;
                //print_r($shards);
                //print "Thise were the shards".PHP_EOL;
                foreach($stream['shards'] as $shardid => $shard){
                    if(!isset($shard['ShardIterator'])){
                        continue;
                    }

                    $iterator = $shard['ShardIterator']; 



                    $KinesisClient = new KinesisClient([
                        'version'     => 'latest',
                        'region'      => $stream['region'],
                        'credentials' => [
                            'key'    => $stream['app_key'],
                            'secret' => $stream['app_secret'],
                        ],
                    ]);
    

                    try{
                        $reply = $KinesisClient->getRecords([
                            'Limit' => 1000,
                            'ShardIterator' => $iterator, // REQUIRED
                            ]);
                    }catch(KinesisException $e){
                        print_r($shard);

                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        echo "Cannot get shard interator. Getting Latest iterator for $streamname: $shardid.".PHP_EOL;
                        $currentIterator = $KinesisClient->getShardIterator([
                            'ShardId' => $shardid, // REQUIRED
                            'ShardIteratorType' => 'LATEST', // REQUIRED
                            //'StartingSequenceNumber' => '<string>',
                            'StreamName' => $streamname, // REQUIRED
                            //'Timestamp' => <integer || string || DateTime>,
                        ]);

                        //print_r($currentIterator); 

                        $stream_monitor_list[$streamname]['shards'][$shard['ShardId']]['ShardIterator'] = $currentIterator['ShardIterator']; 

                        //echo 'Caught exception: ',  $e->getMessage(), "\n";
                        //echo 'Cannot get shard interator. Moving on.'.PHP_EOL;

                        try{
                            $reply = $KinesisClient->getRecords([
                                'Limit' => 1000,
                                'ShardIterator' => $currentIterator['ShardIterator'], // REQUIRED
                                ]);
                            
                            print_r($reply);
                        }catch(KinesisException $e){
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                            print "Kinesis blowing up... Shard may be closed.... Moving on...".PHP_EOL;
                            continue;
                        }

                    }

                    //print_r($reply);
                    
                    $records = $reply['Records']; 
                    $nextiterator = $reply['NextShardIterator'];

                    
                    if(!$nextiterator){
                        print "No next iterator found... Shard may be closed.... $nextiterator".PHP_EOL;

                        continue;
                    }

                    //$stream_monitor_list[$streamname][$shardid]['ShardIterator'] = $nextiterator;
                    $stream_monitor_list[$streamname]['shards'][$shard['ShardId']]['ShardIterator'] = $nextiterator; 

                    //print_r($records);
                    
                    if(!empty($records)){
                        foreach($records as $record){
                            //print "Printing Record from Stream $streamname".PHP_EOL;
                            //print_r($record);

                            // Agent Events Filters. 
                            $json = $record['Data']; 
                            $array = json_decode($json); 
                            //print_r($array); 

                            if(isset($array->CurrentAgentSnapshot)){
                                // If this is an agent event... parse it out and present usefull data. 
                                $timestamp = $array->EventTimestamp; 
                                $eventtype = $array->EventType; 

                                if($eventtype != "STATE_CHANGE" &&  $eventtype != "LOGIN"){
                                    //continue;
                                }

                                $username = $array->CurrentAgentSnapshot->Configuration->Username; 
                                
                                $time = $array->CurrentAgentSnapshot->AgentStatus->StartTimestamp;
                                $status = $array->CurrentAgentSnapshot->AgentStatus->Name;

                                $newstatus = null; 
                                if(!empty($array->CurrentAgentSnapshot->Contacts)){
                                    $status = $array->CurrentAgentSnapshot->Contacts; 
                                    foreach($status as $stat){
                                        if($stat->Channel == "VOICE"){
                                            $newstatus = $stat->State; 
                                        }
                                    }
                                }
                                
                                if($newstatus){
                                    $status = $newstatus; 
                                }

                                $status = strtoupper($status); 

                                $contacts = $array->CurrentAgentSnapshot->Contacts;

                                $instance_arn = $array->InstanceARN; 

                                $instance_array = explode("/", $instance_arn); 

                                //print_r($instance_array[1]); 

                                //die(); 

                                $instance_id = $instance_array[1];

                                if(isset($array->PreviousAgentSnapshot)){
                                    $oldtime = $array->PreviousAgentSnapshot->AgentStatus->StartTimestamp;
                                    $oldstatus = $array->PreviousAgentSnapshot->AgentStatus->Name;
                                }else{
                                    $oldtime = null;
                                    $oldstatus = null;
                                }

                                //print_r($array); 

                                $short_iterator = substr($iterator, -10); 
                                
                                $now = Carbon::now(); 
                                $runtime = $now->diffInSeconds($scriptstart); 
                                
                                print "$runtime | $shardid | $short_iterator | $timestamp | $eventtype | $username | $oldtime | $oldstatus -> $status | $time".PHP_EOL; 

                                
                                 
                                //$json = json_encode($array); 


                                $agent = Agent::updateOrCreate(
                                                // Search
                                                [   'arn' => $array->AgentARN, 
                                                    'instance_id' => $instance_id
                                                ],
                                                // Update or Create merged with Search
                                                [   'username' => $username, 
                                                    'status' => $status,
                                                    'json' => $json
                                                ]
                                ); 

                                //die();

                                //print_r($contacts); 
                            }else{
                                print_r($array);
                            }
                            
                        }
                    }else{
                        //print "found no records".PHP_EOL;
                    }
                    
                }
            }

            //print "No new records found... Please Wait...".PHP_EOL;

            $now = Carbon::now(); 
            $runtime = $now->diffInSeconds($scriptstart); 


            // Logout Agents that haven't been updated in 15 mins. Heartbeats should update every 2 minutes. 
            $agents = Agent::all(); 

            foreach($agents as $agent){
                $updated_at = $agent['updated_at'].PHP_EOL; 

                if($now->diffInSeconds($updated_at) > 900){
                    //print "Agent {$agent['username']} must be logged out... Changing Status".PHP_EOL;
                    $agent->status = "OFFLINE";
                    $agent->save(); 
                    //print_r($agent);

                }
            }

            sleep(2);
        }


        
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


}
