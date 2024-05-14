<?php
namespace Altitude\P21\Observer;

use Magento\Framework\Event\ObserverInterface;

class Success implements ObserverInterface
{
    protected $helper;

    protected $p21;

    protected $customerRepositoryInterface;

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Altitude\P21\Helper\Data $helper,
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->helper = $helper;
        $this->p21 = $p21;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->helper->isActive()) {
            $order = $observer->getEvent()->getOrder();
            $itemsCollection = $order->getAllItems();
            $warehouses = $this->helper->getWarehousesFromItems($itemsCollection);

            foreach ($warehouses as $whID => $items) {
                $this->sendERPOrder($order, $whID, $items);
            }
        }
    }

    public function sendERPOrder($order, $whID, $items)
    {
        $configs = $this->p21->getConfigValue([
            'apikey', 'cono', 'p21customerid', 'whse', 'shipto2erp', 'slsrepin', 'defaultterms', 'operinit',
            'transtype', 'shipviaty', 'slsrepout', 'holdifover', 'shipto2erp', 'potermscode', 'sendtoerpinv', 'orderaspo'
        ]);
        extract($configs);

        $orderincid = $order->getIncrementId();
        $orderid = $order->getId();
        $payment = $order->getPayment();
        $poNumber = $payment->getPoNumber();
        $method = $payment->getMethodInstance();
        $methodTitle = $method->getTitle();
        $methodcode = $payment->getMethod();
        $whse = $whID;

        $shipping_address = $order->getShippingAddress()->getData();
        $erpAddress = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

          try {
            $addressId = $shipping_address["customer_address_id"];
            $addressOBJ = $objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');
            $addressObject = $addressOBJ->getById($addressId);
            try {
                if (isset($addressObject)) {
                    $erpAddress = $addressObject->getCustomAttribute("ERPAddressID");//->getValue(); //->GetValue() ;
                }
            } catch (\Exception $e1) {
                $erpAddress = "";
                $this->gwLog("No erp address2 - " . json_encode($e->getMessage()));
            }
            $this->gwLog("Using shipto: " . $erpAddress . " .... ");
        } catch (\Exception $e) {
            $erpAddress = "";
            $this->gwLog("No erp address - " . json_encode($e->getMessage()));
        }
      //  $this->gwLog("Checking shipto again");
        if ( $erpAddress == ""){
            try {
                if (isset($addressObject)) {
                 //   $this->gwLog("Checking shipto again3");
                    if(null !== $addressObject->getCustomAttribute("ERPAddressID"))
                        {
                         //   $this->gwLog("Checking shipto again4");
                            $erpAddress = $addressObject->getCustomAttribute("ERPAddressID")->getValue(); //->GetValue() ;
                         //   $this->gwLog("Checking shipto again5");
                        }
                }
            } catch (\Exception $e1) {
             //   $this->gwLog("Checking shipto again4");
                $erpAddress = "";
                $this->gwLog("No erp address21 - " . json_encode($e1->getMessage()));
            }
            $this->gwLog("Using shipto:: " . $erpAddress);
        }

        $billing_address = $order->getBillingAddress()->getData();
        $total = $order->getGrandTotal();

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = 'directory_country_region';

        $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id
        $statecd = $region->getData()['code'];

        $batchnm = substr(date("YmdHi") . rand(), -8);  //set for unique bath name and ship to name
        $custno = $p21customerid;

        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        $customerData = $customerSession2->getCustomer();
        if ($order->getCustomerIsGuest()) {
            $custno = $p21customerid;
        } else {
            $CustomerID = $order->getCustomerId();

            $custno = GetP21CustNo($CustomerID);

            if (!$custno) {
                $custno = $p21customerid;
            }
        }

        if ($erpAddress == "") {
            $shipto = $batchnm;
        } else {
            $shipto = $erpAddress;
        }

        $name = $shipping_address["firstname"] . ' ' . $shipping_address["lastname"];
        $addr1 = $shipping_address["street"];
        $addr2 = '';
        $addr3 = '';
        $city = $shipping_address["city"];
        $state = $statecd;
        $zipcd = $shipping_address["postcode"];
        $countrycd = $shipping_address["country_id"];
        $phoneno = $shipping_address["telephone"];
        $faxphoneno = $shipping_address["fax"];
        $termstype = $defaultterms;
        $email = $shipping_address["email"];

        $paramsShipTo = (object) [];
        $paramsShipTo->company_id = $cono;
        $paramsShipTo->customer_id = $custno;
        $paramsShipTo->ship_to_id = $shipto;
        $paramsShipTo->name = $company;
        $paramsShipTo->phys_address1 = $addr1;
        $paramsShipTo->phys_address2 = $addr2;
        $paramsShipTo->phys_address3 = $addr3;
        $paramsShipTo->phys_city = $city;
        $paramsShipTo->phys_state = $state;
        $paramsShipTo->phys_postal_code = $zipcd;
        $paramsShipTo->mail_address1 = $addr1;
        $paramsShipTo->mail_address2 = $addr2;
        $paramsShipTo->mail_address3 = $addr3;
        $paramsShipTo->mail_city = $city;
        $paramsShipTo->mail_state = $state;
        $paramsShipTo->mail_postal_code = $zipcd;
        $paramsShipTo->central_phone_number = $phoneno;
        $paramsShipTo->default_branch = "102";
        $paramsShipTo->delete_flag = "N";
        $paramsShipTo->delivery_instructions = "";
        $paramsShipTo->phys_county = "";
        $paramsShipTo->phys_country = $countrycd;
        $paramsShipTo->email_address = $email;
        $paramsShipTo->url = "";
        $paramsShipTo->third_party_billing_flag = "S";
        $paramsShipTo->freight_cd = "FREIGHT CHARGE";
        $paramsShipTo->terms = $defaultterms;
        $paramsShipTo->primary_salesrep = $slsrepin;
        $paramsShipTo->APIKey = $apikey;
        //end salesshiptoinsert test

        //salesorderinsertUpdate
        $CompanyId = $cono;
        $CustomerId = $custno;
        $InvoiceBatchNumber = '1';
        $Salesreps = $slsrepin;
        $Terms = $termstype;
        $Taker = $slsrepin;
        $PoNo = $orderincid;
        $PackingBasis = "Partial";
        $LocationId = $whse;
        $SourceLocationId = $whse;
        $FreightCd = $shipviaty;
        $Completed = 'N';
        $Approved = 'N';
        $DeletedFlag = 'N';
        $CaptureUsage = 'Y';
        $Quote = 'N';
        $DeliveryInstructions = "";
        $ShipToId = $shipto;

        $ShipToMailAddress = $shipto;
        $ShipToName = $name;
        $ShipToAddress1 = $addr1;
        $ShipToAddress2 = $addr2;
        $ShipToAddress3 = $addr3;
        $ShipToCity = $city;
        $OeHdrShip2State = $statecd;
        $ZipCode = $zipcd;
        $ShipToCountry = $countrycd;
        $ShipToPhone = $pophoneno;
        $JobNo = "";
        //end p21 mid


        $price = 0;

        error_log("!shipto= " . $shipto);
        $paramsHead = new ArrayObject();//(object)array();
        $paramsHead[] = new SoapVar($CompanyId, XSD_STRING, null, null, 'company_id');
        $paramsHead[] = new SoapVar($CustomerId, XSD_STRING, null, null, 'customer_id');
        $paramsHead[] = new SoapVar($orderincid, XSD_STRING, null, null, 'order_no');

        $paramsHead[] = new SoapVar($InvoiceBatchNumber, XSD_STRING, null, null, 'invoice_batch_uid');
        $paramsHead[] = new SoapVar($Salesreps, XSD_STRING, null, null, 'salesrep_id');
        $paramsHead[] = new SoapVar($Terms, XSD_STRING, null, null, 'terms');
        $paramsHead[] = new SoapVar($Taker, XSD_STRING, null, null, 'taker');

        $paramsHead[] = new SoapVar($PoNo, XSD_STRING, null, null, 'po_no');
        $paramsHead[] = new SoapVar($PackingBasis, XSD_STRING, null, null, 'packing_basis');
        $paramsHead[] = new SoapVar($LocationId, XSD_STRING, null, null, 'location_id');
        $paramsHead[] = new SoapVar($SourceLocationId, XSD_STRING, null, null, 'location_id');
        $paramsHead[] = new SoapVar($FreightCd, XSD_STRING, null, null, 'freight_cd');

        $paramsHead[] = new SoapVar($Completed, XSD_STRING, null, null, 'completed');
        $paramsHead[] = new SoapVar($Approved, XSD_STRING, null, null, 'approved');

        $paramsHead[] = new SoapVar($Quote, XSD_STRING, null, null, 'projected_order');

        $paramsHead[] = new SoapVar($DeliveryInstructions, XSD_STRING, null, null, 'delivery_instructions');

        $paramsHead[] = new SoapVar($ShipToName, XSD_STRING, null, null, 'ship2_name');

        $paramsHead[] = new SoapVar($ShipToAddress1, XSD_STRING, null, null, 'ship2_add1');
        $paramsHead[] = new SoapVar($ShipToAddress2, XSD_STRING, null, null, 'ship2_add2');
        $paramsHead[] = new SoapVar($ShipToCity, XSD_STRING, null, null, 'ship2_city');
        $paramsHead[] = new SoapVar($OeHdrShip2State, XSD_STRING, null, null, 'ship2_state');
        $paramsHead[] = new SoapVar($ZipCode, XSD_STRING, null, null, 'ship2_zip');

        $paramsHead[] = new SoapVar($ShipToCountry, XSD_STRING, null, null, 'ship2_country');
        $paramsHead[] = new SoapVar($ShipToPhone, XSD_STRING, null, null, 'ship_to_phone');
		if ($erpAddress == "" && $shipto2erp == "1") {
                $paramsHead[] = new SoapVar($shipto, XSD_STRING, null, null, 'shipto');
            } elseif (isset($erpAddress)) {
                $paramsHead[] = new SoapVar($erpAddress, XSD_STRING, null, null, 'shipto');
            }
        $paramsHead[] = new SoapVar($JobNo, XSD_STRING, null, null, 'job_name');

        $lineno = 0;
        foreach ($items as $item) {
            $lineno = $lineno + 1;
            $name = $item->getName();
            $type = $this->p21->getAltitudeSKU($item); //$item->getSku();
            $id = $item->getProductId();
            $qty = $item->getQty();
            $price = $item->getPrice();
            $unit = 'ea';
            $description = $item["description"];
            $paramsDetail = new ArrayObject(); //(object)array();  //this needs to be multidimensional

            $paramsDetail[] = new SoapVar($CompanyId, XSD_STRING, null, null, 'company_id');
            $paramsDetail[] = new SoapVar($lineno, XSD_STRING, null, null, 'line_no');
            $paramsDetail[] = new SoapVar($type, XSD_STRING, null, null, 'item_id');
            $paramsDetail[] = new SoapVar($qty, XSD_STRING, null, null, 'qty_ordered');
            $paramsDetail[] = new SoapVar($price, XSD_STRING, null, null, 'unit_price');
            $paramsDetail[] = new SoapVar($whse, XSD_STRING, null, null, 'source_loc_id');
            $paramsDetail[] = new SoapVar("N", XSD_STRING, null, null, 'delete_flag');
            $paramsDetail[] = new SoapVar($unit, XSD_STRING, null, null, 'unit_of_measure');
            $paramsDetail[] = new SoapVar($description, XSD_STRING, null, null, 'extended_desc');

            $paramsHead->append(new SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'SalesOrderLinesInsertUpdateRequestContainer'));
        }

        $gcnl = $this->p21->SalesOrderInsertUpdate($paramsHead);

        try {
            if ($erpAddress == "" and 1 == 2) { //don't need to insert if it already exists
                $gcnlship = $this->p21->SalesShipToBatchInsertUpdate($paramsShipTo);
                if (isset($gcnlship["shipto"])) {
                    $shiptono = $gcnlship["shipto"];
                    if ($shiptono != "0" and $shiptono != "") {
                        if ($erpAddress == "") {
                            $addressObject->setCustomAttribute("ERPAddressID", $shiptono);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        if (is_null($gcnl)) {
            return false;
        } elseif (isset($gcnl["ordernumber"])) {
            $orderno = $gcnl["ordernumber"];
            if ($orderno != "0") {
                //update P21_OrderNo field
                $this->p21->UpdateOrderWithERPOrderNo($order, $orderno);
                return true;
            } else {
                return false;
            }

        } else {
           return false;
        }
    }
}
