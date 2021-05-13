<?php

namespace App\AWS;

use Aws\S3\S3Client;
use Aws\S3\S3MultiRegionClient;

class S3
{
    public $S3MultiRegionClient;
    public $S3Client;

    public function __construct($region,$key,$secret)
    {
        $this->S3MultiRegionClient = new S3MultiRegionClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        $this->S3Client = new S3Client([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);
    }

    //Returns an Array with Bucket name and Region
    /* Example Use
        $S3 = new S3(   
                        $this->region,
                        $this->app_key,
                        $this->app_secret
                    );
                    
        $bucketlist = $S3->listBucketsAndRegions();
    */

    /*
        $newbuckets = [
                        "arn:aws:s3:::{$this->instance}-ctr",
                        "arn:aws:s3:::{$this->instance}-agent-events",
                        "arn:aws:s3:::amazon/connect/{$this->instance}",       
        ];

        $bucketlist = $S3->listBucketsAndRegions();

        print_r($bucketlist);


        foreach($bucketlist as $bucketname => $region){
            print $bucketname . $region.PHP_EOL;

            $S3Client = $this->S3Client($region); 
        }
    */
    
    public function listBucketsAndRegions()
    {
        $buckets = $this->S3Client->listBuckets();

        $bucketlist = [];

        foreach($buckets['Buckets'] as $bucket)
        {
            $region = $this->S3MultiRegionClient->determineBucketRegion($bucket['Name']);

            $bucketlist[$bucket['Name']] = $region;
        }
            
        return $bucketlist;
    }
}