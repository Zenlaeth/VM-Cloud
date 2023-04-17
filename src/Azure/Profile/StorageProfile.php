<?php

namespace Azure\Profile;


class StorageProfile
{
    public $osDisk = [
        "name" => 'new_vm_osdisk',
        "osType" => 'Linux',
        "createOption" => 'fromImage'
        // "storageAccountType" => "Premium_LRS",
        // "caching" => "ReadWrite"
        
    ];

    public $imageReference = [
        "sku"=> "16.04-LTS",
        "publisher"=> "Canonical",
        "version"=> "latest",
        "offer"=> "UbuntuServer"
    ];
}