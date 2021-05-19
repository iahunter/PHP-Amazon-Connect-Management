<?php

namespace App\AWS;

class Connect
{

    public function _construct($region, $key, $secret){
        $this->region = $region;
        $this->app_key = $key;
        $this->app_secret = $secret;

        $this->ConnectClient = new ConnectClient([
            'version'     => 'latest',
            'region'      => $this->region,
            'credentials' => [
                'key'    => $this->app_key,
                'secret' => $this->app_secret,
            ],
        ]);

    }

}