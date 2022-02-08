<?php

namespace App\Console\Commands\AWS;

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use Aws\Connect\ConnectClient;

use App\Models\Company;
use App\Models\Account;

use Illuminate\Console\Command;

class GetCurrentMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:get-metric-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache Instance Queue Realtime Metrics';

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
        print "Starting... ".PHP_EOL; 
        while(true){
            $scriptstart = Carbon::now(); 

            $stream_monitor_list = []; 

            $accountnumbers = [];

            $accounts = Account::all(); 

            foreach($accounts as $account){
                $accountnumbers[] = $account->account_number;
            }

            $instance_data = []; 

            $instancequeuestats = []; 

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
                        $instancelist = $client->listInstances();
                        print_r($instancelist);
                    }catch(AwsException $e){
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                        echo "Exception found trying to listInstances... Killing service...".PHP_EOL; 
                        Log::info('Showing the user profile for user: '.$id);
                        sleep(5); 
                        die();;
                    }
            
                    $instances = []; 
                    foreach($instancelist['InstanceSummaryList'] as $instance){
                        //print $instance['Id'].PHP_EOL;

                        // Get Custom Flows and Store in the Database
                        try{
                            $queueslist = $client->listQueues(['InstanceId' => $instance['Id']]);
                            print_r($queueslist);
                        }catch(AwsException $e){
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                            echo "Exception found trying to listQueues... Killing service...".PHP_EOL; 
                            sleep(5); 
                            die();
                        }

                        $states = [ 
                            'AGENTS_ONLINE',
                            'AGENTS_AVAILABLE',
                            'AGENTS_ON_CALL',
                            'AGENTS_NON_PRODUCTIVE',
                            'AGENTS_AFTER_CONTACT_WORK',
                            'AGENTS_ERROR',
                            'AGENTS_STAFFED',
                            'CONTACTS_IN_QUEUE',
                            'OLDEST_CONTACT_AGE',
                            'CONTACTS_SCHEDULED',
                            'AGENTS_ON_CONTACT',
                            'SLOTS_ACTIVE',
                            'SLOTS_AVAILABLE',
                        ];

                        $summary = []; 
                        $metrics = [];
                        foreach($states as $state){

                            if($state == "OLDEST_CONTACT_AGE"){
                                $unit = "SECONDS"; 
                            }
                            else{
                                $unit = "COUNT"; 
                            }

                            // Build Array of states. 
                            $metrics[] = [
                                        'Name' => $state,
                                        'Unit' => $unit,
                            ]; 
                        }



                        //print_r($queueslist); 
                        $queues = []; 
                        foreach($queueslist['QueueSummaryList'] as $queue){
                            if($queue['QueueType'] != "STANDARD"){
                                continue;
                            }
                            //$queues[] = $queue['Name']; 
                        
                            //print_r($metrics); 

                            //print $queue['Name'].PHP_EOL;
                            try{
                                $result = $client->getCurrentMetricData([
                                    'CurrentMetrics' => $metrics, // REQUIRED
                                    
                                    'Filters' => [ // REQUIRED
                                        'Channels' => ['VOICE'],
                                        'Queues' => [$queue['Arn']],
                                    ],
                                    //'Groupings' => ['<string>', ...],
                                    'InstanceId' => $instance['Id'], // REQUIRED
                                    //'MaxResults' => <integer>,
                                    //'NextToken' => '<string>',
                                ]);
                                print_r($result);
                                //die();
                            }catch(AwsException $e){
                                echo 'Caught exception: ',  $e->getMessage(), "\n";
                                echo "Exception found trying to getCurrentMetricData... Killing service...".PHP_EOL; 
                                sleep(5); 
                                die();
                                continue;
                            }
                            $stats = []; 

                            if(empty($result['MetricResults'])){
                                continue;  
                            }

                            foreach($result['MetricResults'] as $metric){
                                //print_r($metric['Collections']);
                                foreach($metric['Collections'] as $collection) {
                                    $name = $collection['Metric']['Name']; 

                                    $stat['Name'] = $name; 
                                    //$stat['Unit'] = $collection['Metric']['Unit']; 
                                    $stat['Value'] = $collection['Value']; 

                                    //$stats[$name] = $stat; 
                                    $stats[] = $stat; 
                                }
                                //die();
                            }

                            $now = Carbon::now(); 
                            
                            $instances[$queue['Name']]['metrics'] = $stats;
                            $instances[$queue['Name']]['timestamp'] = $now; 
                            $instances[$queue['Name']]['queue'] = $queue['Name'];
                            
                            sleep(1);

                        }

                        Cache::put($instance['Id'], $instances, 300);
                        $instances = []; 

                    }

                }

            }
            
            print "End of Iteration... Waiting 5 seconds to start again...".PHP_EOL; 
            sleep(5); 
        }
    }   
}

