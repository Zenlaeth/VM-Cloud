<?php

namespace Profile;


class HardwareProfile
{
    public $vmSize = "Standard_B1s";

    /**
     * @param mixed $vmSize
     */
    public function setVmSize($vmSize)
    {
        $this->vmSize = $vmSize;
    }
}
