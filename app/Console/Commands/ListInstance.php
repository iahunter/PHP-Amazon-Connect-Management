<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

use Aws\Connect\ConnectClient;

use App\Models\Company;
use App\Models\Account;
use App\Models\AmazonConnect\Instance;

class ListInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'awsconnect:list-instances {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List Amazon Connect Instances for Account';

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
                //print_r($result);
            }catch(Exception $e){
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return;
            }
            
            
            foreach($result['InstanceSummaryList'] as $instance){
                //print_r($instance);
                $id = $instance['Id']; 
                $arn = $instance['Arn'];
    
                $arn_array = explode(":",$arn); 
    
                //print_r($arn_array);
                    
                if(is_array($arn_array) && !empty($arn_array))
                {
                    //print_r($arn_array); 
    
                    $region = $arn_array[3];
                    $account = $arn_array[4];
                }else{
                    $region = null;
                    $account = null;
                }
    
                $name =  $instance['InstanceAlias'];
                $created = $instance['CreatedTime'];
    
                $types = [  
                            'CALL_RECORDINGS', 
                            'CONTACT_TRACE_RECORDS',
                            'AGENT_EVENTS',
                ];
                
                $storage = [];
                foreach($types as $type){
                    $getresult = $client->listInstanceStorageConfigs([
                        'InstanceId' => $instance['Id'],
                        'ResourceType' => $type,
                    ]);
                    $storage[$type] = $getresult['StorageConfigs']; 
                    //print_r($getresult);
                }

                $storage_json = json_encode($storage); 

                // Get Custom Flows and Store in the Database
                $flows = $client->listContactFlows([
                    'InstanceId' => $instance['Id'],
                ]);

                //print_r($flows);

                $discard_regex = [
                    "/^Default/",
                    "/^Sample/",
                ];

                $custom_flows = [];

                foreach($flows['ContactFlowSummaryList'] as $flow){
                    //print_r($flow['Name']);
                    $found = false;
                    // Discard Default and Sample Flows
                    foreach($discard_regex as $regex){
                        if(preg_match($regex,$flow['Name'])){
                            $found = true;
                        }
                    }

                    if($found == true){
                        continue;
                    }else{
                        print_r($flow);
                    }

                    // Need to possibly queue these jobs in case they error out. 
                    try{
                        $getresult = $client->describeContactFlow([
                            'ContactFlowId' => $flow['Id'],
                            'InstanceId' => $instance['Id'],
                        ]);
                    }catch(Exception $e){
                        //echo 'Caught exception: ',  $e->getMessage(), "\n";
                        echo 'Cannot get contact flow. Moving on.'.PHP_EOL;
                        continue;
                    }
                    
                    
                    print_r($getresult);
                    $custom_flows[] = $getresult['ContactFlow'];
                }
                
                $custom_flows_json = json_encode($custom_flows);


    
                $json = json_encode($instance, true); 
    
                print_r($json); 
    
                $exists = Instance::where('instance_id',$id)->count();
                print "Found Instance with Name: {$name}".PHP_EOL;
                if(!$exists){
                    print "Creating New Connect Instance!".PHP_EOL;
                    $data = Instance::create(['name' => $name, 'instance_id' => $id, 'account_id' => $account, 'region' => $region, 'flows' => $custom_flows_json, 'storage' => $storage_json, 'json' => $json]);
                    //print_r($data);
    
                    //print_r($instance); 
                }elseif(Instance::where('json', $json)->count()){
                    print "Needs updated json!";

                }else{
                    print "Nothing Changed... Moving on.".PHP_EOL; 
                }
    
                $exists = Instance::where('instance_id',$id)->first();
                //print_r($exists); 
            }
        }
        
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
