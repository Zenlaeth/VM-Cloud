<?php

namespace Azure\Profile;

use Azure\AzureClient;


class NetworkProfile
{
    // protected $applicationId;
    // protected $nicName;

    // public function __construct()
    // {
    //     $this->applicationId = getenv('VM_APPLICATION_ID');
    //     $this->nicName = getenv('NIC_NAME');
    // }

    // public $networkInterfaces = [];

    public $networkInterfaces = [];

    /**
     * @param OsProfile $profile
     */
    public function addNetworkInterface($url)
    {
        $this->networkInterfaces[] = $url;
    }
}