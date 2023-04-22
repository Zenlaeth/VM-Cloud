<?php

namespace App\Entity;


use Profile\HardwareProfile;
use Profile\networkProfile;
use Profile\osProfile;
use Profile\storageProfile;

interface VirtualMachineInterface
{

    /**
     * @return HardwareProfile
     */
    public function getHardwareProfile();

    /**
     * @return storageProfile
     */
    public function getStorageProfile();

    /**
     * @return osProfile
     */
    public function getOsProfile();

    /**
     * @return networkProfile
     */
    public function getNetworkProfile();

    /**
     * @return string
     */
    public function getResourceGroup();

    /**
     * @return string
     */
    public function getName();


}