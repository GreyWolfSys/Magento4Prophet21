<?php

namespace Altitude\P21\Observer;

use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
{
    private $p21;
    private $customerFactory;
    private $addressFactory;
    private $regionFactory;

    public function __construct(
        \Altitude\P21\Model\P21 $p21,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory
    ) {
        $this->p21 = $p21;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;

    }

    public function ShowInfo($item, $objectManager, $customer)
    {
        if (isset($item['central_phone_number'])) {
            $phone = $item['central_phone_number'];
            if (strlen($phone) < 1) {
                $phone = '1112223333';
            }
        } else {
            $phone = '1112223333';
        }
        $this->p21->gwLog('6');
        try {
            unset($address);
            $addressOBJ = $objectManager->get('\Magento\Customer\Model\AddressFactory');
            try {
                $this->p21->gwLog('8');
                foreach ($customer->getAddresses() as $address1) {
                    $erp = $address1->getData('ERPAddressID');
                    $this->p21->gwLog('ERPAddressID: ' . $erp);
                    if ($erp == $item['ship_to_id']) {
                        $address = $address1;

                        break;
                    }
                    // }
                }
            } catch (\Exception $e) {
                $this->p21->gwLog($e->getMessage());
            }
            $countrycd = $item['phys_country'];
            if (! isset($countrycd) || empty($countrycd)) {
                $countrycd = 'US';
            }
            $this->p21->gwLog('7');
            $this->p21->gwLog('ADDRESS:  getting region from erp addr ' . $countrycd);
            $region = $objectManager->create('Magento\Directory\Model\Region');
            $regionId = $region->loadByCode($item['phys_state'], $countrycode)->getId();

            if (isset($region)) {
                try {
                    $statecd = $region->getData()['region_id'];
                } catch (\Exception $e) {
                    $statecd = '';
                }
            } else {
                $statecd = '';
            }

            if (! isset($address)) {
                $this->p21->gwLog('no address found for ' . $item['ship_to_id']);
                $address = $addressOBJ->create();
            }
            $address->setCustomerId($customer->getId());
            //$address->setCompany($customer->getCustomer()->getName());
            $address->setFirstname($customer->getFirstname());
            $address->setLastname($customer->getLastname());
            $address->setStreet($item['phys_address1']);
            $address->setCity($item['phys_state']);
            $address->setRegionId($statecd);
            $address->setPostcode($item['phys_postal_code']);
            $address->setCountryId($countrycd);
            $address->setTelephone($phone);
            $address->setFax($item['central_fax_number']);
            $address->setIsDefaultBilling('0');
            $address->setIsDefaultShipping('0');
            $address->setSaveInAddressBook('1');
            $address->SetData('ERPAddressID', $item['ship_to_id']);
            $address->save();
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage() . '!');
        }
    }


    public function ProcessAddress($GCShip,$customer){
        if (!isset($GCShip["errordesc"]) && !isset($GCShip["_errorMsg"]) ) {
             $item=$GCShip;
              $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                if (isset($item["central_phone_number"])) {
                    $phone = $item["central_phone_number"];
                    if (strlen($phone) < 1) {
                        $phone = "1112223333";
                    }
                } elseif (isset($item["CentralPhoneNumber"])) {
                    $phone = $item["CentralPhoneNumber"];
                    if (strlen($phone) < 1) {
                        $phone = "1112223333";
                    }
                } else {
                    $phone = "1112223333";
                }

                try {
                    unset($address);
                    $addressOBJ = $objectManager->get('\Magento\Customer\Model\AddressFactory');
                    try {
                            $this->p21->gwLog("looping address");
                        foreach ($customer->getAddresses() as $address1) {
                            $erp = $address1->getData("ERPAddressID");

                          //  $this->p21->gwLog("looking for " . $erp);
                            if ($erp == (isset($item["ship_to_id"]) ? $item["ship_to_id"] : $item["AddressId"]) ){ //$item["ship_to_id"] ){                                          //isset($item["ship_to_id"]) ? $item["ship_to_id"] : $item["AddressId"]) {
                                $this->p21->gwLog("found " . $erp);
                                $address = $address1;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->p21->gwLog($e->getMessage());
                    }

                    if (isset($item["phys_country"])) {
                        $countrycode = $item["phys_country"];
                    } elseif (isset($item["PhysCountry"])) {
                        $countrycode = $item["PhysCountry"];
                    } else {
                        $countrycode = "US";
                    }
                    if ($countrycode = "USA") $countrycode = "US";
                    
                    if(empty($countrycode))  $countrycode = "US";
                    $this->p21->gwLog("ADDRESS:  getting region from erp state " . $item["phys_state"]);
                   
                    $region = $objectManager->create('Magento\Directory\Model\Region');
                    if (strpos( $this->p21->getConfigValue('apiurl'),'p21cloud') ===false  ) {
                        $regionId = $region->loadByCode($item["phys_state"], $countrycode)->getId();
                    } else {
                        $regionId = $region->loadByCode($item["PhysState"], $countrycode)->getId();
                    }

                    $phys_state = $regionId;
                    if (!isset($address)) {
                        $this->p21->gwLog("no address found for " . (isset($item["ship_to_id"]) ? $item["ship_to_id"] : $item["AddressId"]) ) ;
                        $this->p21->gwLog("creating address");
                       // $address = $addressOBJ->create();
                        $address = $this->addressFactory->create();
                    }
                 //   $this->p21->gwLog("ADDRESS:  setting fields");
                    $address->setCustomerId($customer->getId());
                    $address->setFirstname($customer->getFirstname());
                    $address->setLastname($customer->getLastname());
                    $address->setStreet(isset($item["phys_address1"]) ? $item["phys_address1"] : $item["PhysAddress1"]);
                    $address->setCity(isset($item["phys_city"]) ? $item["phys_city"] : $item["PhysCity"]);
                    $address->setRegionId($phys_state);
                    $address->setPostcode(isset($item["phys_postal_code"]) ? $item["phys_postal_code"] : $item["PhysPostalCode"]);
                              //     $this->p21->gwLog("Address country= " . $countrycode);
                    $address->setCountryId($countrycode);
                    $address->setTelephone($phone);
                    $address->setFax(isset($item["central_fax_number"]) ? $item["central_fax_number"] : $item["CentralFaxNumber"]);
                    $address->setIsDefaultBilling('0');
                    $address->setIsDefaultShipping('0');
                    $address->setSaveInAddressBook('1');
                    $this->p21->gwLog("ADDRESS:  saving fields");
                    $address->SetData("ERPAddressID", (isset($item["ship_to_id"]) ? $item["ship_to_id"] : $item["AddressId"]) );
                    $address->save();
$this->p21->gwLog("ADDRESS: done saving fields");
                    //$this->p21->gwLog("ship_to_id=" . $address->getData("ERPAddressID") . " and " . isset($item["ship_to_id"]) ? $item["ship_to_id"] : $item["AddressId"]);

                    // break;
                } catch (\Exception $e) {
                    $this->p21->gwLog("Address error: " . $e->getMessage());
                }

        }

    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $disableAddressImport = $this->p21->getConfigValue('defaults/gwcustomer/disable_address_import');

$this->p21->gwLog('Importing addresses');

        if ($disableAddressImport) {
             $this->p21->gwLog('feature disabled');
            return;
        }

        $configs = $this->p21->getConfigValue(['cono', 'p21customerid']);
        extract($configs);

        $this->p21->gwLog('Customer Logged In now');
        $this->p21->gwLog($_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
        $customer = $observer->getEvent()->getCustomer();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Framework\App\Http\Context');
        $isLoggedIn = $customerSession->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);

        $this->p21->gwLog("check if logged in");
        if ($isLoggedIn) {
            $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
            $customerData = $customerSession2->getCustomer();

            $customer = $customerSession2->getCustomer();
            $cust = $customerSession2->getCustomerData();

            $p21custno = $customerData['p21_custno'];
            $cattrValue = $customer->getCustomAttribute('p21_custno');
        } else {
            $p21custno = '';
        }
        $this->p21->gwLog('Cust no: ' . $p21custno); //Get customer name
        if ($isLoggedIn and $p21custno != $p21customerid and !empty($p21custno) ) {
             $_SESSION["loggingin"]=true;
            try {
                $GCShip = $this->p21->SalesShipToList($cono, $p21custno);

                if (isset($GCShip)) {
                 //   $this->p21->gwLog("gcship is set");
                    if (isset($GCShip["SalesShipToListResponseContainerItems"]) ) {
                        $this->p21->gwLog("multiple records");
                        foreach ($GCShip["SalesShipToListResponseContainerItems"] as $item) {
                          $this->ProcessAddress($item,$customer);
                        }
                    } else {
                        $this->p21->gwLog("single record");
                         $this->ProcessAddress($GCShip,$customer);
                    }
                }
            } catch (\Exception $e) {
                $this->p21->gwLog($e->getMessage() . '@');
                $_SESSION["loggingin"]=false;
            }
            $_SESSION["loggingin"]=false;
        }
    }
}
