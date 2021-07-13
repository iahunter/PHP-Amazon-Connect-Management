<?php

namespace App\Console\Commands\AWS;

use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Log;
use Aws\Connect\ConnectClient;
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

use Illuminate\Database\QueryException; 

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
			$cutoff = Carbon::now()->subDays(60);

			

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
					//print "File is outside time window..".PHP_EOL;
					continue;
				}
			}

			print_r($key_array);

			

			$key_array = Ctr::check_db_for_records_return_records_to_insert($key_array); 

			if(empty($key_array)){
				print "Nothing to insert after $cutoff. Ending Script...".PHP_EOL;
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
				
				print $key.PHP_EOL;
				
				if(json_decode($data, true)){
					$array = json_decode($data, true);
					$array['S3_Key'] = $key;
					//print_r($array);
					$insert = Ctr::format_record($array);
					//print_r($insert);
					//continue;
					try{
						$result = Ctr::firstOrCreate($insert);
					}catch(QueryException $e){
						
						echo 'Caught exception: ',  $e->getMessage(), "\n";

						$record = Ctr::where("contact_id", $insert['contact_id'])
									->update($insert);

						if(!$record){
							echo "Could not update Record {$insert['contact_id']}".PHP_EOL; 
						}else{
							echo "Updated Record {$insert['contact_id']}".PHP_EOL; 
						}
					}

					//print_r($result);
					//print " ".PHP_EOL;
				}
				else{
					// If files has multiple records we need to parse them because they can't be decoded by json decoder
					$array = [];
					$fixstupidcrap = str_replace("}{", "}&&{", $data); 
					$stupid = explode("&&", $fixstupidcrap);
					//print_r($stupid);

					foreach($stupid as $object){
						$json = json_decode($object, true);
						$json['S3_Key'] = $key;
						//print_r($array);
						$insert = Ctr::format_record($json);
						//print_r($insert);
						
						$array[] = $insert;
						//die();

						print_r($array); 
						//die();
						//$result = Ctr::insert($insert);
						try{
							//$result = DB::table('connect_ctrs')->insert($insert);
							$result = Ctr::firstOrCreate($insert);
						}catch(QueryException $e){
							
							echo 'Caught exception: ',  $e->getMessage(), "\n";

							//die();

							$record = Ctr::where("contact_id", $insert['contact_id'])
										->update($insert);

							//print_r($record);

							if(!$record){
								echo "Could not update Record {$insert['contact_id']}".PHP_EOL; 
							}else{
								echo "Updated Record {$insert['contact_id']}".PHP_EOL; 
							}

							//die();

							continue;
						}
						
						//print_r($result);
					}

					//die();
				}
				
				//die();
				if(!$array){
					print " No contents in {$key}".PHP_EOL;
					print_r($data).PHP_EOL;
					continue; 
				};
			}

	}
}