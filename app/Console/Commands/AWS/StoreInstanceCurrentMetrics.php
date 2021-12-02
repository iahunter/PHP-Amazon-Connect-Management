<?php

namespace App\Console\Commands\AWS;

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use Aws\Connect\ConnectClient;

use App\Models\Company;
use App\Models\Account;

use Illuminate\Console\Command;

class StoreInstanceCurrentMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:store-instance-metric-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store Instance Queue Realtime Metrics';

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
                    //print_r($instancelist);
                }catch(AwsException $e){
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    die();
                }
        
                foreach($instancelist['InstanceSummaryList'] as $instance){
                    $key = $instance['Id']; 

                    if(Cache::has($key)){
                        $stats = Cache::get($key);
                    }

                    
                    print_r($stats); 
                }
            }
        }
    }   
}

