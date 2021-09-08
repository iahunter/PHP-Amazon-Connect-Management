<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

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

                //print_r($array); 
                $insert['contact_id'] = $array['ContactId'];

                $id = explode("/", $array['InstanceARN']);

                $insert['instance_id'] = $id[1]; 

                if(isset($array['S3_Key'])){
                    $insert['s3_key'] = $array['S3_Key']; 
                }
                

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
                    $starttime = Carbon::parse($array['InitiationTimestamp']);
                    $insert['start_time'] = $starttime; 
				}else{
					$insert['start_time'] = null;
				}
				if(isset($array['DisconnectTimestamp'])){
					//$insert['disconnect_time'] = $array['DisconnectTimestamp'];
                    $endtime = Carbon::parse($array['DisconnectTimestamp']);
                    $insert['disconnect_time'] = $endtime;

                    $insert['contact_duration'] = $endtime->diffInSeconds($starttime); 
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

    public static function get_last_record_from_bucket($bucket_name){
        // Used to get a starting S3 key to start from when fetching new records from S3. 
        $regex = '^s3:\/\/'.$bucket_name.'.*'; 

        $calls = self::where('s3_key', 'regexp', $regex)->latest('start_time')->first();

        $recordkey = $calls['s3_key']; 

        $array = explode("s3://{$bucket_name}/", $recordkey); 

        return $array[1];

    }

    public static function years_call_summary($instance_id){

        $start = Carbon::now()->startOfYear()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 

        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($todays_calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }

    public static function months_call_summary($instance_id){

        $start = Carbon::now()->startOfMonth()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 


        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($todays_calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }

    public static function months_daily_call_summary($instance_id){


        $start = Carbon::now()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 


        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }

    public static function weeks_call_summary($instance_id){


        $start = Carbon::now()->startOfWeek()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 

        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }

    public static function yesterday_call_summary($instance_id){


        $start = Carbon::yesterday()->startOfDay()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->startOfDay()->shiftTimezone(env("TIMEZONE"));

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 


        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }


    public static function todays_call_summary($instance_id){

        $start = Carbon::now()->startOfDay()->shiftTimezone(env("TIMEZONE"));
        //print $start.PHP_EOL;
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));
        //print $now.PHP_EOL;

        
        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 

        /*
        $start = Carbon::yesterday()->startOfDay();
        //print $start.PHP_EOL;
        $now = Carbon::now()->startOfDay();
        //print $now.PHP_EOL;
        */


        //print_r(self::whereBetween('start_time', [$start, $now])->get()); 

        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->count())
        {
            //print "Get calls...".PHP_EOL;
            $calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->get(); 
            //print_r($todays_calls); 
        }else{
            $calls = []; 
        }

        if(empty($calls)){
            return []; 
        }
        
        return self::call_summary_report($calls); 
        
    }

    public static function todays_call_summary_by_queue($instance_id, $queue){

        $start = Carbon::now()->startOfDay()->shiftTimezone(env("TIMEZONE"));
        //print $start.PHP_EOL;
        $now = Carbon::now()->shiftTimezone(env("TIMEZONE"));  
        //print $now.PHP_EOL;

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 

        /*
        $start = Carbon::yesterday()->startOfDay();
        //print $start.PHP_EOL;
        $now = Carbon::now()->startOfDay();
        //print $now.PHP_EOL;
        */


        //print_r(self::whereBetween('start_time', [$start, $now])->get()); 

        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->where('queue', $queue)->count())
        {
            //print "Get calls...".PHP_EOL;
            $todays_calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->where('queue', $queue)->get(); 
            //print_r($todays_calls); 
        }else{
            $todays_calls = []; 
        }

        if(empty($todays_calls)){
            return []; 
        }
        
        return self::call_summary_report($todays_calls); 
        
    }

    public static function yesterday_call_summary_by_queue($instance_id, $queue){

        $start = Carbon::yesterday()->startOfDay()->shiftTimezone(env("TIMEZONE"));
        $now = Carbon::now()->startOfDay()->shiftTimezone(env("TIMEZONE")); 

        $start = $start->timezone("UTC"); 
        $now = $now->timezone("UTC"); 

        if (self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->where('queue', $queue)->count())
        {
            //print "Get calls...".PHP_EOL;
            $todays_calls = self::whereBetween('start_time', [$start, $now])->where('instance_id', $instance_id)->where('queue', $queue)->get();
            
        }else{
            $todays_calls = []; 
        }

        if(empty($todays_calls)){
            return []; 
        }
        
        return self::call_summary_report($todays_calls); 
        
    }

    

    public static function call_summary_report($calls){
        
        $time = 0; 
        $callkeys = []; 
        $callsummary = [];
        $callcount = 0; 
        $callsummary['abandons'] = [];
        $callsummary['inbound'] = [];  
        $callsummary['callbacks'] = [];  
        $callsummary['other_prob_callback'] = [];  
        $callsummary['outbound'] = [];  
        $callsummary['inboundtimeouts'] = [];  
        $callsummary['transfer'] = [];  
        $callsummary['inboundhangups'] = [];  

        foreach($calls as $key => $call){
            $call = json_decode(json_encode($call), true); 
            $callkeys[$call['contact_id']] = $call; 
        }

        //print_r($callkeys); 

        

        foreach($callkeys as $call){
            $callcount ++; 
            $callduration = $call['contact_duration']; 

            if($callduration == 0){
                // Ignore records if no call duration. 
                continue; 
            }

            if($callduration < 60){
                $callduration = 60; 
            }

            $time = $time + $callduration; 

            if($call['queue'] && $call['queue_duration'] > 0){
                if(!$call['connect_to_agent_time'] && !$call['next_contact_id']){
                    $callsummary['abandons'][] = $call;
                    continue;  
                }
                if($call['initiation_method'] == "INBOUND" && $call['connect_to_agent_time']){
                    $callsummary['inbound'][] = $call; 
                    continue; 
                }
                if($call['initiation_method'] == "CALLBACK"){
                    $callsummary['callbacks'][] = $call; 
                    continue; 
                }
                if(!$call['connect_to_agent_time'] && $call['next_contact_id']){
                    $callsummary['other_prob_callback'][] = $call; 
                    continue; 
                }
                //print_r($call); 
            }
            elseif($call['initiation_method'] == "OUTBOUND"){
                $callsummary['outbound'][] = $call; 
                continue; 
            }
            elseif($call['disconnect_reason'] == "CONTACT_FLOW_DISCONNECT"){
                $callsummary['inboundtimeouts'][] = $call;
                continue; 
            }
            else{
                //$inboundhangups[] = $call;
                $json = json_decode($call['cdr_json'], true); 
                if($json['TransferredToEndpoint']){
                    $callsummary['transfer'][] = $call; 
                    continue; 
                }
                if($json['DisconnectReason'] == "CUSTOMER_DISCONNECT"){
                    $callsummary['inboundhangups'][] = $call; 
                    continue; 
                }
                //print_r($json); 
            }
        }

        //print_r($callsummary).PHP_EOL;

        $report = []; 
        foreach($callsummary as $key => $value){
            //print $key.": ". count($value).PHP_EOL;
            $report[$key] = count($value);
        }

        $report['seconds'] = $time; 

        $minutes = $time / 60;
        $report['minutes'] = $minutes; 
        //print $minutes.PHP_EOL; 
        $cost_per_minute = .018 + .015 + 0.0022; // Connect Cost per min + Contact Lens per min+ Telecom DID per min. .0352 per min
        $usage_cost = $minutes * $cost_per_minute;
        //print $callcount; 
        //$transaction_cost = $callcount * .025; 
        $report['totalcalls'] = $callcount; 
        $report['cost'] = $usage_cost; 

        //$report['report'] = $callsummary; 

        //print_r($report); 

        return $report; 
    }

}
