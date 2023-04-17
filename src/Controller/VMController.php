<?php

namespace App\Controller;

use Azure\AzureClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Azure\Entity\VirtualMachine;
use Azure\AzureVMClient;
use Azure\Entity\NetworkInterface;
use Azure\Profile\NetworkProfile;
use Azure\Profile\StorageProfile;

class VMController extends AbstractController
{
    /**
     * @Route("/index", name="app_index")
     */
    public function index(): Response
    {
        return $this->render('vm/index.html.twig', [
            'controller_name' => 'VMController',
        ]);
    }

    /**
     * @Route("/create-vm", name="app_create_vm")
     */
    public function createVM(): Response
    {
        // Create client and authenticate LATER.
        $azureClient = new AzureVMClient(
            $this->getParameter('wm.subscription_id')
        );

        // Do some other stuff.

        $azureClient->authenticate(
            $this->getParameter('wm.tenant'),
            $this->getParameter('wm.application_id'),
            $this->getParameter('wm.password'));
        
        // Create new machine
        $name = 'new_vm';
        $region = 'westeurope';
        $machine = new VirtualMachine($name, $region);

        $resourceGroupName = 'azure-sample-group-virtual-machines';

        // // Delete afterwards.
        // $azureClient->deleteResourceGroup($resourceGroupName);
        // die();

        $azureClient->createResourceGroup($resourceGroupName);
        $machine->setResourceGroup($resourceGroupName);

        // Add or change Profiles..
        $storage = new StorageProfile();
        $machine->setStorageProfile($storage);
        
        // Create a public ip address
        $ipName = 'azure-sample-ip-config';
        $azureClient->createPublicIpAddress($ipName, $resourceGroupName, $region);


        // Create a virtual network
        $vnetName = 'azure-sample-vnet';
        $azureClient->createVirtualNetwork($vnetName, $resourceGroupName);


        // Create a subnet
        $subnetName = 'azure-sample-subnet';
        $azureClient->createSubnet($vnetName, $subnetName, $resourceGroupName);

        // Create a network interface
        $networkName = 'azure-sample-nic';
        $interface = new NetworkInterface;
        $azureClient->createNetworkInterface(
            $networkName, 
            $machine, 
            $interface, 
            $this->getParameter('wm.subscription_id'),
            $resourceGroupName,
            $vnetName,
            $subnetName,
            $ipName
        );

        // Add Network Profile..
        $network = new NetworkProfile();
        $network->addNetworkInterface([
            "id" => "/subscriptions/" . $this->getParameter('wm.subscription_id') . "/resourceGroups/" . $resourceGroupName . "/providers/Microsoft.Network/networkInterfaces/" . $networkName
        ]);
        $machine->setNetworkProfile($network);

        // Create a VM.
        $azureClient->createVM($machine);

        return $this->json("OK", Response::HTTP_OK);
    }
}
