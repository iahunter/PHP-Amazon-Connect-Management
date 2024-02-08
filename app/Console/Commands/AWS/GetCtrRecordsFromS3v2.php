<?php

namespace App\Console\Commands\AWS;

use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Log;
use Aws\Connect\ConnectClient;
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

use Illuminate\Database\QueryException; 


use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\Models\AmazonConnect\Ctr;
use Illuminate\Support\Facades\DB;

use App\Models\Company;
use App\Models\Account;
use App\Models\CtrBuckets;

use Illuminate\Console\Command;

class GetCtrRecordsFromS3v2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:get-ctr-records-from-s3-v2';

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

		$buckets = CtrBuckets::all();

        //$buckets = CtrBuckets::where('id', 6)->get();

        print_r($buckets); 

		if(!$buckets){
			return; 
		}

		foreach($buckets as $bucketobject){


			print_r($bucketobject); 

			$account = Account::where('account_number', $bucketobject->account_id)->first(); 

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

			//print $this->app_key.PHP_EOL; 
			//print $this->app_secret.PHP_EOL; 

			$bucket = $bucketobject->name; 
			

			$s3Client = new S3Client([
				'version'     => 'latest',
				'region'      => $bucketobject->region,
				'credentials' => [
					'key'    => $this->app_key,
					'secret' => $this->app_secret,
				],
			]);

			$lastest = Ctr::get_last_record_from_bucket($bucket);
            print_r($lastest);
            //die(); 
			if($lastest){
				// Trying to speed this up by getting records only after the last record in our DB. 
				$objects = $s3Client->listObjectsV2([
					'Bucket' => $bucket,
					'Prefix' => 'ctr-', //must have the trailing forward slash "/"
					'StartAfter' => $lastest,
				]);
			}else{
				$objects = $s3Client->listObjects([
					"Bucket" => $bucket,
					"Prefix" => 'ctr-' //must have the trailing forward slash "/"
				]);
			}

			//print_r($objects); 

			if(!(array)$objects){
				print_r($objects);
				print "No Objects... Skipping Bucket: $bucket".PHP_EOL; 
				continue;  
			}
			

			$now = Carbon::now();
			$cutoff = Carbon::now()->subDays(150);

			//print_r($objects);

			$key_array = []; 
			$inserts = []; 
			if(empty($objects['Contents'])){
				continue; 
			}

            /*
            *****************************************************
            Need to get all the objects if the next token is set.
            *****************************************************
            */

            $ctr_array = [];

            $next = 1;
            while($next){
                $next = 0; 
                foreach($objects['Contents'] as $object){
                    $ctr_array[] = $object; 
                }
                if($objects['NextContinuationToken']){
                    $nexttoken = $objects['NextContinuationToken'];
                    print "Found next token... Getting addtional Objects".PHP_EOL; 
                    $objects = $s3Client->listObjectsV2([
					    'ContinuationToken' => $nexttoken,
                        'Bucket' => $bucket,
					    //'Prefix' => 'ctr-', //must have the trailing forward slash "/"
				    ]);
                    $next = 1; 
                }
            }

            print_r($ctr_array);


            /*
            *****************************************************
            Need to get all the objects if the next token is set.
            *****************************************************
            */
			
			//foreach ($objects['Contents'] as $object) {
            foreach ($ctr_array as $object) {
				//print_r($object); 

				//die(); 
				$file_date = $object['LastModified']; 
				$date = Carbon::parse($file_date); 
				$date = $date->toDateTimeString(); 

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
				continue;
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

						//print_r($array); 
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
}
