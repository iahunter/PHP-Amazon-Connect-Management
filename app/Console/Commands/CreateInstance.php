<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Instance;
use App\Models\Company;

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

 
        $a = Instance::create(['account' => $this->account, 'name' => $this->instance_name]);
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

        $storage = [
            "none",
            "s3",
        ];

        $this->storage_type = $this->choice('Where do you want to store long term CTR and Agent Data?', $storage);


    }
}
