<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\AmazonConnect\Instance;
use App\Models\Company;
use App\Models\Account;

class CreateInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acm:create-new-instance {company?} {account_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a New Standard Connect Instance for Staging';

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

        $builddoc = [
            'storage' => [
                'kinesis' => $this->kinesis,
                's3' => $this->s3
            ],
            'region' => $this->region,
            'saml'  => $this->saml
        ];

        $builddoc = json_encode($builddoc);

        $name = Instance::where('name', $this->instance_name)
                        ->where('account_id', $this->account_number)
                        ->count();

        if($name){
            $instance = Instance::where('name', $this->instance_name)
                            ->where('account_id', $this->account_number)
                            ->first();

            $instance->build_data = $builddoc;
            $instance->save();
        }else{
            $instance = Instance::create(['name' => $this->instance_name, 'account_id' => $this->account_number, 'build_data' => $builddoc]);
        }

        print_r($instance);

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

        $this->instance_name = $this->ask('What is the Name of the new Instance?');

        $regions = [
            "us-east-1",
            "us-west-2"
        ];
        $this->region = $this->choice('What region would you like to use?', $regions);

        $bolean = [
            "no",
            "yes",
        ];

        $kinesis = $this->choice('Would you like to setup a Kinesis Stream for CTR and Agent Events?', $bolean);

        if($kinesis == "no"){
            $this->kinesis = false;
        }else{
            $this->kinesis = true;
        }

        $s3 = $this->choice('Would you like to send the CTR and Agent Events to the S3 Bucket?', $bolean);

        if($s3 == "no"){
            $this->s3 = false;
        }else{
            $this->s3 = true;
        }

        $saml = $this->choice('Would you like to setup SAML Authentication?', $bolean);

        if($s3 == "no"){
            $this->saml = false;
        }else{
            $this->saml = true;
        }
    }
}
