<?php

namespace App\Console\Commands\AWS;

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
        $states = [ 'AGENTS_ONLINE',
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

                    foreach($states as $state){
                        try{
                            $result = $client->getCurrentMetricData([
                                'CurrentMetrics' => [ // REQUIRED
                                    [
                                        'Name' => $state,
                                        'Unit' => 'COUNT',
                                    ],
                                    // ...
                                ],
                                
                                'Filters' => [ // REQUIRED
                                    'Channels' => ['VOICE'],
                                    'Queues' => ['BasicQueue'],
                                ],
                                //'Groupings' => ['<string>', ...],
                                'InstanceId' => $instance['Id'], // REQUIRED
                                //'MaxResults' => <integer>,
                                //'NextToken' => '<string>',
                            ]);
                            print_r($result);
                        }catch(AwsException $e){
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                            continue;
                        }

                        $summary[$state] = $result; 
                    }
                    
                    print_r($summary);
    }
}
