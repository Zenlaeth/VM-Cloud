<?php

namespace App\Controller;

use AzureClient;
use App\Form\LoginType;
use Client\AzureVMClient;
use App\Entity\VirtualMachine;
use Profile\NetworkProfile;
use Profile\StorageProfile;
use App\Entity\NetworkInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class VMController extends AbstractController
{
    /**
     * @Route("/index/{email}", name="app_index")
     */
    public function index(string $email)
    {
        if(!$email) {
            return $this->redirectToRoute('account_login');
        }

        return $this->render('index.html.twig', [
            'controller_name' => 'VMController',
            'email' => $email
        ]);
    }

    /**
     * Permet d'afficher le formulaire de connexion
     * 
     * @Route("/login", name="account_login")
     * 
     * @return Response
     */
    public function login(Request $request)
    {
        $form = $this->createForm(LoginType::class);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $email = $request->request->get('login')['email'];
            $password = $request->request->get('login')['password'];
            $token = $request->request->get('login')['_token'];
            
            $users = [
                [
                    'email' => 'vm1@hotmail.com',
                    'password' => 'vm1'
                ],
                [
                    'email' => 'vm2@hotmail.com',
                    'password' => 'vm2'
                ],
                [
                    'email' => 'vm3@hotmail.com',
                    'password' => 'vm3'
                ]
            ];

            foreach($users as $user) {
                if($user['email'] == $email && $user['password'] == $password) {
                    $this->addFlash(
                        'success',
                        "Vous vous êtes bien connecté."
                    );
        
                    return $this->redirectToRoute('app_index', ['email' => $email]);
                }
            }
        }

        return $this->render('login.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/create-vm/{type}/{email}", name="app_create_vm")
     */
    public function createVM($type, $email): Response
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
        $vmNumber = 1;
        $machineName = 'new_vm_' . $vmNumber;
        $region = 'westeurope';

        if($azureClient->getVmDetailsByName($machineName)) {
            $vmNumber = strval(count($azureClient->getVmDetailsByName($machineName)) + 1);
            $machineName = 'new_vm_' . $vmNumber;
        }

        $machine = new VirtualMachine($machineName, $region);

        $resourceGroupName = 'azure-sample-group-virtual-machines';
        // // Delete afterwards.
        // $azureClient->deleteResourceGroup($resourceGroupName);
        // die();

        $azureClient->createResourceGroup($resourceGroupName);
        $machine->setResourceGroup($resourceGroupName);
        
        // Add or change Profiles..
        $storage = new StorageProfile();
        if($type == 'Ubuntu') {
            $storage->addOsDisk([
                "name" => 'new_vm_osdisk_ubuntu_' . $vmNumber,
                "osType" => 'Linux',
                "createOption" => 'fromImage'
            ]);
            $storage->addImageReference([
                "sku" => "16.04-LTS",
                "publisher" => "Canonical",
                "version"=> "latest",
                "offer" => "UbuntuServer"
            ]);
        }
        else if($type == 'Windows') {
            $storage->addOsDisk([
                "name" => 'new_vm_osdisk_windows_' . $vmNumber,
                "createOption" => 'fromImage'
            ]);
            $storage->addImageReference([
                "sku"=> "2016-Datacenter",
                "publisher" => "MicrosoftWindowsServer",
                "version" => "latest",
                "offer" => "WindowsServer"
            ]);
        }

        $machine->setStorageProfile($storage);
        
        // Create a public ip address
        $ipName = 'azure-sample-ip-config-' . $vmNumber;
        $azureClient->createPublicIpAddress($ipName, $resourceGroupName, $region);

        // Create a virtual network
        $vnetName = 'azure-sample-vnet-' . $vmNumber;

        if(!$azureClient->getVirtualNetworkDetail('azure-sample-vnet')) {
            $azureClient->createVirtualNetwork($vnetName, $resourceGroupName);
        }

        // Create a subnet
        $subnetName = 'azure-sample-subnet-' . $vmNumber;
    
        $azureClient->createSubnet($vnetName, $subnetName, $resourceGroupName);


        // Create a network interface
        $networkName = 'azure-sample-nic-' . $vmNumber;
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

        // Delete afterwards.
        // $azureClient->deleteResourceGroup($resourceGroupName);
        // $azureClient->deleteVM($machineName, $resourceGroupName);
        
        // return $this->json("OK", Response::HTTP_OK);
        $this->addFlash(
            'success',
            "La machine virtuelle a bien été créé !"
        );

        return $this->redirectToRoute('app_index', ['email' => $email]);
    }
}
