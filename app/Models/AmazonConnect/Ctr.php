<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ctr extends Model
{
    use HasFactory;
    
    protected $table = 'connect_ctrs';
    
    protected $primaryKey = 'contact_id';

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
        'cdr_json',
    ];


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

}
