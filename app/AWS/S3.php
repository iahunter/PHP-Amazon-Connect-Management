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