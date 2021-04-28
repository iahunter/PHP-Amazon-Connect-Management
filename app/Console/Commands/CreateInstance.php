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
    protected $signature = 'awsconnect:create-new-instance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a New Standard Connect Instance';

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
        $a = Instance::create(['account' => $this->account, 'name' => $this->instance_name, 'instance_id' => $this->instance_name]);
    }
	
	public function prompt()
	{
		if (empty($this->instance_name)) {
            $this->instance_name = $this->ask('What is the Instance Name?', 'test');
        }
		if (empty($this->account)) {
			$companies = Company::all()-get(); 
			print($companies); 
			
            $this->account = $this->choice(
										'What is the Company Name?',
										['Taylor', 'Dayle'],
									);
        }
		if (empty($this->instance_name)) {
            $this->instance_name = $this->ask('What is the Instance Name?', 'test');
        }
	}
}
