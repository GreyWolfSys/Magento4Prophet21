<?php

namespace Altitude\P21\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use SoapVar;

class GetP21Price implements ObserverInterface
{
    protected $p21;

    protected $request;

    protected $_addressFactory;

    public function __construct(
        \Altitude\P21\Model\P21 $p21,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        $this->p21 = $p21;
        $this->addressFactory = $addressFactory;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->p21->botDetector()) {
            return "";
        }
       
//session_unset();
        $moduleName = $this->p21->getModuleName(get_class($this));
        $configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse', 'onlycheckproduct','localpriceonly']);
        extract($configs);

        $url = $this->p21->urlInterface()->getCurrentUrl();
        $ip = $this->remoteAddress->getRemoteAddress();
        $displayText = $observer->getEvent()->getName();
        $controller = $this->request->getControllerName();
        $skipAPI=false;
         $this->p21->gwLog("xcontroller: " . $controller);
        $singleitem = "true";
        $shipto = "";
        $custno = 0;
        $products = $productsCollection = [];

        $debuggingflag = "true";
      //  $debuggingflag = "false";

        if ($this->p21->getSession()->getApidown()) {
            $apidown = $this->p21->getSession()->getApidown();
        } else {
            $apidown = false;
        }
        $apidown = false;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        if ($debuggingflag == "true") {
            $this->p21->gwLog("url: " . $url);
            $this->p21->gwLog("ip:: " . $ip);
        }

        try {
            $singleProduct = $observer->getEvent()->getProduct();
            if (is_null($singleProduct)) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Item Collection");
                }

                $productsCollection = $observer->getCollection();
                $singleitem = "false";
            } else {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Single Item");
                }
                $products = [];
                $productsCollection[] = $singleProduct;
                $singleitem = "true";
            }
        } catch (exception $e) {
        }

        $customerSession = $this->p21->getSession();
        if ($customerSession->isLoggedIn() || (strpos($url, '/rest/') !== false)) {
            // Logged In
            if ($debuggingflag == "true") {
                $this->p21->gwLog(" logged in");
            }

            $customerData = $customerSession->getCustomer();
            $custno = $customerData['p21_custno'];

            if ($debuggingflag == "true") {
                $this->p21->gwLog("cust= " . $custno);
            }

            $shippingAddressId = $customerData['default_shipping'];
            $shippingAddress = $this->addressFactory->create()->load($shippingAddressId);

            if ($shippingAddress->getData('ERPAddressID') != "") {
                $shipto = "";
            }
        } else {
            // Not Logged In
            $custno = $p21customerid;
        }

        if (empty($custno)) {
            $custno = $p21customerid;
        }

        if ($debuggingflag == "true") {
            $this->p21->gwLog("Product retrieved");
        }

        if ($this->p21->df_is_admin()) {
            $admin = true;
        } else {
            $admin = false;
        }

        if ($debuggingflag == "true") {
            $this->p21->gwLog("admin = " . $admin);
        }

         $bSkip = '';
        $params = new \ArrayObject();
        $thisparam= array(
            'company_id' => $cono, 'customer_id'=>$custno, 'sales_location_id'=>$whse,'ship_to_id'=>"",'APIKey'=>$apikey,'source_location_id'=>$whse
        );
        $params[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);
        
      /*  $params[] = new \SoapVar($cono, XSD_STRING, null, null, 'company_id');
        $params[] = new \SoapVar($custno, XSD_STRING, null, null, 'customer_id');
        $params[] = new \SoapVar($whse, XSD_STRING, null, null, 'sales_location_id');
        $params[] = new \SoapVar("", XSD_STRING, null, null, 'ship_to_id');

        #$params[] = new \SoapVar("1", XSD_STRING, null, null, 'oe_qty_ordered');
        $params[] = new \SoapVar($apikey, XSD_STRING, null, null, 'APIKey');

        if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') !==false   ){
             $params[] = new \SoapVar("", XSD_STRING, null, null, 'order_date');
             $params[] = new \SoapVar("", XSD_STRING, null, null, 'job_no');
        } else {
            $params[] = new \SoapVar($whse, XSD_STRING, null, null, 'source_location_id');
        }*/

        foreach ($productsCollection as $product) {
            $price = 0;
            $visibility = "";
            
            $prod = $this->p21->getAltitudeSKU($product);
            $products[$prod] = $product;

            $price = $product->getPrice();

        if (strpos($url, 'checkout') === false && strpos($url, 'cart') === false && 1==2) { //cart
            if (isset($_SESSION[$url . $prod . "price"] ) && 1==2) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("prod/url already  " . $url . $prod);
                }
                $price=$_SESSION[$url . $prod . "price"] ;
                if (isset($_SESSION[$url . $prod . "listprice"] )) {
                    $listprice=$_SESSION[$url . $prod . "listprice"];
                } else {
                    $listprice=0;
                }
                $product->setPrice($price);
                $product->setFinalPrice($price);
                if ($listprice > 0 && false) {
                    $product->setSpecialPrice($listprice);
                } else {
                    $product->setSpecialPrice(null);
                }
                $skipAPI=true;
                continue;
            } else {
                //$_SESSION[$url . $prod] = 1;

            }
       }

            if ($debuggingflag == "true") {
                $this->p21->gwLog("product sku: $prod");
                $this->p21->gwLog("product price: $price");
            }
            if ($localpriceonly=="Magento") {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("skip price for local price only setting");
                }
                $bSkip = 'true';
            }
            if ($controller != "product" && $controller != "block" && strpos($url, 'cart') == false && $controller != "order" && $controller != "order_create") {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("controllervar=" . $onlycheckproduct);
                }
                if ($onlycheckproduct == "1" ) {
                    if ($debuggingflag == "true") {
                        $this->p21->gwLog("skip price for non-product page!");
                    }
                    $bSkip = 'true';
                }
            }

            if ($debuggingflag == "true") {
                $this->p21->gwLog('Product: ' . $prod . ' - Magento Price: ' . $price);
            }

            $currparent = "";
            unset($productparent);

            if (!isset($parentdone)) {
                $parentdone = "|";
            }

            if ($debuggingflag == "true") {
                $this->p21->gwLog("Child check" . $parentdone);
            }
            try {
                $productparent = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                if (isset($productparent[0])) {
                    $currparent = $productparent[0];
                }
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog('Error ' . $e->getMessage());
                }
            }

            if ($debuggingflag == "true") {
                $this->p21->gwLog("controller: " . $controller . " -- singleitem: " . $singleitem . " -- currparent: " . $currparent . " -- isset:" . isset($currparent));
            }
            if ($controller != 'product') {
                try {
                    if ($currparent == "" && $singleitem == "false" && isset($productparent[0])) {
                        if ($debuggingflag == "true") {
                            $this->p21->gwLog("Skipping P21 price check for parent of collection");
                        }
                        $visibility = "0";
                    } else {
                        if ($debuggingflag == "true") {
                            $this->p21->gwLog("Setting vis by prev run");
                        }
                        if (isset($productparent[0])) {
                            if (strpos($parentdone, "|" . $productparent[0] . "|") !== false) {
                                $visibility = "0";
                                if ($debuggingflag == "true") {
                                    $this->p21->gwLog("hiding");
                                }
                            } else {
                                if ($debuggingflag == "true") {
                                    $this->p21->gwLog("not hiding");
                                }
                                $visibility = "4";
                            }
                        } else {
                            $visibility = "4";
                        }
                    }
                } catch (Exception $e) {
                    if ($debuggingflag == "true") {
                        $this->p21->gwLog('Error ' . $e->getMessage());
                    }
                    $visibility = "4";
                }
            }

            try {
                if ($singleitem == "false") {
                    if ($controller == 'product') {
                        if (isset($productparent[0])) {
                            if ($debuggingflag == "true") {
                                $this->p21->gwLog("skipping " . $prod);
                            }
                            $visibility = "0";
                        } else {
                            if ($debuggingflag == "true") {
                                $this->p21->gwLog("checking  " . $prod);
                            }
                            $visibility = "4";
                        }
                    } else {
                    }
                }
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog('Error ' . $e->getMessage());
                }
            }

            if (strpos($url, 'cart') !== false) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("checking for cart  " . $prod);
                }
                $visibility = "4";
            }

			 $this->p21->gwLog("Checking url for cart: " . $url);

                 if (strpos($url, 'checkout') === false && strpos($url, 'cart') === false ) { //cart
                 }else {
                     $this->p21->gwLog("Checkout, checking price");
                     $controller="cart";
                     $bSkip = 'false';
                 }

            $this->p21->getSession()->setApidown(false);
            $apidown = $this->p21->getSession()->getApidown();
            $pagestate =  $objectManager->get('Magento\Framework\App\State');

            if (strpos($url, 'admin') !== false || strpos($url, '/catalog/product/index/key/') !== false || $admin == true || strpos($url, '/product/index/key') !== false || $pagestate->getAreaCode()=='adminhtml' ) { //
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping P21 price check for admin");
                }

                return "";
            } elseif ($apidown == true || $bSkip == 'true') {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping P21 price check for apidown or non-product page" . ($apidown));
                }
                if ($localpriceonly=="Hybrid") {
                    return $price;
                } else{
                    return "";
                }
                return "";
            } elseif ($visibility != "" && $visibility != "4" && $singleitem == "false") {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping P21 price check for invis");
                }

                return "";
            } elseif ($controller == "product" && $singleitem == "false") {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping P21 price check for prod...");
                }

                return "";
            } elseif ($currparent !== "" && $controller !== "cart" && $singleitem == "false") {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping P21 price check for child item");
                }

                return "";
            } else {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Price check continues...");
                    $this->p21->gwLog("launching api");
                }
            }

        try {
          
            $this->p21->gwLog('getting uom in getp21price.php');
            $productRepository1 = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
            $this->p21->gwLog('getting uom2');
            $productObj = $productRepository1->get($prod);
            $this->p21->gwLog('getting uom3');
    
            $uom= $productObj->getData('sales_uom'); //$productObj->getCustomAttribute("sales_uom")-getValue();
    
           // $uom= $product->getData('sales_uom'); //$productObj->getCustomAttribute("sales_uom")-getValue();
            $this->p21->gwLog('getting uom4' . $uom);
        } catch (\Exception $e1) {
                $this->p21->gwLog("E!!!" . $e1->getMessage());
                 $uom="EA";
        }
        
        if (empty($uom)) $uom="ea";
       $this->p21->gwLog('uom=' . $uom);
            $productParams = new \ArrayObject();
             if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') !==false   ){
                 $gcuom=$this->p21->ItemsProductSelect($prod);
                 if (isset($gcuom)){
                    $uom=$gcuom["SalesPricingUnit"];

                 } else {
                     $uom="sf";
                 }
                $productParams[] = new \SoapVar($prod, XSD_STRING, null, null, 'item_id');
                $productParams[] = new \SoapVar($whse, XSD_STRING, null, null, 'source_location_id');
                $productParams[] = new \SoapVar('', XSD_STRING, null, null, 'unit_size');
                $productParams[] = new \SoapVar('1', XSD_STRING, null, null, 'unit_quantity');
                $productParams[] = new \SoapVar('', XSD_STRING, null, null, 'customer_part_no');
                $productParams[] = new \SoapVar($uom, XSD_STRING, null, null, 'uom');
            } else {
                $productParams[] = new \SoapVar(array('customer_part_no' => $prod), SOAP_ENC_OBJECT);
                $productParams[] = new \SoapVar(array('oe_qty_ordered' => 1), SOAP_ENC_OBJECT);
                $productParams[] = new \SoapVar(array('pricing_unit' => $uom), SOAP_ENC_OBJECT);
                $productParams[] = new \SoapVar(array('sales_unit' => $uom), SOAP_ENC_OBJECT);
              /*  $productParams[] = new \SoapVar('1', XSD_STRING, null, null, 'oe_qty_ordered');
                $productParams[] = new \SoapVar($uom, XSD_STRING, null, null, 'pricing_unit');
                $productParams[] = new \SoapVar($uom, XSD_STRING, null, null, 'sales_unit');
                 $thisparamLine= array(
                    'customer_part_no' => $prod, 'oe_qty_ordered'=>'1', 'pricing_unit'=>$uom,'sales_unit'=>$uom
                );*/
            }
  $thisparamLines= array(   'SalesCustomerPricingListLinesRequestContainer' => $productParams->getArrayCopy()               );
       // $params[] = new \SoapVar($thisparamLines, SOAP_ENC_OBJECT);
        
            $params->append(new SoapVar(
               // array(   'SalesCustomerPricingListLinesRequestContainer' => (array)$productParams               ),
               $thisparamLines,
                SOAP_ENC_OBJECT,
                null,
                null,
                'SalesCustomerPricingListLinesRequestContainer1'
            ));
            
                  /*  $thisparam= array(
                    'company_id' => $cono, 'customer_id'=>$custno, 'sales_location_id'=>$whse,'ship_to_id'=>"",'APIKey'=>$apikey,'source_location_id'=>$whse
                );
        $params[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);*/
        }

    if ($skipAPI) return "";
        try {
            if ($currparent . "" != "") {
                $parentdone .= "|" . $currparent . "|";
            }
            if ($debuggingflag == "true") {
                $this->p21->gwLog("selprod=" . $prod);
            }

            $apiname = "SalesCustomerPricingList";
            $client = $this->p21->createSoapClient($apikey, $apiname);

            $rootparams = (object) [];
            $rootparams->SalesCustomerPricingListRequestContainer = $params->getArrayCopy();
/*ob_start();
var_dump($rootparams);
$result1 = ob_get_clean();
$this->p21->gwLog($result1);
*/
            $result = $client->SalesCustomerPricingListRequest($rootparams);
            $gcnl = json_decode(json_encode($result), true);
//$this->p21->gwLog("REQUEST:\n" . $client->__getLastRequest() . "");
        } catch (Exception $e) {
            $this->p21->gwLog('Error ' . $e->getMessage());
            $this->p21->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }

        $this->p21->gwLog("Processing data...");

        $newprice = 0;

        try {
            if (!isset($gcnl)) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("error from pricing: apidown");
                }

                $this->p21->getSession()->setApidown(true);
                $apidown = $this->p21->getSession()->getApidown();
                if ($localpriceonly=="Hybrid") {
                    $newprice = $price;
                } else{
                    $newprice = 0;
                }
            }

            if (isset($gcnl["fault"])) {
                if ($localpriceonly=="Hybrid") {
                    $newprice = $price;
                } else{
                    $newprice = 0;
                }
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("error from pricing: " . $gcnl["fault"]);
                }

                $this->p21->getSession()->setApidown(true);
                $apidown = $this->p21->getSession()->getApidown();

                if ($debuggingflag == "true") {
                    $this->p21->gwLog("API error: " . $gcnl["fault"]);
                }
            }
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage());
        }

        try {
            $listprice = null;

            if (isset($gcnl["SalesCustomerPricingListResponseContainerItems"])) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("multi item");
                }

                foreach ($gcnl["SalesCustomerPricingListResponseContainerItems"] as $_gcnl) {
                    $erpproduct='';
                    if (isset($_gcnl["product"]))  $erpproduct=$_gcnl["product"];
                    elseif (isset($_gcnl["ItemId"]))  $erpproduct=$_gcnl["ItemId"];
                     elseif (isset($gcnl["item_id"]))  $erpproduct=$gcnl["item_id"];
                     
                    if ($erpproduct != "") {

                        $product = $products[$_gcnl["product"]];
                        foreach ($productsCollection as $product) {
                            $this->p21->gwLog("multi item:: " . $product["product"]);
                            $price = $product->getPrice();
                            $prod = $this->p21->getAltitudeSKU($product) ;

                                if ($prod==$erpproduct)   {
                                    if ($debuggingflag == "true") {
                                        $this->p21->gwLog("Product matches for " . $prod);
                                    }

                                    if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') ===false   ) {
                                        $price = $_gcnl["unit_price"];
                                    } else {
                                         $price = $_gcnl["UnitPrice"];
                                    }
                                   if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') ===false   ) {
                                         if (isset($_gcnl["base_price"])) {
                                            $listprice = $_gcnl["base_price"];
                                       } else {
                                           $listprice =$price;
                                       }
                                    } else {
                                        $listprice = $_gcnl["BaseUnitPrice"];
                                    }
                                    if ($price==0 && $localpriceonly=="Hybrid") {
                                        $price = $product->getPrice();
                                    } 
                                    $product->setPrice($price);
                                    $product->setFinalPrice($price);
                                    $_SESSION[$url . $prod . "price"] = $price;

                                    if ($listprice > 0 && false) {
                                        $product->setSpecialPrice($listprice);
                                        $_SESSION[$url . $prod . "listprice"] = $listprice;
                                    } else {
                                        $product->setSpecialPrice(null);
                                    }

                                    $message = $prod . " Before: " . $price . " After: " . $product->getData('final_price');
                                    if ($debuggingflag == "true") {
                                        $this->p21->gwLog($message);
                                    }
                                }

                        }
                    }
                }
            } elseif (isset($gcnl["product"]) || isset($gcnl["ItemId"]) || isset($gcnl["item_id"]) ) {
                $erpproduct='';

                if (isset($gcnl["product"]))  $erpproduct=$gcnl["product"];
                elseif (isset($gcnl["ItemId"]))  $erpproduct=$gcnl["ItemId"];
                elseif (isset($gcnl["item_id"]))  $erpproduct=$gcnl["item_id"];
                
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("single item " . $erpproduct);
                }

                try {
                    $product = $products[$erpproduct];
                    $price = $product->getPrice();
                    $prod = $this->p21->getAltitudeSKU($product) ;
                    if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') ===false   ) {
                        $price = $gcnl["unit_price"];
                    } else {
                        $price = $gcnl["UnitPrice"];
                    }

                    //$price=2.17;
                    if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') ===false   ) {
                        $listprice = $gcnl["base_price"];
                    }  else {
                        $listprice = $gcnl["BaseUnitPrice"];
                    }
                    if ($price==0 && $localpriceonly=="Hybrid") {
                        $price = $product->getPrice();
                    } 
                    $product->setPrice($price);
                    $product->setFinalPrice($price);
                    $_SESSION[$url . $prod . "price"] = $price;

                    if ($listprice > 0 && false) {
                       // $product->setSpecialPrice($listprice);
                        $_SESSION[$url . $prod . "listprice"] = $listprice;
                    } else {
                        $product->setSpecialPrice(null);
                    }
                    $this->p21->gwLog("ERP price: " . $price);

                    $this->p21->gwLog("Mag price: " . $price);

                    $message = $prod . " !!Before: " . $price . " After: " . $product->getData('final_price');
                    if ($debuggingflag == "true") {
                        $this->p21->gwLog($message);
                    }
                } catch (\Exception $e1) {
                    $this->p21->gwLog($e1->getMessage());
                }
            } else {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("not set!!");
                }
            }
        } catch (Exception $e) {
            $this->p21->gwLog($e->getMessage());
            if ($debuggingflag == "true") {
                $this->p21->gwLog('Error ' . $e->getMessage());
            }
        }

        return true;
    }
}
