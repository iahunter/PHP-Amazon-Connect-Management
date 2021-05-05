<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use Aws\Connect\ConnectClient;
use Aws\Kinesis\KinesisClient;
use Aws\S3\S3Client;

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

class DeployInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:deploy-new-instance {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy New Connect Instance to AWS';

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
        echo "###################################################".PHP_EOL;
        echo "                Deploy Amazon Connect              ".PHP_EOL;
        echo "###################################################".PHP_EOL;

        $this->starttime = Carbon::now();
        echo $this->starttime.PHP_EOL;

        $instance = $this->get_instance_info();

        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        $this->S3Client = new S3Client([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        $this->KinesisClient = new KinesisClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

        //print_r($this->S3Client->listBuckets());

        print_r($this->KinesisClient->listStreams());
        
        //$this->create_instance($instance); 



        $time = Carbon::now();
        echo "Start Time: ". $this->starttime.PHP_EOL; 
        echo "End Time: ". $time.PHP_EOL;
    }


    public function construct_Connect_Storage($instance){
        
    }

    public function create_instance($instance)
    {

        //print_r($instance);
        try{
            $result = $this->ConnectClient->createInstance($instance);
        } catch (\Exception $e) {
            echo $e->getMessage().PHP_EOL;
            return;
        }
        

        //print_r($result);

        $instance_id = $result['Id'];

        sleep(5);

        $creating = true;

        $time = Carbon::now();
        $timeout = Carbon::now()->addMinutes(20);

        echo $time . " Creating Instance ID: {$instance_id} with name: {$this->instance_name}".PHP_EOL;
        
        while($creating == true && $time <= $timeout){
            $result = $this->ConnectClient->listInstances();
            $instances = $result['InstanceSummaryList'];

            //print_r($instances);
            $time = Carbon::now();
            foreach($instances as $instance){
                if($instance['Id'] == $instance_id){
                    if($instance['InstanceStatus'] == "ACTIVE"){
                        print "Instance created successfully...".PHP_EOL;
                        print_r($instance); 
                        $creating = false;
                    }else{
                        echo $time." Status: " . $instance['InstanceStatus'] ." : Please Wait...".PHP_EOL;
                        sleep(30);
                    }
                }
            }
        }
    }

    public function get_instance_info()
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
        
        $this->instance_name = $this->ask('What is the name of the instance?');

        $instance_array = [
            "IdentityManagementType" => "SAML",
            "InboundCallsEnabled" => true,
            "InstanceAlias" => $this->instance_name,
            "OutboundCallsEnabled" => true
        ];
        
        //print_r($instance_array);

        return $instance_array;
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
