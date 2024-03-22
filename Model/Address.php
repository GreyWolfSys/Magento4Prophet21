<?php

namespace Altitude\P21\Model;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Indexer\StateInterface;

class Address extends \Magento\Customer\Model\Address
{
    private $p21;

    private $regionFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        AddressMetadataInterface $metadataService,
        AddressInterfaceFactory $addressDataFactory,
        RegionInterfaceFactory $regionDataFactory,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\Reflection\DataObjectProcessor $dataProcessor,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data,
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->dataProcessor = $dataProcessor;
        $this->_customerFactory = $customerFactory;
        $this->indexerRegistry = $indexerRegistry;
        $this->p21 = $p21;
        $this->regionFactory = $regionFactory;
        $data=[];

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $directoryData,
            $eavConfig,
            $addressConfig,
            $regionFactory,
            $countryFactory,
            $metadataService,
            $addressDataFactory,
            $regionDataFactory,
            $dataObjectHelper,
            $customerFactory,
            $dataProcessor,
            $indexerRegistry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Processing object before save data.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $apiname = "SalesShipToInsertUpdate";
        $configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse', 'slsrepin', 'defaultterms']);
        extract($configs);
        $client = $this->p21->createSoapClient($apikey, $apiname);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $address_to_erp = $this->p21->getConfigValue('defaults/gwcustomer/address_to_erp');

        if ($address_to_erp) {

            $customer = $this->getCustomer();
            $addressData = $this->getData();

            $custno = $p21customerid;
            $shipto = "";

            if ($this->getData('ERPAddressID')) {
                $shipto = $this->getData('ERPAddressID');
            }

            if ($customer->getData('p21_custno')) {
                $custno = $customer->getData('p21_custno');
            }

//var_dump($addressData);
//exit;
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $region = $objectManager->create('Magento\Directory\Model\Region')->load($addressData["region_id"]);
          //  if (empty($addressData["street"])) return "";
            $addr1 = $addressData["street"];
            $addr2 = '';
            $city = $addressData["city"];
            try {
                $state = $region->getData()['code'];
            } catch (\Exception $e) {}
            $zipcd = $addressData["postcode"];
            $countrycd = $addressData["country_id"];
            
            if (!empty($addressData["country_id"])) {
                $countrycd = $addressData["country_id"];
            } else {
                $countrycd = "US";
            }
            
            $phoneno = $addressData["telephone"];
            if (isset($addressData["company"])) {
                $company = $addressData["company"];
            } else {
                $company = "";
            }

            $email = $customer->getData("email");

            $paramsShipTo = (object) [];
            $paramsShipTo->company_id = $cono;
            $paramsShipTo->customer_id = $custno;
            $paramsShipTo->ship_to_id = $shipto;
            $paramsShipTo->name = $company;
            $paramsShipTo->phys_address1 = $addr1;
            $paramsShipTo->phys_address2 = $addr2;
            $paramsShipTo->phys_city = $city;
          if (!empty($state))  $paramsShipTo->phys_state = $state;
            $paramsShipTo->phys_postal_code = $zipcd;
            $paramsShipTo->phys_country = $countrycd;
            $paramsShipTo->central_phone_number = $phoneno;
            $paramsShipTo->primary_salesrep = $slsrepin;
            $paramsShipTo->freight_cd = "FREIGHT CHARGE";
            $paramsShipTo->preferred_location_id = $whse;
            $paramsShipTo->default_branch = $whse;
            $paramsShipTo->terms = $defaultterms;
            $paramsShipTo->email_address = $email;
            $paramsShipTo->APIKey = $apikey;

            $rootparams = (object) [];
            $rootparams->SalesShipToInsertUpdateRequestContainer = $paramsShipTo;

            try {
                $result = $client->SalesShipToInsertUpdate($rootparams);
                $result = json_decode(json_encode($result), true);

                if (isset($result[ (isset($result["ship_to_id"]) ? "ship_to_id" : "AddressId")]) && $this->getData('ERPAddressID') == "") {
                    $shiptono =  (isset($result["ship_to_id"]) ? $result["ship_to_id"] : $result["AddressId"]);

                    if ($shiptono != "0" && $shiptono != "") {
                        $this->setData("ERPAddressID", $shiptono);
                    }
                }
            } catch (\Exception $e) {}
        }

        parent::beforeSave();
    }
}
