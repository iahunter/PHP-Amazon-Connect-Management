<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Company;
use App\Models\Account;

use Illuminate\Support\Facades\Crypt;

class CreateAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acm:create-account';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new account under an existing company';

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
		$this->prompt(); 
        $a = Account::create([  'company_id' => $this->company_id, 
                                'account_number' => $this->account_number, 
                                'account_description' => $this->account_description,
                                'account_app_key' => $this->account_app_key,
                                'account_app_secret' => $this->account_app_secret,
                            ]);

        print_r($a);
    }
	
	public function prompt()
	{
        $names = Company::names();

        print_r($names);

        //$companies = Company::where('name' = )
        if (empty($this->company_name)) {
            $this->company_name = $this->choice('What is the Company Name?', $names,);
            $company = Company::where('name', $this->company_name)->first();
            print_r($company);
            $this->company_id = $company->id;
        }
		if (empty($this->account_number)) {
            $this->account_number = $this->ask('What is the AWS Account Number', '123456789012');
        }
        if (empty($this->account_description)) {
            $this->account_description = $this->ask('What is the Account Description', 'Lab');
        }

        if (empty($this->account_app_key)) {
            $this->account_app_key = $this->ask('What is the Account App Key');

            // Encrypt the Key
            $this->account_app_key = Crypt::encryptString($this->account_app_key); 
        }

        if (empty($this->account_app_secret)) {
            $this->account_app_secret = $this->ask('What is the Account App Secret');
            
            // Encrypt the Secret 
            $this->account_app_secret = Crypt::encryptString($this->account_app_secret); 

        }
	}
}
