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

        if(!$calls){
            return null; 
        }
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

        $dates = collect();

        $dates = []; 

        foreach( range( -30, 0 ) AS $i ) {
            $date = Carbon::now()->addDays( $i )->format( 'Y-m-d' );

            $start = Carbon::now()->addDays( $i )->startOfDay()->shiftTimezone(env("TIMEZONE"));
            $end = Carbon::now()->addDays( $i + 1 )->startOfDay()->shiftTimezone(env("TIMEZONE"));

            $start = $start->timezone("UTC"); 
            $end = $end->timezone("UTC"); 

            if (self::whereBetween('start_time', [$start, $end])->where('instance_id', $instance_id)->count())
            {
                //print "Get calls...".PHP_EOL;
                $calls = self::whereBetween('start_time', [$start, $end])->where('instance_id', $instance_id)->get(); 
                //print_r($todays_calls); 
            }else{
                $calls = []; 
            }

            $report = self::call_summary_report($calls); 

            //return $report; 

            //$dates->put( $date, $report);
            $report['date'] = $date; 

            $dates[] = $report; 
        }

        //print_r($dates); 

        return $dates; 
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

        $agent_calls = []; 
        foreach($todays_calls as $call){

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
        
        //return self::call_summary_report($todays_calls); 

        $callsummary = self::call_summary_report($todays_calls); 

        return $callsummary; 
        
        
    }

    

    public static function get_call_types($calls){
        
        $time = 0; 
        $agenttime = 0;
        $transfertime = 0;
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
        
        // Review CallBacks to get associated call records. 
        $callback_review = []; 
        foreach($callkeys as $key => $call){
            $call = json_decode(json_encode($call), true);
            $call = json_decode($call['cdr_json'], true); 
            if($call['InitialContactId'] != null){
                if(isset($callback_review[$call['InitialContactId']])){
                    continue; 
                }
                $initialcall = Ctr::where('contact_id', $call['InitialContactId'])->first(); 
                //print_r($initialcall); 
                
                $initialcall = json_decode(json_encode($initialcall), true);
                $initialcall_array = json_decode($initialcall['cdr_json'], true); 
                //print_r($initialcall); 

                $callback_review[$initialcall_array['ContactId']][$initialcall_array['ContactId']] = $initialcall;
                if($initialcall_array["NextContactId"]){
                    $next = $initialcall_array["NextContactId"]; 
                    
                    //return $next; 

                    //unset($callkeys[$call['ContactId']]); 
                    
                    while($next){
                        //print_r($next); 
                        $nextcall = Ctr::where('contact_id', $next)->first();
                        
                        
                        //$nextcall = (array)$nextcall; 
                        $nextcall = json_decode(json_encode($nextcall), true); 
                        //print_r($nextcall); 
                        if(!$nextcall){
                            break; 
                        }
                        $nextcall_array = json_decode($nextcall['cdr_json'], true); 

                        $callback_review[$initialcall_array['ContactId']][$nextcall_array['ContactId']] = $nextcall;
                        if($nextcall_array["NextContactId"]){
                            $next = $nextcall_array["NextContactId"]; 
                        }else{
                            $next = false; 
                        }
                        if(isset($callkeys[$nextcall_array['ContactId']])){
                            unset($callkeys[$nextcall_array['ContactId']]); 
                        }
                    }

                    if(isset($callkeys[$initialcall_array['ContactId']])){
                        unset($callkeys[$initialcall_array['ContactId']]); 
                    }
                }
            }
        }

        //print_r($callback_review); 
        //return $callback_review; 
        //print_r($callkeys); 

        //return $callback_review; 
        //print_r($callback_review); 

        
        foreach($callback_review as $key => $array){
            //print_r($array); 
            $initialcall = $array; 
            foreach($array as $initialcall){
                $call_array = json_decode(json_encode($initialcall), true); 
                
                $initialcall_array = json_decode($call_array['cdr_json'], true); 
                //print_r($initialcall_array);
                //$contact_duration = $initialcall_array['contact_duration']; 

                if($initialcall_array['ConnectedToSystemTimestamp']){
                    $endtime = Carbon::parse($initialcall_array['ConnectedToSystemTimestamp']);
                    $starttime = Carbon::parse($initialcall_array['DisconnectTimestamp']);
                    $call_array['contact_duration'] = $endtime->diffInSeconds($starttime); 
                }else{
                    $call_array['callback_duration'] = $call_array['contact_duration'];
                    $call_array['contact_duration'] = 0;
                }

                $callkeys[$initialcall_array['ContactId']] = $call_array; 

                //print_r($call_array); 
                
            }
            
        }

        

        foreach($callkeys as $call){
            $callcount ++; 
            $callduration = $call['contact_duration'];  

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
                    $callsummary['inbound_callback'][] = $call; 
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
            elseif($call['disconnect_reason'] == "THIRD_PARTY_DISCONNECT"){
                $callsummary['transfer'][] = $call;
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
        return $callsummary; 
    }


    public static function agent_summary_report($calls){

        $callsummary = self::get_call_types($calls); 

        $agent_calls = []; 
        foreach($callsummary as $key => $value){
            //print_r($key); 
            $count = 0; 
            
            foreach($value as $call){
                if(!$call['agent']){
                    continue; 
                }
                if($call['initiation_method'] == "INBOUND" && $call['connect_to_agent_time']){
                    $callsummary['inbound'][] = $call; 
                    $agent = $call['agent']; 
                    if(!array_key_exists($key, $agent_calls)){
                        $agent_calls[$key] = []; 
                    }
                    if(!array_key_exists($agent, $agent_calls[$key])){
                        $agent_calls[$key][$agent]['calls'] = 1;
                        $agent_calls[$key][$agent]['time'] = $call['connect_to_agent_duration'];
                    }else{
                        $agent_calls[$key][$agent]['calls'] = $agent_calls[$key][$agent]['calls'] + 1; 
                        $agent_calls[$key][$agent]['time'] = $agent_calls[$key][$agent]['time'] + $call['connect_to_agent_duration'];
                    }
                }
                
            }
        }

        return $agent_calls; 
    }
    


    public static function call_summary_report($calls){

        $callsummary = self::get_call_types($calls); 

        $callcount = 0; 
        $time = 0; 
        $agenttime = 0; 
        $transfertime = 0; 
        $report = []; 
        foreach($callsummary as $key => $value){
            //print $key.": ". count($value).PHP_EOL;
            if(is_array($value)){
                $report[$key] = count($value); 

                foreach($value as $call){
                    $callcount ++; 
                    $callduration = $call['contact_duration']; 
        
                    if($callduration == 0){
                        // Ignore records if no call duration. 
                        //continue; 
                    }
        
                    if($callduration < 60){
                        $callduration = 60; 
                    }
        
                    $time = $time + $callduration; 

                    if($call['connect_to_agent_duration']){
                        //print_r($call); 
                        $agenttime =  $agenttime + $call['connect_to_agent_duration'];
                    }
                    
                    if($key == 'transfer'){
                        
                        //print_r($call); 
                        //$inboundhangups[] = $call;
                        $json = json_decode($call['cdr_json'], true); 
                        
                        if($json['TransferCompletedTimestamp']){
                            $endtime = Carbon::parse($json['DisconnectTimestamp']);
                            $transferstart = Carbon::parse($json['TransferCompletedTimestamp']);
        
                            $calltransfertime = $endtime->diffInSeconds($transferstart); 

                            
                            $transfertime = $transfertime + $calltransfertime;
                            continue; 
                        }
                    }
                }
            }else{
                continue; 
            }
        }

        $report['seconds'] = $time; 

        $minutes = $time / 60;
        $agenttime = $agenttime / 60;
        $transfertime = $transfertime / 60;
        $report['minutes'] = $minutes; 
        $report['transfertime'] = $transfertime; 
        //print $minutes.PHP_EOL; 
        //$cost_per_minute = .018 + .015 + 0.0022; // Connect Cost per min + Contact Lens per min+ Telecom DID per min. .0352 per min
        //$usage_cost = $minutes * $cost_per_minute;
        //print $callcount; 
        //$transaction_cost = $callcount * .025; 
        $report['totalcalls'] = $callcount; 
        //$report['cost'] = $usage_cost; 

        $report['lenscost'] = $agenttime * .015; // Lens only analyses calls connected to an agent. 
        $report['telecocost'] = ($minutes + $transfertime) * 0.0022; // This equals inbound costs plus any outound costs due to external transfers. 
        $report['connectcost'] = ($minutes + $transfertime) * .018; // This equals inbound costs plus any outound costs due to external transfers. 

        $report['totalcost'] = $report['lenscost'] + $report['telecocost'] + $report['connectcost']; 

        //$report['report'] = $callsummary; 

        //print_r($report); 
        //$report['agentsummary'] = $agent_calls; 

        return $report; 
    }
}
