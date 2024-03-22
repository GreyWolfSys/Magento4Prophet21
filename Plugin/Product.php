<?php

namespace Altitude\P21\Plugin;

class Product
{
    protected $objectManager;

    public $customerSession;

    protected $customerRepository;

    public $cid;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->kunde = $this->customerSession->isLoggedIn();
        $this->cid = $this->customerSession->getCustomerId();
        $this->objectManager = $objectManager;
    }

    public function afterGetPrice(\Magento\Catalog\Model\Product $subject, $result)
    {
        return $result;
        $debuggingflag = "true";
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // error_log ("!ip: " . $ip);
        //  error_log ("!ip: " . $ip);
        if ($ip != '10.0.71.1') {
            //   return $result;
        }
        //   error_log ("!ip: " . $ip);
        try {
            if (!empty($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $bot_identifiers = [
            'bot',
            'slurp',
            'crawler',
            'spider',
            'curl',
            'facebook',
            'fetch',
            'linkchecker',
                            'semrush',
            'xenu',
                            'google',
        ];
                // See if one of the identifiers is in the UA string.
                foreach ($bot_identifiers as $identifier) {
                    if (strpos($user_agent, $identifier) !== false) {
                        #return $result;
                    }
                }
            }
            if (empty($_SERVER['HTTP_USER_AGENT'])) {
                #return $result;
            }
        } catch (exception $e) {
        }
        if ($debuggingflag == "true") {
            $this->p21->gwLog("Starting price check product page");
        }

        if ($debuggingflag == "true") {
            $this->p21->gwLog("prod price check agent: " . $_SERVER['HTTP_USER_AGENT']);
        }
        if ($debuggingflag == "true") {
            $this->p21->gwLog("prod price check referrer: " . $_SERVER['HTTP_REFERER']);
        }

        $url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

        $id = $subject->getId();
        $sku = $subject->getSku();

        if (isset($_SESSION[$url . $sku])) {
            if ($debuggingflag == "true") {
                $this->p21->gwLog("sku/url already  " . $sku);
            }

        return $result;
        } else {
            $_SESSION[$url . $sku] = 1;
        }

        if ($debuggingflag == "true") {
            $this->p21->gwLog("prod=" . $sku);
        }
        $result = $this->calculate($result, $sku, $result, $debuggingflag);
        unset($_SESSION[$url . $sku]);

        return $result;
    }

    public function calculate($price, $sku, $result, $debuggingflag)
    {
        $sendtoerpinv = $this->p21->getConfigValue(['p21customerid', 'cono', 'whse','slsrepin','defaultterms','operinit','transtype','shipviaty','slsrepout','updateqty']);

        $newprice = $result;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        if ($debuggingflag == "true") {
            $this->p21->gwLog("!!");
        }

        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');

        // get product by product sku
        $product = $productRepository->get($sku);
        if ($debuggingflag == "true") {
            $this->p21->gwLog("name=" . $product->getName());
        }
        //$debuggingflag="true";
        $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        $product = $productRepository->get($sku);
        $productparent = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->getParentIdsByChild($product->getId());
        if (isset($productparent[0])) {
            if ($debuggingflag == "true") {
                $this->p21->gwLog("is child, skipping");
            }

            return "Select option to see price";// $result;
        }

        if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            if ($debuggingflag == "true") {
                $this->p21->gwLog("product is configurable");
            }

            return "Select option to see price";// $result;
        }

        if ($debuggingflag == "true") {
            error_log("@@@");
        }
        $singleitem = "false";
        $visibility = "";
        $request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $controller = $request->getControllerName();
        $currparent = "";
        $productparent = "";
        $singleitem = "true";
        //******************
        if ($debuggingflag == "true") {
            $this->p21->gwLog("controller=" . $controller);
        }
        if ($controller == "category" || $controller == "cart" || $controller == "section") {
            if ($debuggingflag == "true") {
                $this->p21->gwLog("..skipping for controller " . $controller);
            }

            return $result;
        } elseif ($controller != 'product') {
            try {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog("Skipping for controller " . $controller);
                }

                return $result;
            } catch (Exception $e) {
                if ($debuggingflag == "true") {
                    $this->p21->gwLog('Error ' . $e->getMessage());
                }
                $visibility = "4";
            }
        } else {
            $visibility = "1";

            $currparent = "";
            unset($productparent);
            if (isset($parentdone)) {
            } else {
                $parentdone = "|";
            }
        }
        try {
            if ($singleitem == "false") {
                if ($controller == 'product') {
                    $visibility = "4";
                } else {
                }
            }
        } catch (Exception $e) {
            if ($debuggingflag == "true") {
                $this->p21->gwLog('Error ' . $e->getMessage());
            }
        }

        //****************

       /*****************************************************/
        if ($visibility != "" && $visibility != "4" && $singleitem == "false") {
            if ($debuggingflag == "true") {
                $this->p21->gwLog("Skipping P21 price check for invis");
            }
            //$price=rand() ;
            return $result;
        } else {
            if ($this->customerSession->isLoggedIn()) {
                // Logged In
                $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
                $customerData = $customerSession2->getCustomer();

                $custno = $customerData['p21_custno'];
            } else {
                // Not Logged In
                $custno = $p21customerid;
            }

            if (empty($custno)) {
                $custno = $p21customerid;
            }

        try {
       /*     $this->p21->gwLog('getting uom1');
            $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
            $this->p21->gwLog('getting uom2');
            $productObj = $productRepository->get($prod);*/
            $this->p21->gwLog('getting uom in product.php');
    
            $uom= $product->getData('sales_uom'); //$productObj->getCustomAttribute("sales_uom")-getValue();
            $this->p21->gwLog('getting uom4');
        } catch (\Exception $e1) {
                $this->p21->gwLog($e1->getMessage());
                 $uom="EA";
        }
        if (empty($uom)) $uom="EA";
        
            try {
                if (isset($_SESSION['x' . $custno . $sku])) {
                    return $_SESSION['x' . $custno . $sku];
                } else {
                    //  $this->p21->gwLog ("doing price check");

                    // $gcnl=SalesCustomerPricingSelect($cono, $custno, $sku, "1", $whse, $whse,"ea","","","","" );
                $gcnl = $this->p21->SalesCustomerPricingSelect($cono, $custno, $sku,  $whse, $whse, "" ,"" , "", "1",$prod,"",$uom);
         

                }
            } catch (Exception $e) {
                $this->p21->gwLog('Error ' . $e->getMessage());
                $newprice = $result;
            }

            if (isset($gcnl["unit_price"])) {
                $newprice = $gcnl["unit_price"];
            } elseif (isset($_gcnl["UnitPrice"])) {
                $newprice = $_gcnl["UnitPrice"];
            }
			 if (isset($gcnl["base_price"])) {
                $listprice = $gcnl["base_price"];
            } elseif (isset($_gcnl["BasePrice"])) {
                $listprice = $_gcnl["BasePrice"];
 				 $this->p21->gwLog("listprice=" . $listprice);

				if ($listprice > 0 && false) {
                    $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
                    $_product = $productRepository->get($sku);
                    $_product->setSpecialPrice($listprice);
                    $_product->getResource()->saveAttribute($_product, 'special_price');
                    $_product->save();
                }
            } else {
                $newprice = $result;
            }

            return $newprice;
        }
       /******************************************************/
    }
}
