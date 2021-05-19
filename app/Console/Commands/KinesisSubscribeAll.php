<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Aws\Kinesis\KinesisClient;

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\Models\Company;
use App\Models\Account;

class KinesisSubscribeAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:kinesis-subscribe-streams {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribes to all Streams and Prints to the Console';

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

        $streamarray = [];

        foreach($streams as $streamname){
            $shards = $KinesisClient->listShards([
                /*'ExclusiveStartShardId' => '<string>',
                'MaxResults' => <integer>,
                'NextToken' => '<string>',
                'StreamCreationTimestamp' => <integer || string || DateTime>,*/
                'StreamName' => $streamname,
            ]);
            $streamarray[$streamname] = $shards;
        }

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

                //print_r($currentIterator['ShardIterator']);

                $streamsharditerators[$streamname][$shardid] = $currentIterator['ShardIterator'];
            }
        }

        //print_r($streamsharditerators);

        while(true){
            foreach($streamsharditerators as $streamname => $shards){
                print $streamname.PHP_EOL;
                foreach($shards as $shardid => $iterator){
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
                    }
                    
                    if(!empty($records)){
                        foreach($records as $record){
                            //print "Printing Record from Stream $streamname".PHP_EOL;
                            print_r($record);
                        }
                    }else{
                        //print "found no records".PHP_EOL;
                    }
                    
                }
            }

            print "No new records found... Please Wait...".PHP_EOL;

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
