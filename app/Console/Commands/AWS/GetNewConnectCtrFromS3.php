<?php

namespace App\Console\Commands\AWS;

use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Log;
use Aws\Connect\ConnectClient;
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

use Carbon\Carbon;
use App\Models\AmazonConnect\Ctr;
use Illuminate\Support\Facades\DB;

use Illuminate\Console\Command;

class GetNewConnectCtrFromS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:get-ctr-records-from-s3';

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
        $s3Client = new S3Client([
			//'profile' => 'default',
			'region' => env('AMAZON_REGION'),
			'version' => '2006-03-01',
			'credentials' => [
				'key'    => env('AMAZON_KEY'),
				'secret' => env('AMAZON_SECRET'),
			],
		]);
		
		//$buckets = $s3Client->listBuckets();
		
		//print_r($buckets->get('Buckets'));
		
		
		//foreach ($buckets['Buckets'] as $bucket) {
			//echo $bucket['Name'] . "\n";
		
		

			

			$bucket = env('AMAZON_BUCKET');

			$objects = $s3Client->getIterator('ListObjects', array(
				"Bucket" => $bucket,
				"Prefix" => 'ctr-' //must have the trailing forward slash "/"
			));

			$now = Carbon::now();
			$cutoff = Carbon::now()->subDays(30);

			//print_r($objects);
			$key_array = []; 
			$inserts = []; 
			foreach ($objects as $object) {
				$file_date = $object['LastModified']; 
				$date = Carbon::parse($file_date); 
				$date = $date->toDateTimeString(); 

				//print_r($date); 


				if($file_date > $cutoff){
					//print "File is within time window..".PHP_EOL;
					$key_array[] = "s3://{$bucket}/{$object['Key']}";
				}else{
					print "File is outside time window..".PHP_EOL;
					continue;
				}
			}

			print_r($key_array);

			

			$key_array = Ctr::check_db_for_records_return_records_to_insert($key_array); 

			if(empty($key_array)){
				print "Nothing to insert. Ending Script...".PHP_EOL;
				return;
			}

			foreach ($key_array as $key) {
				$insert = []; 

				if(Ctr::where('s3_key', $key)->count()){
					print "Key Found... Skipping Record...".PHP_EOL;
					continue;
				}

				//echo $object['Key'] . "\n";
				
				// Register the stream wrapper from an S3Client object
				$s3Client->registerStreamWrapper();
				
				// Download the body of the "key" object in the "bucket" bucket
				$data = file_get_contents($key);
				
				

				$array = json_decode($data, true);
				
				//print_r($array);
				
				if(!$array){
					print " No contents in {$key}".PHP_EOL;
					print_r($data).PHP_EOL;
					continue; 
				};

				
			
				$insert['contact_id'] = $array['ContactId'];

				$insert['s3_key'] = $key; 

				print $insert['s3_key'].PHP_EOL;

				$insert['account'] = $array['AWSAccountId'];
				
				$insert['channel'] = $array['Channel'];

				$insert['initiation_method'] = $array['InitiationMethod'];
				

				if(isset($array['Queue']['Name'])){
					$insert['queue'] = $array['Queue']['Name'];
				}else{
					$insert['queue'] = null;
				}
				
				if(isset($array['Queue']['Duration'])){
					$insert['queue_duration'] = $array['Queue']['Duration'];
				}else{
					$insert['queue_duration'] = null;
				}

				
				if(isset($array['CustomerEndpoint']['Address'])){
					$insert['customer_endpoint'] = $array['CustomerEndpoint']['Address']; 
				}else{
					$insert['customer_endpoint'] = null;
				}
				if(isset($array['SystemEndpoint']['Address'])){
					$insert['system_endpoint'] = $array['SystemEndpoint']['Address'];
				}else{
					$insert['system_endpoint'] = null;
				}
				
				if(isset($array['Agent']['Username'])){
					$insert['agent'] = $array['Agent']['Username'];
				}else{
					$insert['agent'] = null;
				}

				if(isset($array['Agent']['ConnectedToAgentTimestamp'])){
					$insert['connect_to_agent_time'] = $array['Agent']['ConnectedToAgentTimestamp'];
				}else{
					$insert['connect_to_agent_time'] = null;
				}

				if(isset($array['Agent']['AgentInteractionDuration'])){
					$insert['connect_to_agent_duration'] = $array['Agent']['AgentInteractionDuration'];
				}else{
					$insert['connect_to_agent_duration'] = null;
				}
				
				if(isset($array['InitiationTimestamp'])){
					$insert['start_time'] = $array['InitiationTimestamp'];
				}else{
					$insert['start_time'] = null;
				}
				if(isset($array['DisconnectTimestamp'])){
					$insert['disconnect_time'] = $array['DisconnectTimestamp'];
				}else{
					$insert['disconnect_time'] = null;
				}

				if(isset($array['DisconnectReason'])){
					$insert['disconnect_reason'] = $array['DisconnectReason'];
				}else{
					$insert['disconnect_reason'] = null;
				}

				$insert['cdr_json'] = json_encode($array); 

				
				//print_r($insert); 

				$inserts[] = $insert; 

				DB::table('connect_ctrs')->insertOrIgnore($insert);
				
			}

		//}
	
		
	}
}