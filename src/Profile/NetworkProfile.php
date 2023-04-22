<?php

namespace Profile;

use AzureClient;


class NetworkProfile
{
    public $networkInterfaces = [];

    public function addNetworkInterface($url)
    {
        $this->networkInterfaces[] = $url;
    }
}