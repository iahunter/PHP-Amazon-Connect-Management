<?php

namespace App\AWS;

class Kinesis
{
    $result = $client->createStream([
        'ShardCount' => <integer>, // REQUIRED
        'StreamName' => '<string>', // REQUIRED
    ]);
}