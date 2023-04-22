<?php

namespace Profile;


class StorageProfile
{
    public $osDisk = [];

    // public $osDisk = [
    //     "name" => 'new_vm_osdisk',
    //     "osType" => 'Linux',
    //     "createOption" => 'fromImage'
    //     // "storageAccountType" => "Premium_LRS",
    //     // "caching" => "ReadWrite"
        
    // ];
    
    public $imageReference = [];

    // public $imageReference = [
    //     "sku"=> "16.04-LTS",
    //     "publisher"=> "Canonical",
    //     "version"=> "latest",
    //     "offer"=> "UbuntuServer"
    // ];

    public function addOsDisk($osDisk)
    {
        $this->osDisk = $osDisk;
    }

    public function addImageReference($imageReference)
    {
        $this->imageReference = $imageReference;
    }

}