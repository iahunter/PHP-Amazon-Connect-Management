<?php

namespace App\AWS;

use Aws\Iam\IamClient;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IAM
{
    public function __construct($account, $region){
        $this->account = $account;
        $this->region = $region;
    }

    public function allowDatabase()
    {
        return [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => [   "glue:GetTable",
                            "glue:GetTableVersion",
                            "glue:GetTableVersions",
                        ],

            "Resource" => [ "arn:aws:glue:$this->region:$this->account:catalog",
                            "arn:aws:glue:$this->region:$this->account:database/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%",
                            "arn:aws:glue:$this->region:$this->account:table/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%"
                    ]

        ];
    }

    public function allowS3($bucket)
    {
        $array = [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => [   "s3:AbortMultipartUpload",
                            "s3:GetBucketLocation",
                            "s3:GetObject",
                            "s3:ListBucket",
                            "s3:ListBucketMultipartUploads",
                            "s3:PutObject",
            ],

            "Resource" => [],
        ];

        $array["Resource"][] = "arn:aws:s3:::$bucket";
        $array["Resource"][] = "arn:aws:s3:::$bucket/*";

        return $array;
    }

    public function allowLambda()
    {
        return [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => ["lambda:InvokeFunction",
                         "lambda:GetFunctionConfiguration",
            ],

            "Resource" => ["arn:aws:lambda:$this->region:$this->account:function:%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%"
            ]
        ];
    }

    public function allowLogs($firehose_name)
    {
        return [
                "Sid" => "",
                "Effect" => "Allow",
                "Action" => ["logs:PutLogEvents",
                ],

                "Resource" => ["arn:aws:logs:$this->region:$this->account:log-group:/aws/kinesisfirehose/$firehose_name:log-stream:*"
                ],
        ];
    }

    public function allowDecryptS3()
    {
        return [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => [   "kms:GenerateDataKey",
                            "kms:Decrypt",
            ],

            "Resource" => ["arn:aws:kms:$this->region:$this->account:key/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%"
            ],

            "Condition" => [
                "StringEquals" => ["kms:ViaService" => "s3.$this->region.amazonaws.com",
                ],
                "StringLike" => ["kms:EncryptionContext:aws:s3:arn" => [
                                    "arn:aws:s3:::%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%/*",
                                ]
                ],
            ]
        ];
    }

    public function allowKinesisStreams(array $streamarns)
    {
        return [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => [   "kinesis:DescribeStream",
                            "kinesis:GetShardIterator",
                            "kinesis:GetRecords",
                            "kinesis:ListShards",
            ],
            //"Resource" => ["arn:aws:kinesis:$this->region:$this->account:stream/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%"
            "Resource" => $streamarns,
        ];
    }

    public function allowDecryptKinesis()
    {
        return [
            "Sid" => "",
            "Effect" => "Allow",
            "Action" => ["kms:Decrypt"
            ],
            "Resource" => ["arn:aws:kms:$this->region:$this->account:key/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%",
            ],
            "Condition" => [
                "StringEquals" => ["kms:ViaService" => "kinesis.$this->region.amazonaws.com",
                ],

                "StringLike" => ["kms:EncryptionContext:aws:kinesis:arn" => "arn:aws:kinesis:$this->region:$this->account:stream/%FIREHOSE_POLICY_TEMPLATE_PLACEHOLDER%",
                ],
            ],
        ];
    }    
}
