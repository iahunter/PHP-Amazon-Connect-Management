<?php

namespace App\AWS;

class Firehose
{
    public function __construct($account, $region){
        $this->account = $account;
        $this->region = $region;
    }
    
    public function generateKinesisStreamToS3Firehose($stream_arn, $firehose_name, $bucket, $role_arn, $type, $tags = [])
    {
        return [    
                    "DeliveryStreamName" => $firehose_name,
                    "DeliveryStreamType" => "KinesisStreamAsSource",
                    "KinesisStreamSourceConfiguration" => [
                            "KinesisStreamARN" => $stream_arn,
                            "RoleARN" => $role_arn
                    ],

                    "S3DestinationConfiguration" => [
                        "RoleARN" => $role_arn,
                        "BucketARN" => "arn:aws:s3:::$bucket",
                        "Prefix" => $type."-",
                        "ErrorOutputPrefix" => $type."-errors-",
                        "BufferingHints" => [
                                "SizeInMBs" => 5,
                                "IntervalInSeconds" => 300
                        ],
                        "CompressionFormat" => "UNCOMPRESSED",
                        "EncryptionConfiguration" => [
                                "NoEncryptionConfig" => "NoEncryption",
                        ],

                        "CloudWatchLoggingOptions" => [
                                "Enabled" => true,
                                "LogGroupName" => "/aws/kinesisfirehose/$firehose_name",
                                "LogStreamName" => "S3Delivery",
                        ],
                    ],

                    'Tags' => $tags,
                        
                ];

    }
}