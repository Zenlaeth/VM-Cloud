<?php

namespace Azure\Profile;

use Azure\AzureClient;


class NetworkProfile
{
    public $networkInterfaces = [];

    public function addNetworkInterface($url)
    {
        $this->networkInterfaces[] = $url;
    }
}