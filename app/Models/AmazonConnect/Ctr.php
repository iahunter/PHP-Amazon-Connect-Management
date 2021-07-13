<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Ctr extends Model
{
    use HasFactory;
    
    protected $table = 'connect_ctrs';
    
    protected $primaryKey = 'contact_id';

    public $timestamps = false;

    public $incrementing = false;
	
	protected $fillable = [
        'contact_id',
        's3_key',
        'account',
        'instance_id',
        'channel',
        'initiation_method',
        'queue',
        'queue_duration',			
        'customer_endpoint', 
        'system_endpoint', 
        'agent', 
        'connect_to_agent_time',
        'connect_to_agent_duration',
        'start_time', 
        'disconnect_time', 
        'contact_duration', 
        'disconnect_reason',
        'initial_contact_id',
        'previous_contact_id',
        'next_contact_id',
        'cdr_json',
    ];

    // TODO Mysql querie for API... 
    // select initiation_method, contact_id, initial_contact_id, previous_contact_id, next_contact_id, customer_endpoint, system_endpoint from connect_ctrs;

    public static function check_db_for_record($key)
    {
        $check = self::where('s3_key', $key)->count();
        //print $check.PHP_EOL;
        return $check;
    }

    public static function divide_and_conquer($records, $min, $max)
    {

        // Cut the results in half and check to see if middle record is found. This uses the array keys to do the division and checks the cdr record with that key to see if it exists or not.
        $half = ceil((($max - $min) / 2) + $min);

        //print "Found Half of {$max} and {$min} is {$half}.".PHP_EOL;

        // Check if the record with the key value of $half exists in the DB.
        if (self::check_db_for_record($records[$half])) {
            //print "Found Record {$half} in the DB... Setting as the minimum record.";
            // If found set that key to the minimum array key record.
            $min = $half;
        } else {
            // If not found then set that as the max array key record.
            $max = $half;
        }

        // Return the min and max keys in the array for the search.
        return ['min' => $min, 'max' => $max];
    }

    public static function check_db_for_records_return_records_to_insert(array $records)
    {
        //echo 'Checking '.count($records).' records in the cdr array to find last record in the DB...'.PHP_EOL;

        $min = 0;
        $max = count($records) - 1;
        $last = count($records);

        if ($max <= 0) {
            return;
        }

        //

        /* Debugging
        print "Min key: ".$min.PHP_EOL;
        print "Max key: ".$max.PHP_EOL;
        print "Last: ".$last.PHP_EOL;
        */

        // Check if last record exists in Database.
        if (self::check_db_for_record($records[$max])) {
            //echo "Found last record in the DB: {$records[$max]}".PHP_EOL;

            return; 															// return nothing because we assume all records are now in the DB.
        }
        // Check if first record exists.
        if (! self::check_db_for_record($records[$min])) {
            //print $records[$min].PHP_EOL; 
            //echo 'Did not find record in the DB. Sending all records back for insertion... '.PHP_EOL;

            return $records;
        }
        // If first record exits, it tries to find out where the script left off last time or if new lines have been added after it ran last.
        elseif (self::check_db_for_record($records[$min])) {
            //echo "Found {$records[$min]} record in the DB. Trying to find last record entered... ".PHP_EOL;

            // Find last record inserted into the DB.
            while (($max - $min) > 1) {

                // Use divide and conquer to find last record
                $result = self::divide_and_conquer($records, $min, $max);

                $min = $result['min']; 				// Update the minimum record array key found.
                $max = $result['max']; 				// Update the maximum record array key found.

                /* Debugging
                print "Min = {$min}".PHP_EOL;
                print "Max = {$max}".PHP_EOL;
                print "Continue Searching for the last record inserted... ".PHP_EOL;
                */
            }

            $output = array_slice($records, $min, $last, true); 			// Get the minimum starting record and trim everyting before it off the array.

            return $output;
        }

        //echo 'Found nothing... Stopping... '.PHP_EOL;
    }

    public static function format_record($array){

                $insert['contact_id'] = $array['ContactId'];

                $insert['instance_id'] = $array['InstanceARN']; 

                $insert['s3_key'] = $array['S3_Key']; 

				//print $insert['s3_key'].PHP_EOL;

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
					//$insert['connect_to_agent_time'] = $array['Agent']['ConnectedToAgentTimestamp'];
                    $insert['connect_to_agent_time'] = Carbon::parse($array['Agent']['ConnectedToAgentTimestamp']);
                }else{
					$insert['connect_to_agent_time'] = null;
				}

				if(isset($array['Agent']['AgentInteractionDuration'])){
					$insert['connect_to_agent_duration'] = $array['Agent']['AgentInteractionDuration'];
				}else{
					$insert['connect_to_agent_duration'] = null;
				}
				
				if(isset($array['InitiationTimestamp'])){
					//$insert['start_time'] = $array['InitiationTimestamp'];
                    $insert['start_time'] = Carbon::parse($array['InitiationTimestamp']);
				}else{
					$insert['start_time'] = null;
				}
				if(isset($array['DisconnectTimestamp'])){
					//$insert['disconnect_time'] = $array['DisconnectTimestamp'];
                    $insert['disconnect_time'] = Carbon::parse($array['DisconnectTimestamp']);
				}else{
					$insert['disconnect_time'] = null;
				}

				if(isset($array['DisconnectReason'])){
					$insert['disconnect_reason'] = $array['DisconnectReason'];
				}else{
					$insert['disconnect_reason'] = null;
				}

                if(isset($array['InitialContactId'])){
					$insert['initial_contact_id'] = $array['InitialContactId'];
				}else{
					$insert['initial_contact_id'] = null;
				}

                if(isset($array['PreviousContactId'])){
					$insert['previous_contact_id'] = $array['PreviousContactId'];
				}else{
					$insert['previous_contact_id'] = null;
				}

                if(isset($array['NextContactId'])){
					$insert['next_contact_id'] = $array['NextContactId'];
				}else{
					$insert['next_contact_id'] = null;
				}

				$insert['cdr_json'] = json_encode($array); 

                return $insert;
    }


}
