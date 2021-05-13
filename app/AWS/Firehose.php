<?php

namespace App\AWS;

class Firehose
{
    public function __construct($account, $region){
        $this->account = $account;
        $this->region = $region;
    }
    
    public function generateKinesisStreamToS3Firehose($instance_name, $stream_arn, $firehose_name, $bucket, $role_arn, $type)
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
                        "Prefix" => $type,
                        "ErrorOutputPrefix" => $type."Errors",
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

                    'Tags' => [
                        [
                            'Key' => 'connect', // REQUIRED
                            'Value' => $instance_name,
                        ],
                    ],
                ];

    }
}