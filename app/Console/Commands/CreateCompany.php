<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Company;

class CreateCompany extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acm:create-company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Company';

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
        $a = Company::create(['name' => $this->company_name, 'description' => $this->company_description]);
    }
	
	public function prompt()
	{
		if (empty($this->company_name)) {
            $this->company_name = $this->ask('What is the Company Name?', 'test');
        }
		if (empty($this->company_description)) {
            $this->company_description = $this->ask('What is the Description', 'test');
        }
	}
}
