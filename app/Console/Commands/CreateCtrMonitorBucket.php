<?php

namespace App\Console\Commands;

use Aws\S3\S3Client; 
use Aws\Connect\ConnectClient;
use Aws\Exception\AwsException; 

use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\AWS\Connect;

use App\Models\Company;
use App\Models\Account;
use App\Models\CtrBuckets;

use Illuminate\Console\Command;

class CreateCtrMonitorBucket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acm:create-ctr-bucket-monitor {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
                print_r($result);
            }catch(Exception $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }
            
            foreach($result['InstanceSummaryList'] as $instance){
                $instance_array[$region][$instance['InstanceAlias']] = $instance;
                $instance_names[] = $instance['InstanceAlias'];
            }
        }

        //print_r($instance_array);

        $this->instance_alias = $this->choice('Instance Name?', $instance_names);

        foreach($instance_array as $region => $instances){

            if(key_exists($this->instance_alias, $instances)){
                $this->region = $region;
                
                $this->instance = $instances[$this->instance_alias];
            }
        }

        $this->shortregion = str_replace("-","",$this->region);

        $this->instance_id = $this->instance['Id']; 
        
        print $this->account_number.PHP_EOL;
        print $this->instance_id.PHP_EOL; 
        print $this->region.PHP_EOL; 

        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);
        
        try{
            $buckets = $s3Client->listBuckets();
            //print_r($buckets);
        }catch(AwsException $e){
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return;
        }
        
        $bucketnames = []; 
        foreach ($buckets['Buckets'] as $bucket) {
            $bucketnames[] = $bucket['Name'];
        }

        if (!empty($bucketnames)) {
            $this->bucket = $this->choice('What is the Bucket Name to Monitor?', $bucketnames,);
        }else{
            print "No buckets found... Ending Script".PHP_EOL; 
            return; 
        }

        print $this->bucket; 

        $insert = [ 'name'          => $this->bucket, 
                    'account_id'       => $this->account_number,
                    'instance_id'   => $this->instance_id, 
                    'region'        => $this->region, 
                    'monitor'       => true,
                ]; 

        $result = CtrBuckets::updateOrCreate($insert);

        print_r($result);
        
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
