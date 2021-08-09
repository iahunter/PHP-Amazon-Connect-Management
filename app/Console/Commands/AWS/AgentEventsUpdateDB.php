<?php

namespace App\Console\Commands\AWS;

use Illuminate\Console\Command;

use Aws\Kinesis\KinesisClient;

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\Models\AmazonConnect\Agent;
use App\Models\Company;
use App\Models\Account;

class AgentEventsUpdateDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:agentevents-stream {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribes to Agent Stream and Updates Database with agent status';

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
        
        $this->get_stream_info();

        $KinesisClient = $this->KinesisClient($this->region);


        $streamlist = $KinesisClient->listStreams();
        //print_r($streamlist);
        $streams = $streamlist['StreamNames'];
        
        $streamname = $this->choice('What Stream would you like to subscribe to?', $streams, $maxAttempts = 3, $allowMultipleSelections = true);

        $streamarray = [];

        $shards = $KinesisClient->listShards([
            /*'ExclusiveStartShardId' => '<string>',
            'MaxResults' => <integer>,
            'NextToken' => '<string>',
            'StreamCreationTimestamp' => <integer || string || DateTime>,*/
            'StreamName' => $streamname,
        ]);
        $streamarray[$streamname] = $shards;

        $streamsharditerators = [];

        foreach($streamarray as $streamname => $stream){
            //print_r($streamname); 
            //print_r($stream);

            foreach($stream['Shards'] as $shard){
                //print_r($shard);
                $shardid = $shard['ShardId'];
                $currentIterator = $KinesisClient->getShardIterator([
                    'ShardId' => $shardid, // REQUIRED
                    'ShardIteratorType' => 'LATEST', // REQUIRED
                    //'StartingSequenceNumber' => '<string>',
                    'StreamName' => $streamname, // REQUIRED
                    //'Timestamp' => <integer || string || DateTime>,
                ]);

                //print $currentIterator['ShardIterator'].PHP_EOL;

                $streamsharditerators[$streamname][$shardid] = $currentIterator['ShardIterator'];
            }
        }

        //print_r($streamsharditerators);
        print "Monitoring Stream $streamname".PHP_EOL;
        while(true){
            foreach($streamsharditerators as $streamname => $shards){
                //print $streamname.PHP_EOL;
                //print_r($shards);
                //print "Thise were the shards".PHP_EOL;
                foreach($shards as $shardid => $iterator){
                    if(!$iterator){
                        continue;
                    }
                    $reply = $KinesisClient->getRecords([
                    'Limit' => 1000,
                    'ShardIterator' => $iterator, // REQUIRED
                    ]);

                    //print_r($reply);
                    
                    $records = $reply['Records']; 
                    $nextiterator = $reply['NextShardIterator'];

                    $streamsharditerators[$streamname][$shardid] = $nextiterator;
                    if(!$nextiterator){
                        print "No next iterator found... Shard may be closed.... $nextiterator".PHP_EOL;

                        continue;
                    }

                    //print_r($records);
                    
                    if(!empty($records)){
                        foreach($records as $record){
                            //print "Printing Record from Stream $streamname".PHP_EOL;
                            //print_r($record);

                            // Agent Events Filters. 
                            $json = $record['Data']; 
                            $array = json_decode($json); 
                            print_r($array); 

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

                                print_r($array); 
                                
                                
                                print "$timestamp | $eventtype | $username | $oldtime | $oldstatus -> $status | $time"; 


                                $json = json_encode($array); 


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


    public function get_stream_info()
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
