<?php

namespace Altitude\P21\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;

class Data extends AbstractHelper
{
    private $p21;

    public function __construct(
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->p21 = $p21;
    }

    public function getConfigData($field)
    {
        return $this->p21->getConfigValue("settings/erporders/$field");
    }

    public function isActive()
    {
        return $this->getConfigData('multip21orders');
    }

    public function useShippingCostPerWH()
    {
        return $this->getConfigData('shipping_per_wh');
    }

    public function defaultWh()
    {
        return $this->p21->getConfigValue("settings/defaults/whse");
    }

    public function getWarehousesFromItems($items) {
        $cono = $this->p21->getConfigValue('cono');

        $warehouses = $orderWhs = [];

        foreach ($items as $item) {
            $_sku = $this->p21->getAltitudeSKU($item); //$item->getSku();
            $itemAllQty = $this->p21->ItemsWarehouseProductList($cono, $_sku);

            if (isset($itemAllQty)) {
                if (!isset($itemAllQty["errordesc"])) {
                    foreach ($itemAllQty["ItemsWarehouseProductListResponseContainerItems"] as $_itemQty) {
                        $AvailQty = $_itemQty["qtyonhand"];

                        if ($AvailQty >= $item->getQty()) {
                            $warehouses[$_sku][$_itemQty["location_id"]] = [
                                'item' => $item,
                                'qty' => $AvailQty,
                                'location_id' => $_itemQty["location_id"]
                            ];
                        }
                    }
                }
            }
        }

        foreach ($warehouses as $_sku => $skuWhs) {
            $_tmpWhs = $skuWhs;
            usort($_tmpWhs, function($a, $b) {
                return $b['qty'] <=> $a['qty'];
            });

            $warehouses[$_sku] = $_tmpWhs;
        }

        foreach ($warehouses as $_sku => $skuWhs) {
            $firstWh = current(array_keys($skuWhs));
            $_wh = $skuWhs[$firstWh];

            $orderWhs[$_wh['location_id']][$_sku] = $_wh['location_id'];
        }

        return $orderWhs;
    }

    public function getWarehouses($items) {
        global $apikey, $apiurl, $p21customerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $whselist, $whsename;

        $warehouses = [];

        foreach ($items as $item) {
            $_sku = $this->p21->getAltitudeSKU($item); //$item->getSku();
            $itemAllQty = $this->p21->ItemsWarehouseProductList($cono, $_sku);

            if (isset($itemAllQty)) {
                if (!isset($itemAllQty["errordesc"])) {
                    foreach ($itemAllQty["ItemsWarehouseProductListResponseContainerItems"] as $_itemQty) {
                        $AvailQty = $_itemQty["qty_on_hand"]; # - $_itemQty["qtyreservd"] - $_itemQty["qtycommit"];

                        if ($AvailQty >= $item->getQty()) {
                            $warehouses[$_itemQty["location_id"]][$_sku] = $AvailQty;
                        }
                    }
                }
            }
        }

        return $warehouses;
    }

    public function cheapestWh($warehouses)
    {
        $wh = -1;

        foreach ($warehouses as $whID => $rates) {
            if ($wh == -1) {
                $wh = $whID;
            }

            foreach ($rates as $_rate) {
                foreach ($warehouses as $subWhID => $subRates) {
                    foreach ($subRates as $_subRate) {
                        if ($_rate->getMethod() == $_subRate->getMethod() && $_rate->getPrice() > $_subRate->getPrice()) {
                            $wh = $subWhID;
                        }
                    }
                }
            }
        }

        return $wh;
    }

    public function getWarehouseInfo($whID)
    {
        global $apikey, $apiurl, $p21customerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $whselist, $whsename;
        $wsdl_url = $apiurl . "wsdl.aspx?result=wsdl&api=ItemsWarehouseList";
        $map_url = $apiurl . "ws.aspx?result=ws&apikey=" . $apikey ."&api=ItemsWarehouseList";

        $client = new \SoapClient(
            null,
            [
                'location'=>$map_url,
                'uri'=>str_replace("&",'$amp;',$wsdl_url),
                'trace'=>1,
                'use'=> SOAP_LITERAL,
                'soap_version' => SOAP_1_2,
            ]
        );

        try {
            $params = (object)[];
            $params->cono = $cono;
            $params->brswhse = $whID;
            $params->APIKey = $apikey;
            $rootparams = (object)[];
            $rootparams->ItemsWarehouseListRequestContainer = $params;
            $result = $client->ItemsWarehouseListRequest($rootparams);

            $response = json_decode(json_encode($result));

            return $response;
        } catch (\Exception $e){}
    }

    public function magento_log($message = "")
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/erp.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }

    public function getProductImageData()
    {
        $imageData = dirname(__FILE__) . '/paid_invoice.jpg';

        return file_get_contents($imageData);
    }


    public function getModuleName()
    {
        return self::MODULE_NAME;
    }

   public function getQtyInfo($product)
    {
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'whse', 'whselist', 'whsename','apiurl','hidewhselist','zerostockmsg']);
        $hideqtyavai = $this->p21->getConfigValue('defaults/products/hideqtyavai');
        extract($configs);

        $this->p21->gwLog("Getting qty");

        if ($this->p21->botDetector()) {
            return false;
        }

        if ($product->getTypeId() != 'simple') {
            return false;
        }

        $CustWhseName = "";
        $result = "";
        $qtyAvailable = [];

        $customerSession = $this->p21->getSession();
        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            if ($customerData['p21_custno'] > 0) {
                $p21custno = $customerData['p21_custno'];
            } else {
                $p21custno = $p21customerid;

            }

            $gcCust = $this->p21->SalesCustomerSelect($cono, $p21custno);

            if (isset($gcCust["whse"])) {
                $whse = $gcCust["whse"];
                $CustWhseName = $this->p21->TrimWHSEName($gcCust["whsenm"], "-");
            }
        } else {
            $p21custno = $p21customerid;
            if ($hideqtyavai) {
                return false;
            }
        }

        $totalQty=0;

        try {
            $uom="";
            $prod = $this->p21->getAltitudeSKU($product); //$product->getSku();
          //  $this->p21->gwLog("Getting qty for sku " . $prod);
            $prodID = $product->getId();
            if (strpos($apiurl,'p21cloud') ===false  ){
                //$gcQty = $this->p21->ItemsWarehouseProductSelect($cono, $prod, $whse, '');
                if (1==1) {
               // if (isset($gcQty)) {
                   /* if ((empty($gcQty["item_id"]))) {
                        $AvailQty = 0;
                   //      $this->p21->gwLog ("!!!qty");
                    } else {
                        $AvailQty = $gcQty["qty_on_hand"] - $gcQty["qty_in_process"];
                        if (isset($gcQty["sales_pricing_unit"])) $uom=$gcQty["sales_pricing_unit"];
                        $product->setCustomAttribute("sales_uom", $uom);
                        $product->save($product);
                    }
                   
                    
                    $qtyAvailable['qty'] = $AvailQty;
                    $qtyAvailable['uom'] = $uom;*/
                    
                    $qtyAvailable['more'] = [];
					$qtyAvailable['uom'] = $uom;
                    $gcAllQty = $this->p21->ItemsWarehouseProductList($cono, $prod);
                    //$AvailQty=0;
                    if (!isset($gcAllQty["ErrorDescription"]) || $gcAllQty["ErrorDescription"] == "") {
                        if (isset($gcAllQty["ItemsWarehouseProductListResponseContainerItems"])){
                            foreach ($gcAllQty["ItemsWarehouseProductListResponseContainerItems"] as $item) {
                                
                                if ((trim($whselist) == "") || (strpos(strtoupper("," . $whselist . "," ), "," . strtoupper($item["location_id"]) . "," ) !== false) && 1==1) {
                                    if ($whsename == "1") {
                                        $showwhse = $item["name"] ;
                                    } else {
                                        $showwhse = $item["location_id"] ;
                                    }
                                    //$showwhse = $item["location_id"];
                                    $qtyAvailable['more'][] = [
                                        'whName' => $this->p21->TrimWHSEName($showwhse , "-x"),
                                        'qty' => ($item["qty_on_hand"] - $item["qty_in_process"] - $item["qty_allocated"])
                                    ];
                                    $totalQty+=($item["qty_on_hand"] - $item["qty_in_process"]- $item["qty_allocated"]);
                                    if ($whse==$item["location_id"]) {
                                        $qtyAvailable['qty']=($item["qty_on_hand"] - $item["qty_in_process"] - $item["qty_allocated"]);
                                        $AvailQty= $qtyAvailable['qty'];
                                        $qtyAvailable['uom']=$item["sales_pricing_unit"];
                                        $product->setCustomAttribute("sales_uom", $qtyAvailable['uom']);
                                        $product->save($product);
                                    }
                                } //if trim whselist
                            } //foreach
                        } else {
                            $item=$gcAllQty;
                            if ((trim($whselist) == "") || (strpos(strtoupper("," . $whselist . "," ), "," . strtoupper($item["location_id"]) . "," ) !== false) && 1==1) {
                                    if ($whsename == "1") {
                                        $showwhse = $item["name"] ;
                                    } else {
                                        $showwhse = $item["location_id"] ;
                                    }
                                    //$showwhse = $item["location_id"];
                                    $qtyAvailable['more'][] = [
                                        'whName' => $this->p21->TrimWHSEName($showwhse , "-x"),
                                        'qty' => ($item["qty_on_hand"] - $item["qty_in_process"] - $item["qty_allocated"])
                                    ];
                                    $totalQty+=($item["qty_on_hand"] - $item["qty_in_process"] - $item["qty_allocated"]);
                                    if ($whse==$item["location_id"]) {
                                        $qtyAvailable['qty']=($item["qty_on_hand"] - $item["qty_in_process"] - $item["qty_allocated"]);
                                        $AvailQty= $qtyAvailable['qty'];
                                        $qtyAvailable['uom']=$item["sales_pricing_unit"];
                                        $product->setCustomAttribute("sales_uom", $qtyAvailable['uom']);
                                        $product->save($product);
                                    }
                                } //if trim whselist
                        }
                    } //if isset gcallqty
                } else{ // if isset gcqty
                    $prod = $this->p21->getAltitudeSKU($product); //$product->getSku();
                    $prodID = $product->getId();

               //  $gcQty = $this->p21->SalesCustomerPricingSelect($cono, $p21custno, $prod, $whse, $whse,  '', '','','1',$prod);
                 $gcQty = $this->p21->SalesCustomerPricingSelect($cono, $p21custno, $prod, $whse, $whse,  "", "",  "", "1", "",  "");
                    if (isset($gcQty)) {
                        if ((empty($gcQty["item_id"]))) {
                            $AvailQty =0;
                        } else {
                            $AvailQty = $gcQty["QuantityAvailable"] ;
                        }
                    }
                }
            } else {   //p21 cloud
           // $this->p21->gwLog("checking api");
                   // $gcQty=SalesCustomerPricingSelect($cono, $p21custno, $prod, $whse, $whse, "", "", "", "", "", "" );
                    $gcQty = $this->p21->SalesCustomerPricingSelect($cono, $p21custno, $prod, $whse, $whse,  "", "",  "", "1", "",  "");
          //   $this->p21->gwLog(" done checking api, qty=" . $gcQty["QuantityAvailable"]);
                    //  $qtyonhand=$gcQty["qtyonhand"];
                    //  $qtyreservd=$gcQty["qtyreservd"];
                    //  $qtycommit=$gcQty["qtycommit"];
                        if (isset($gcQty["UnitPrice"])){
                            $newprice=$gcQty["UnitPrice"];
                            $product->setPrice($newprice);
                            $product->setFinalPrice($newprice);

                        }
                        $AvailQty=round($gcQty["QuantityAvailable"]); //$qtyonhand-$qtyreservd-$qtycommit;
                        $qtyAvailable['qty'] = $AvailQty;
                        $qtyAvailable['more'] = [];
                        $result = "";

                        $gcAllQty = $this->p21->ItemsWarehouseProductList($cono, $prod);
                            if (!isset($gcAllQty["NoRecords"])) {
                                foreach ($gcAllQty["ItemsWarehouseProductListResponseContainerItems"] as $item) {
                                    if ((trim($whselist) == "") || (strpos(strtoupper($whselist), strtoupper($item["LocationId"])) !== false)) {
                                        if ($whsename == "1") {
                                            $showwhse = $item["LocationName"];
                                        } else {
                                            $showwhse = $item["LocationId"];
                                        }
                                        $qtycount=$item["QuantityAvailable"] ;
                                        $qtyAvailable['more'][] = [
                                            'whName' => $this->p21->TrimWHSEName($showwhse, "-"),
                                            'qty' => (round($qtycount,2))
                                        ];
                                        $totalQty+=$qtycount;
                                    }
                                }
                            }
            }
          if  (!empty($qtyAvailable['more']) > 0) {
                $qtyAvailable['more'][] = [
                    'whName' => 'Total',
                    'qty' => (round($totalQty,2))
                ];
          }
          //$this->p21->gwLog ("!!!qty");
          //$this->p21->gwLog ($zerostockmsg);
          //$this->p21->gwLog ($totalQty);
         //  $this->p21->gwLog ("!!!qty");
          //'hidewhselist','zerostockmsg''
          if ($hidewhselist=='1') unset($qtyAvailable['more'] );
          //$totalQty=0;
          $qtyAvailable['qty'] =(round($totalQty,2));
          if ($zerostockmsg !='' && $totalQty <=0) {
              unset($qtyAvailable['more'] );
              $qtyAvailable['qty'] = $zerostockmsg . "";
          }

        } catch (\Exception $e) {
            $this->p21->gwLog('Error ' . $e->getMessage());
        }

        return $qtyAvailable;
    }

    public function getPriceInfo($product)
    {
        #global $apikey,$apiurl,$p21customerid,$cono,$whse,$slsrepin, $defaultterms,$operinit,$transtype,$shipviaty,$slsrepout,$updateqty,$whselist,$whsename;
    return [];
        $moduleName = $this->p21->getModuleName(get_class($this));
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'whse', 'whselist', 'whsename']);
        extract($configs);
        $qtyPricing = [];

        if ($this->p21->botDetector()) {

        }

        if ($product->getTypeId() != 'simple') {
            return [];
        }

        $customerSession = $this->p21->getSession();
        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();

            $customer = $customerSession->getCustomer();
            $cust = $customerSession->getCustomerData();

            if ($customerData['p21_custno'] > 0) {
                $p21custno = $customerData['p21_custno'];
            } else {
                $p21custno = $p21customerid;
            }

            //get P21 customer data, particularly the default warehouse
            $gcCust = $this->p21->SalesCustomerSelect($cono, $p21custno);

            if (isset($gcCust["whse"]) && $gcCust["whse"] != "") {
                $whse = $gcCust["whse"];
            }
        } else {
            $p21custno = $p21customerid;
        }

        try {
            $response = $this->p21->SalesCustomerQuantityPricingList($cono, $whse, $p21custno, $product, $moduleName);
            $formater = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);

            if (isset($response['price2']) && $response['price2'] > 0) {
                for ($i = 1; $i <= 8; $i++) {
                    if ($response['price' . $i] > 0) {
                        if ($i == 1) {
                            $qtyFrom = 0;
                        } else {
                            $qtyFrom = $response['qty' . ($i - 1)];
                        }

                        $qtyTo = $response['qty' . $i] - 1;
                        $qtyFromTo = "$qtyFrom - $qtyTo";

                        if (
                            (isset($response['qty' . ($i + 1)]) && $response['qty' . ($i + 1)] == 0) ||
                            !isset($response['qty' . ($i + 1)])
                        ) {
                            $qtyFromTo = $qtyFrom . "+";
                        }

                        $qtyPricing[] = [
                            'fromTo' => $qtyFromTo,
                            'price' => $formater->formatCurrency($response['price' . $i], "USD")
                        ];
                    }
                }
            } else {
                return [];
            }
        } catch (Exception $e) {
            return [];
        }

        return $qtyPricing;
    }


    public function isLoggedIn()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        if ($customerSession->isLoggedIn()) {
            return true;
        }

        return false;
    }

    public function getCustomer()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        return $customerSession->getCustomer();
    }

    public function getDefaultShipVia()
    {
        return "";//$this->getConfigValue('default_erpshipvia');
    }

    public function getDefaultShipViaDesc()
    {
        return "";//$this->getConfigValue('default_erpshipviadesc');
    }

    public function getConfigValue($configName)
    {
        return $this->p21->getConfigValue($configName);
    }

    public function getShippingNotice()
    {
        return $this->getConfigValue('shipping_notice');
    }

    public function sendAddressToERP()
    {
        return $this->p21->getConfigValue('address_to_erp');
    }

    public function isAbleToEditAddress()
    {
        $result= 0;
        try{
            $this->p21->getConfigValue('allow_edit_address');

        } catch (Exception $e) {
            $result= 0;
        }
        return $result;
    }

    public function getUpchargeShipping()
    {
        $methods = [];

        $configShippingMethods = $this->getShippingUpchargeConfigValue('shipping_methods');

        if ($configShippingMethods && is_object(json_decode($configShippingMethods))) {
            foreach (json_decode($configShippingMethods) as $_method) {
                $methods[] = $_method->shippingtitle;
            }
        }

        return $methods;
    }

    public function getUpchargeLabel()
    {
        return $this->getShippingUpchargeConfigValue('upcharge_label');
    }

    public function getUpchargePayment()
    {
        return $this->getShippingUpchargeConfigValue('payment_method');
    }

    public function getUpchargePercent()
    {
        $upchargePercent = $this->getShippingUpchargeConfigValue('upcharge_percent');
        if (!empty($upchargePercent)){
            return str_replace("%", "", $upchargePercent);
        } else {
            return 0;
        }
    }

    public function getUpchargeWaiveAmount()
    {
      
        return $this->getShippingUpchargeConfigValue('waive_amount');
    }

    public function getShippingUpchargeConfigValue($configName)
    {
        return $this->p21->getConfigValue( $configName );
    }

    public function getUpchargeAmount($quote)
    {
        if ($quote->getPayment() && $quote->getSubtotal()) {
            $objectManager = ObjectManager::getInstance();
            $upchargeTotal = $objectManager->create('Altitude\P21\Model\Total\UpchargeTotal');
			error_log ("upcharge 5: data.php getupchargeamount");
            return $upchargeTotal->getUpchargeAmount($quote);
        } else {
            return 0;
        }
    }
    public function getUpchargePostTax()
    {
        return $this->getShippingUpchargeConfigValue('posttax');
    }
}
