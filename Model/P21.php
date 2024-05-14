<?php

namespace Altitude\P21\Model;

use \SoapVar;
use \ArrayObject;
use \PDO;

class P21 extends \Magento\Framework\Model\AbstractModel
{
    protected $httpHeader;

    protected $urlInterface;

    protected $customerSession;

    protected $scopeConfig;

    protected $state;

    protected $resourceConnection;

    protected $customerRepositoryInterface;

    public function __construct(
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->httpHeader = $httpHeader;
        $this->urlInterface = $urlInterface;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->customerSession = $customerSession;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->resourceConnection = $resourceConnection;
    }

    public function configMapping($key = "")
    {
        $mapping = [
            'apikey' => 'connectivity/webservices/apikey',
            'apiurl' => 'connectivity/webservices/apiurl',

            'p21customerid' => 'defaults/gwcustomer/erpcustomerid',
            'cono' => 'defaults/gwcustomer/cono',
            'whse' => 'defaults/gwcustomer/whse',
            'shipviaty' => 'defaults/gwcustomer/shipviaty',
            'address_to_erp' => 'defaults/gwcustomer/address_to_erp',
            'taker' => 'defaults/shoppingcart/taker',
            'slsrepin' => 'defaults/shoppingcart/slsrepin',
            'slsrepout' => 'defaults/shoppingcart/slsrepout',
            'defaultterms' => 'defaults/shoppingcart/defaultterms',
            'operinit' => 'defaults/shoppingcart/operinit',
            'transtype' => 'defaults/shoppingcart/transtype',
            'autoinvoice' => 'defaults/shoppingcart/autoinvoice',
            'sendtoerpinv' => 'defaults/shoppingcart/sendtoerpinv',
            'hidepmt' => 'defaults/shoppingcart/hidepmt',
            'holdifover' => 'defaults/shoppingcart/holdifover',
            'credit_status'=> 'defaults/shoppingcart/credit_status',
            'price_library'=> 'defaults/shoppingcart/price_library',
            'whselist' => 'defaults/products/whselist',
            'whsename' => 'defaults/products/whsename',
            'hidewhselist' => 'defaults/products/hidewhselist',
            'zerostockmsg' => 'defaults/products/zerostockmsg',
            'altitemidfield' => 'defaults/products/altitemidfield',
            'localpriceonly' => 'defaults/products/local_price_only',
            
        //    'maxrecall' => 'connectivity/maxrecall/maxrecall',
        //    'maxrecalluid' => 'connectivity/maxrecall/maxrecalluid',
        //    'maxrecallpwd' => 'connectivity/maxrecall/maxrecallpwd',

            'send_as_item'=>'shipping_upcharge/general/send_as_item',
            'invstartdate' => 'defaults/display/invstartdate',

            'orderaspo' => 'defaults/misc/orderaspo',
            'updateqty' => 'defaults/misc/updateqty',
            'onlycheckproduct' => 'defaults/misc/onlycheckproduct',
            'potermscode' => 'defaults/misc/potermscode',

            'cenposuid' => "",
            'cenpospwd' => "",
            'cenposmerchid' => "",

            'shipping_methods' => "shipping_upcharge/general/shipping_methods",
            'upcharge_label' => "shipping_upcharge/general/upcharge_label",
            'payment_method' => "shipping_upcharge/general/payment_method",
            'upcharge_percent' => "shipping_upcharge/general/upcharge_percent",
            'waive_amount' => "shipping_upcharge/general/waive_amount"
        ];

        if ($key != "" && isset($mapping[$key])) {
            return $mapping[$key];
        } elseif (strpos($key, '/') != false) {
            return $key;
        } else {
            return $mapping;
        }
    }

    // // Sorts array or items in ASC
    public function array_sort_by_column($arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        if ($dir=="desc") {
                $dir=SORT_DESC ;
            }else {
                $dir=SORT_ASC;
            }
        if ($col=="OrderDate") $col="order_date";
        if (str_contains($col,"dt") || str_contains($col,"date")){
            foreach ($arr as $key => $part) {
                if (isset($part[$col]))      {
                    $sort_col[$key] = strtotime($part[$col]);
                } else {
                  // unset($arr[$key]);
                   $sort_col[$key]="na";
                }
              }
        } else {
            foreach ($arr as $key=> $row) {
                $sort_col[$key] = $row[$col];
               if (isset($row[$col]))      {
                    $sort_col[$key] = $row[$col];
                } else {
                  // unset($arr[$key]);
                   $sort_col[$key]="na";
                }
            }
        }
        array_multisort($sort_col, $dir, $arr);
        return $arr;
    }
    
    
    public function getAllConfigValues()
    {
        $configValues = [];

        foreach ($this->configMapping() as $_config => $_configPath) {
            if (strpos($_config, "cenpos") !== false) {
                $configValues[$_config] = $_configPath;
            } else {
                $configValues[$_config] = $this->scopeConfig->getValue($_configPath);
            }
        }

        return $configValues;
    }

    public function getConfigValue($configName)
    {
        if (is_array($configName)) {
            $configs = [];
            foreach ($configName as $_config) {
                $configPath = $this->configMapping($_config);
                $_configKey = $_config;

                if (strpos($_config, "/") !== false) {
                    $_tmp = explode("/", $_config);
                    $_configKey = last($_tmp);
                }

                if (strpos($_config, "cenpos") !== false) {
                    $configs[$_configKey] = $configPath;
                } else {
                    $configs[$_configKey] = $this->scopeConfig->getValue($configPath);
                }
            }

            return $configs;
        } else {
            $configPath = $this->configMapping($configName);

            if (is_array($configPath)) {
                return null;
            } elseif (strpos($configName, "cenpos") !== false) {
                return $configPath;
            } else {
                return $this->scopeConfig->getValue($configPath);
            }
        }
    }

    public function LogAPICall($apiname, $moduleName = "")
    {
        try {
            $agent = $this->httpHeader->getHttpUserAgent();
            $url = $this->urlInterface->getCurrentUrl();
        } catch (\Exception $e) {
            $agent = "";
            $url = "";
        }

        if ($moduleName) {
            $this->gwLog("$moduleName: API: " . $apiname . "; agent: " . $agent . "; url: " . $url);
        } else {
            $this->gwLog("API: " . $apiname . "; agent: " . $agent . "; url: " . $url);
        }
    }

    public function urlInterface()
    {
        return $this->urlInterface;
    }

    public function gwLog($trace="", $message = "")
    {
        if ($message=="") {
            $message=$trace;
        }else{
            $message= $trace . $message;
        }
        
        $debugEnabled = "1";//$this->scopeConfig->getValue('defaults/misc/debugenabled');

        if ($message != "" && $debugEnabled==1) {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/altitude.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            if (is_string($message)) {
                $logger->info($message);
            } else {
                $logger->info(json_encode($message));
            }
        }
    }

    public function getWsdlMapUrl($apikey, $apiName)
    {
        $apiUrl = $this->getConfigValue('apiurl');

        return [
            'wsdlUrl' => $apiUrl . "wsdl.aspx?result=wsdl&apikey=$apikey&api=$apiName",
            'mapUrl' => $apiUrl . "ws.aspx?result=ws&apikey=$apikey&api=$apiName"
        ];
    }

    public function createSoapClient($apikey, $apiName)
    {
        $getWsdlMapUrl = $this->getWsdlMapUrl($apikey, $apiName);
        $this->gwLog($getWsdlMapUrl['wsdlUrl']);
        try {
            $client = new \SoapClient(
            null,
            [
                'location' => $getWsdlMapUrl['mapUrl'],
                'uri' => str_replace("&", '$amp;', $getWsdlMapUrl['wsdlUrl']),
                'trace' => 1,
                'use' => SOAP_LITERAL,
                'soap_version' => SOAP_1_2,
                'connection_timeout' => 1,
                ]
            );

            return $client;
        } catch (\Exception $e) {
        }

        return false;
    }

    public function df_is_admin()
    {
        return 'adminhtml' === $this->state->getAreaCode();
    }

    public function TrimWHSEName($name, $trimchar)
    {
        if (strpos($name, $trimchar) !== false) {
            $name = strstr($name, $trimchar, true);
        }

        return $name;
    }

    public function makeRESTRequest($map_url, $request, $username, $password)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $map_url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "".$xmlrequest);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $content = curl_exec($ch);

        if (curl_errno($ch)) {
            return "";
            print curl_error($ch);
        } else {
            curl_close($ch);
        }

        return $content;
    }

    public function botDetector()
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();

        if (!empty($userAgent)) {
            $userAgent = strtolower($userAgent);
            $botIdentifiers = [
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
            foreach ($botIdentifiers as $_bot) {
                if (strpos($userAgent, $_bot) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getModuleName($className)
    {
        $module = explode("\\", $className);

        if (isset($module[1])) {
            return "GWS_" . $module[1];
        } else {
            return $className;
        }
    }

    public function getSession()
    {
        return $this->customerSession;
    }

    public function getAltitudeSKU($product)
    {

        $configs = $this->getConfigValue([
            'altitemidfield'
        ]);
        extract($configs);
       

        
        if ($this->df_is_admin()) {
            $sku = $product->getSku();
        }
        elseif ($altitemidfield=="1") {
            $sku = $product->getErpItemId();
            if (empty($sku))
            {
                $sku = $product->getSku();
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
                $product = $productRepository->get($sku);
                $sku = $product->getData('erp_item_id');
            }
            if (empty($sku))
                $sku = $product->getSku();
        } else {
            $sku = $product->getSku();
        }
        
        return $sku;
      
    }
function SalesCreditCardAuthInsert($cono, $orderno, $ordersuf, $amount, $authamt, $bankno, $charmediaauth,$cmm, $commcd, $createdt, $currproc, $mediaauth,$mediacd,$origamt,$origproccd,$preauthno,$processcd,$processno,$respdt,$response,$saleamt,$statustype,$submitdt,$transcd,$transdt,$user1,$user2,$user3,$user4,$user5,$user6,$user7,$user8,$user9){
	global $apikey,$apiurl;
	$wsdl_url=$apiurl . "wsdl.aspx?result=wsdl&apikey=" . $apikey . "&api=SalesCreditCardAuthInsert";
	$map_url=$apiurl . "ws.aspx?result=ws&apikey=" . $apikey . "&api=SalesCreditCardAuthInsert";
	//error_log ($map_url,0);

	try{
	$client=new SoapClient(
		null,
		array(
			'location'=>$map_url,
			'uri'=>str_replace("&",'$amp;',$wsdl_url),
			'trace'=>1,
			'use'=> SOAP_LITERAL,
			'soap_version' => SOAP_1_2,
			)
		);

	$params1=(object)array();
    $params1->cono = $cono;
    $params1->orderno = $orderno;
    $params1->ordersuf = $ordersuf;
    $params1->amount = $amount;
    $params1->authamt = $authamt;
    $params1->bankno = $bankno;
    $params1->charmediaauth = $charmediaauth;
    $params1->cmm = $cmm;
    $params1->commcd = $commcd;
    $params1->createdt = $createdt;
    $params1->currproc = $currproc;
    $params1->mediaauth = $mediaauth;
    $params1->mediacd = $mediacd;
    $params1->origamt = $origamt;
    $params1->origproccd = $origproccd;
    $params1->preauthno = $preauthno;
    $params1->processcd = $processcd;
    $params1->processno = $processno;
    $params1->respdt = $respdt;
    $params1->response = $response;
    $params1->saleamt = $saleamt;
    $params1->statustype = $statustype;
    $params1->submitdt = $submitdt;
    $params1->transcd = $transcd;
    $params1->transdt = $transdt;
    $params1->user1 = $user1;
    $params1->user2 = $user2;
    $params1->user3 = $user3;
    $params1->user4 = $user4;
    $params1->user5 = $user5;
    $params1->user6 = $user6;
    $params1->user7 = $user7;
    $params1->user8 = $user8;
    $params1->user9 = $user9;

	$params1->APIKey = $apikey;
	$rootparams=(object)array();
	$rootparams ->SalesCreditCardAuthInsertRequestContainer = $params1;
	$result=(object)array();
}
	catch (\Exception $e){
		error_log  ('Caught exception: ' .  $e->getMessage());
		error_log  ("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
	}
	try{
		//error_log ("attempting contact",0);
		$result = $client->SalesCreditCardAuthInsertRequest($rootparams);
	}
	catch (\Exception $e){
		error_log  ('Caught exception: ' .  $e->getMessage());
		error_log  ("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
	}
	//error_log ("contact complete",0);
	try {
	//error_log("Result length: " . strlen($result));
	$response=  json_decode(json_encode($result), true);
	return $response;//["SOAP:ENVELOPE"]["SOAP:BODY"]["SALESCUSTOMERPRICINGSELECTRESPONSECONTAINERLIST"]["SALESCUSTOMERPRICINGSELECTRESPONSECONTAINERLISTITEMS"];
	}
	catch (\Exception $e){
		error_log  ('Caught exception: ',  $e->getMessage(), "");
		error_log  ("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
	}

}
    public function SalesCustomerSelect($company_id, $customer_id)
    {
        $apiname = "SalesCustomerSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->customer_id = $customer_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesCustomerSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesCustomerSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }
        public function SalesCustomerDefaultSelect($company_id)
    {
        $apiname = "SalesCustomerDefaultSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesCustomerDefaultSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesCustomerDefaultSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }
    public function ItemsInventorySupplierList($supplier_id, $division_id,$location_id,$item_id)
    {
        //supplier_id=&division_id=&location_id=100&item_id=TBC-SSC019
        $apiname = "ItemsInventorySupplierList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->supplier_id = $supplier_id;
        $params1->division_id = $division_id;
        $params1->location_id = $location_id;
        $params1->item_id = $item_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->ItemsInventorySupplierListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->ItemsInventorySupplierListRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }
    public function SalesShipToList($company_id, $customer_id)
    {//no cloud variation required
        $apiname = "SalesShipToList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

$this->gwLog("cono: " . $company_id);
$this->gwLog("cust: " . $company_id);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->customer_id = $customer_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesShipToListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesShipToListRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }
    public function SalesShipToSelect($company_id, $customer_id,$ship_to_id)
    {//no cloud variation required
        $apiname = "SalesShipToSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->customer_id = $customer_id;
        $params1->$ship_to_id = $ship_to_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesShipToSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesShipToSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }

    public function SalesCustomerPricingSelect($company_id, $customer_id, $item_id, $sales_location_id = "101", $source_location_id = "101", $ship_to_id = "", $order_date = "", $unit_size = "", $unit_quantity = "1", $customer_part_no = "", $job_no = "", $unit="ea")
    {
        $apiname = "SalesCustomerPricingSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);
        $this->gwLog( "apikey = " . $apikey);
        try {
            $params1 = (object)[];
            $params1->company_id = $company_id;
            $params1->customer_id = $customer_id;

            $params1->sales_location_id = $sales_location_id;
            $params1->source_location_id = $source_location_id;
            $params1->ship_to_id = "";

            if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ){
                $params1->oe_qty_ordered = $unit_quantity;
                $params1->pricing_unit = $unit;
                $params1->sales_unit = $unit;
                $params1->supplier_id = "";
                $params1->prod_group_id = "";
                $params1->mfr_class_id = "";
                $params1->customer_part_no = $item_id;
            } else {
                $params1->oe_qty_ordered = $unit_quantity;
                $params1->unit_size = $unit_size;
                $params1->unit_quantity = $unit_quantity;
                $params1->order_date = "";
                $params1->item_id = $item_id;
                $params1->job_no = $job_no;
            }


            $params1->APIKey = $apikey;
            $rootparams = (object)[];
            $rootparams->SalesCustomerPricingSelectRequestContainer = $params1;
            $result = (object)[];
            $this->gwLog( "request = " . json_encode($rootparams));
            $result = $client->SalesCustomerPricingSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);
            $this->gwLog( "response = " . json_encode($result));
            return $response;
        } catch (\Exception $e) {
        }
    }

function ItemsProductSelect( $brsprod){
	  $apiname = "ItemsProductSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        try {
            $params1 = (object)[];
            $params1->item_id = $brsprod;

            $params1->APIKey = $apikey;
            $rootparams = (object)[];
            $rootparams->ItemsProductSelectRequestContainer = $params1;
            $result = (object)[];

            $result = $client->ItemsProductSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
}

    public function SalesCustomerInsert($company_id, $customer_id, $customer_name, $mail_address1, $mail_address2, $mail_city, $mail_state, $zipcd, $central_phone_number, $central_fax_number
        , $last_maintained_by, $terms, $customer_type_cd, $po_no_required, $currency_id, $invoice_batch_uid, $statement_batch_uid, $salesrep_id, $taxable_flag, $email_address
        , $allow_line_item_freight_flag, $delete_flag
        ,$source_price_cd
        ,$ar_account_no,$revenue_account_no,$cos_account_no,$allowed_account_no,$terms_account_no,$freight_account_no,$brokerage_account_no,$deferred_revenue_account_no
        ,$credit_status,$price_library_id,$invoice_type
        ,$default_branch,$location_id,$pricing_method_cd,$default_disposition,$price_library_id2)
    {
        try {
            $apiname = "SalesCustomerInsertUpdate";
            $this->LogAPICall($apiname);
            $apikey = $this->getConfigValue('apikey');
            $client = $this->createSoapClient($apikey, $apiname);

            $params1 = (object)[];
            $params1->company_id = $company_id;
            $params1->customer_id = $customer_id;
            $params1->customer_name = $customer_name;
            $params1->mail_address1 = $mail_address1;
            $params1->mail_address2 = $mail_address2;
            $params1->mail_city = $mail_city;
            $params1->mail_state = $mail_state;
            $params1->zipcd = $zipcd;
            $params1->central_phone_number = $central_phone_number;
            $params1->central_fax_number = $central_fax_number;
            $params1->last_maintained_by = $last_maintained_by;
            $params1->terms = $terms;
            $params1->customer_type_cd = $customer_type_cd;
            $params1->po_no_required = $po_no_required;
            $params1->currency_id = $currency_id;
            $params1->invoice_batch_uid = $invoice_batch_uid;
            $params1->statement_batch_uid = $statement_batch_uid;
            $params1->salesrep_id = $salesrep_id;
            $params1->taxable_flag = $taxable_flag;
            $params1->email_address = $email_address;
            $params1->allow_line_item_freight_flag = $allow_line_item_freight_flag;
            $params1->delete_flag = $delete_flag;
            $params1->source_price_cd = $source_price_cd;

            $params1->ar_account_no = $ar_account_no;
            $params1->revenue_account_no = $revenue_account_no;
            $params1->cos_account_no = $cos_account_no;
            $params1->allowed_account_no = $allowed_account_no;
            $params1->terms_account_no = $terms_account_no;
            $params1->freight_account_no = $freight_account_no;
            $params1->brokerage_account_no = $brokerage_account_no;
            $params1->deferred_revenue_account_no = $deferred_revenue_account_no;
            
            $params1->credit_status = $credit_status;
            $params1->pricing_method_cd = $pricing_method_cd;
            $params1->invoice_type = $invoice_type;
            $params1->default_branch_id = $default_branch;
            $params1->preferred_location_id = $location_id;
            $params1->price_library_id = $price_library_id;
            $params1->default_disposition = $default_disposition;
            $params1->price_library_id2 = $price_library_id2;
            
            $params1->APIKey = $apikey;
            $rootparams = (object)[];
            $rootparams->SalesCustomerInsertUpdateRequestContainer = $params1;
            $result = (object)[];

            $result = $client->SalesCustomerInsertUpdateRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog($e->getMessage());
        }
    }

    public function SalesOrderSelect($company_id, $order_id)
    {
        $apiname = "SalesOrderSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->order_id = $order_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesOrderSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesOrderSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog($e->getMessage());
        }
    }

    public function ItemsWarehouseProductSelect($company_id, $item_id, $location_id, $inactive)
    {

        //no p21cloud analog
        if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ){
                $apiname = "ItemsWarehouseProductSelect";
                $this->LogAPICall($apiname);
                $apikey = $this->getConfigValue('apikey');
                $client = $this->createSoapClient($apikey, $apiname);

                $params1 = (object)[];
                $params1->company_id = $company_id;
                $params1->item_id = $item_id;
                $params1->location_id = $location_id;
                $params1->inactive = $inactive;
                $params1->APIKey = $apikey;
                $rootparams = (object)[];
                $rootparams->ItemsWarehouseProductSelectRequestContainer = $params1;
                $result = (object)[];

                try {
                    $result = $client->ItemsWarehouseProductSelectRequest($rootparams);
                    $response = json_decode(json_encode($result), true);

                    return $response;
                } catch (\Exception $e) {
                    //$this->gwLog('Caught exception: ',  $e->getMessage(), "");
                    //$this->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
                }
        } else {
            return SalesCustomerPricingSelect($company_id, $this->getConfigValue('p21customerid'), $item_id, $location_id, $location_id,  "", "", "", "1", "", "");

        }
    }

    public function ItemsWarehouseProductList($company_id, $item_id, $location_id="")
    {
       // if (strpos($this->getConfigValue('apiurl'),'p21cloud') !==false  ) return ""; //no cloud analog yet

        $apiname = "ItemsWarehouseProductList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->item_id = $item_id;
		if ($location_id != "") {
			$params1->location_id = $location_id;
		}
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->ItemsWarehouseProductListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->ItemsWarehouseProductListRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ',  $e->getMessage(), "");
            $this->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }


    public function SalesShipToBatchInsertUpdate($params)
    {
        $apiname = "SalesShipToInsertUpdate";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesShipToInsertUpdateRequestContainer = $params;

        try {
            $result = $client->SalesShipToInsertUpdate($rootparams);
            return  json_decode(json_encode($result), true);
        } catch (\Exception $e) {
        }
    }





public function UpdateOrderFieldProcessed($orderid, $DateEntered)
    {
        try {
            $dbConnection = $this->resourceConnection->getConnection();

            $sql = "update `gws_GreyWolfOrderFieldUpdate` set `dateprocessed`=now() where `orderid`='$orderid' and dateentered='" . $DateEntered . "';";
            $dbConnection->query($sql)->execute();
        } catch (\Exception $e) {
            $this->gwLog("Failed to close row in order field update table: " . $e->getMessage());
        }
    }

    public function UpdateOrderQueue($orderid)
    {
        $dbConnection = $this->resourceConnection->getConnection();

        $sql = "update `gws_GreyWolfOrderQueue` set `dateprocessed`=now() where `orderid`='$orderid' ";
        if ($dbConnection->query($sql)->execute() === true) {
            // //$this->gwLog "New record created successfully";
            //$this->gwLog ("Order queue record updated successfully for order " . $orderid);
        } else {
            // //$this->gwLog "Error: " . $sql . "<br>" . $dbConnection->error;
            //$this->gwLog ("Update Queue Error: " . $sql . "..." . $dbConnection->error . " ... order " . $orderid);
        }
    }

    public function SubmitOrder($invoice)
    {
        
        $configs = $this->getConfigValue([
            'apikey', 'p21customerid', 'cono', 'whse', 'slsrepin', 'defaultterms','shipviaty','sendtoerpinv', 'orderaspo', 'potermscode', 'operinit', 'transtype','slsrepout', 'holdifover', 'address_to_erp','taker','send_as_item'
        ]);

        extract($configs);

        if ($sendtoerpinv == "1") {
            $order = $invoice->getOrder();
        } else {
            $order = $invoice;
        }
      //  $order = $invoice->getOrder();
        $orderid = $order->getId();
        $orderincid = $order->getIncrementId();
        $shipping_address = $order->getShippingAddress()->getData();
        $this->gwLog("Sending order " . $orderincid);
        try{
            $shippingAmount = $order->getShippingAmount();
        } catch (\Exception $ePO) {
            $shippingAmount = 0;
        }
        $payment = $order->getPayment();
        try{
            if (isset($payment))
            {
                $poNumber = $payment->getPoNumber();
            }
            else
            {
                $poNumber = "";
            }
        } catch (\Exception $ePO) {
            $poNumber ="";
        }

        if (isset($payment)) {
			$method = $payment->getMethodInstance();
			$methodTitle = $method->getTitle();
			$methodcode = $payment->getMethod();
        } else {
            $method = "";
            $methodTitle = "";
            $methodcode = $defaultterms;
        }
        $erpAddress = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

       try {
            $addressId = $shipping_address["customer_address_id"];
            $addressOBJ = $objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');
            $addressObject = $addressOBJ->getById($addressId);
            try {
                if (isset($addressObject)) {
                    $erpAddress = $addressObject->getCustomAttribute("ERPAddressID")->getValue(); //->GetValue() ;
                }
            } catch (\Throwable $e2) { // For PHP 7
                $erpAddress = "";
                $this->gwLog("No erp address2 - " . json_encode($e2->getMessage()));
            } catch (\Exception $e1) {
                $erpAddress = "";
                $this->gwLog("No erp address2 - " . json_encode($e1->getMessage()));
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
        $items = $invoice->getAllItems();
        $total = $invoice->getGrandTotal();

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = 'directory_country_region';

        //$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id
        $statecd = $region->getData()['code'];

        //	var_dump($statecd);
        //******************************************
        $batchnm = substr(date("YmdHi") . rand(), -8);

        $custno = $p21customerid;

        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        $customerData = $customerSession2->getCustomer();
        if ($order->getCustomerIsGuest()) {
            //$this->gwLog ("customer is guest");
            $custno = $p21customerid;
        } else {
            $CustomerID = $order->getCustomerId();

            $custno = $customerData['p21_custno'];

            if (!$custno) {
                $custno = $p21customerid;
            }
        }

        if ($erpAddress == "") {
            $shipto = $custno;
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
		//$TermsToUse = $defaultterms;
        if ($methodcode == "purchaseorder" && isset($potermscode)) {
            $termstype = $potermscode;
        } elseif ($methodcode == "cashondelivery") {
           // $TermsToUse = "cod";
        } elseif (stripos($methodcode, "credit") !== false) {
          //  $TermsToUse = "cc";
        }
        $paramsShipTo = (object) [];
        $paramsShipTo->company_id = $cono;
        $paramsShipTo->customer_id = $custno;
        $paramsShipTo->ship_to_id = $shipto;
        $paramsShipTo->name = $name;
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
        $paramsShipTo->freight_cd =$shipviaty;
        $paramsShipTo->terms = $defaultterms;
        $paramsShipTo->primary_salesrep = $slsrepin;
        $paramsShipTo->APIKey = $apikey;

        //salesorderinsertUpdate

        $CompanyId = $cono;
        $CustomerId = $custno;
        $InvoiceBatchNumber = '1';
        $Salesreps = $slsrepin;
        $Terms = $termstype;
        $Taker = $taker;
        // $PoNo= $orderincid;
        if ($orderaspo == 1) {
            $PoNo = $orderincid;//'Web Order #'
        } else {
            $PoNo = $poNumber;//'Web Order #'
        }
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

		if ($holdifover !="") {
			if ($total>$holdifover) {
				$disposition='h';
			}
		} else {
		    
		}

        $ShipToMailAddress = $shipto;
        $ShipToName = $name;
        $ShipToAddress1 = $addr1;
        $ShipToAddress2 = $addr2;
        $ShipToAddress3 = $addr3;
        $ShipToCity = $city;
        $OeHdrShip2State = $statecd;
        $ZipCode = $zipcd;
        $ShipToCountry = $countrycd;
        $ShipToPhone = $phoneno;
        $JobNo = "";

        //end p21 mid
        $lineno = '1';
        $price = 0;
        $descrip1 = '';
        $descrip2 = '';
        $qtyord = 10;
        $shipprod = '1-001';
        $reqprod = '';
        $enterdt = '';
        $unit = 'ea';
        $lncomm = '';
        $cprintfl = '';
        $lndiscamt = '';
        $lndisctype = '';
        $SalesOrderLinesInsert = '';
        $rushfl = '';
        $taxablefl = '';
        $prodcostSRLA = '';
        $directshipyesno = '';
        $pdrecno = 0;
        $nonstockcost = 0;
        $prodcat = '';
        $approvety = '';

        $this->gwLog("!shipto= " . $shipto);
        $paramsHead = new \ArrayObject();//(object)array();
         if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ){
         
            $thisparam= array(
                'company_id' => $CompanyId, 'customer_id'=>$CustomerId, 'location_id'=>$LocationId,'invoice_batch_uid'=>$InvoiceBatchNumber,'APIKey'=>$apikey,'job_name'=>$JobNo,
                'salesrep_id' => $Salesreps, 'terms'=>$Terms, 'taker'=>$Taker,'po_no'=>$PoNo,'packing_basis'=>$PackingBasis,'freight_cd'=>$FreightCd,
                'completed' => $Completed, 'approved'=>$Approved, 'projected_order'=>$Quote,'ship2_add1'=>$ShipToAddress1,'ship2_add2'=>$ShipToAddress2,'ship2_city'=>$ShipToCity,
                'ship2_state' => $OeHdrShip2State, 'ship2_zip'=>$ZipCode, 'ship2_country'=>$ShipToCountry,'ship_to_phone'=>$ShipToPhone,'delivery_instructions'=>$DeliveryInstructions,'ship2_name'=>$ShipToName,
                'freight_out'=>$shippingAmount, 'address_id'=>$shipto
            );
           
            if ($erpAddress == "" && $address_to_erp == "1") {
               $thisparam=array_merge( $thisparam,array('shipto' => $shipto));
            } elseif (isset($erpAddress)) {
                $thisparam=array_merge( $thisparam, array('shipto' => $erpAddress));
            }
            if (!empty($disposition)) $thisparam=array_merge( $thisparam,array('disposition' => $disposition));
            
            $paramsHead[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);


        } else {
            $paramsHead[] = new \SoapVar($CompanyId, XSD_STRING, null, null, 'CompanyId');
            $paramsHead[] = new \SoapVar($CustomerId, XSD_STRING, null, null, 'CustomerId');
            $paramsHead[] = new \SoapVar($InvoiceBatchNumber, XSD_STRING, null, null, 'InvoiceBatchNumber');
            $paramsHead[] = new \SoapVar($Salesreps, XSD_STRING, null, null, 'Salesreps');
            $paramsHead[] = new \SoapVar($Terms, XSD_STRING, null, null, 'Terms');
            $paramsHead[] = new \SoapVar($Taker, XSD_STRING, null, null, 'Taker');
            $paramsHead[] = new \SoapVar($PoNo, XSD_STRING, null, null, 'PoNo');

            $paramsHead[] = new \SoapVar($LocationId, XSD_STRING, null, null, 'SourceLocationId');
            $paramsHead[] = new \SoapVar($SourceLocationId, XSD_STRING, null, null, 'SourceLocationId');
            $paramsHead[] = new \SoapVar($FreightCd, XSD_STRING, null, null, 'FreightCd');
            $paramsHead[] = new \SoapVar($Completed, XSD_STRING, null, null, 'Completed');
            $paramsHead[] = new \SoapVar($Approved, XSD_STRING, null, null, 'Approved');
            $paramsHead[] = new \SoapVar($Quote, XSD_STRING, null, null, 'Quote');
            $paramsHead[] = new \SoapVar($DeliveryInstructions, XSD_STRING, null, null, 'DeliveryInstructions');
            $paramsHead[] = new \SoapVar($ShipToName, XSD_STRING, null, null, 'ShipToName');
            $paramsHead[] = new \SoapVar($ShipToAddress1, XSD_STRING, null, null, 'ShipToAddress1');
            $paramsHead[] = new \SoapVar($ShipToAddress2, XSD_STRING, null, null, 'ShipToAddress2');
            $paramsHead[] = new \SoapVar($ShipToCity, XSD_STRING, null, null, 'ShipToCity');
            $paramsHead[] = new \SoapVar($OeHdrShip2State, XSD_STRING, null, null, 'OeHdrShip2State');
            $paramsHead[] = new \SoapVar($ZipCode, XSD_STRING, null, null, 'ZipCode');
            $paramsHead[] = new \SoapVar($ShipToCountry, XSD_STRING, null, null, 'ShipToCountry');
            $paramsHead[] = new \SoapVar($ShipToPhone, XSD_STRING, null, null, 'ShipToPhone');
            $paramsHead[] = new \SoapVar($JobNo, XSD_STRING, null, null, 'JobNo');
            $paramsHead[] = new \SoapVar($apikey, XSD_STRING, null, null, 'APIKey');

        }
        $this->gwLog("1");
        $lineno = 0;
        $haslines = false;
        foreach ($items as $item) {

          //  if ($item->getOrderItem()->getParentItem()) {
           //     $this->gwLog("Skipping item has parent item");
           //     continue;
          //  }


            $lineno = $lineno + 1;
            $name = $item->getName();
            $type = $this->getAltitudeSKU($item); //$item->getSku();
            $id = $item->getProductId();
              $this->gwLog('Setting salesorderinsert uom');
            $unit = 'ea';
            try {
                $productRepository1 = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
                  $this->gwLog('getting uom for prod ' . $type);
                $productObj = $productRepository1->get($type);
                  $this->gwLog('getting uom3');
                $unit= $productObj->getData('sales_uom'); 
                  $this->gwLog('set uom:' . $unit);
            } catch (\Exception $e1) {
                    $this->gwLog('Caught exception: ' . $e1->getMessage());
                    $unit="ea";
            }
            if (empty($unit)) $unit = 'ea';
            $this->gwLog('order unit = ' . $unit);
            //$qty = $item->getQty();
			 if ($sendtoerpinv == "1") {
                $qty = $item->getQty();
            } else {
                $qty = $item->getQtyOrdered();
            }
            if (empty($qty)){
                $qty=1;
            }
            $price = $item->getPrice();
          
            $description = $item["description"];
            
             if (strpos($name, "Invoice") !== false && strpos($name, "Customer") !== false ) {
                //insert payment for each item, so multiple invoices can be paid at once
                $arr = explode('-', $type);
                $invno = $arr[1];
             //   $invsuf = $arr[2];
                $this->gwLog("Submitting invoice pmt: " . $type . "; cust=" . $custno . "; invoice=" . $invno . "; amt=" . $price);
               // $gcinv = $this->SalesOrderPaymentInsert($custno, $invno, $invsuf, $price, $operinit, $moduleName);

                //if (isset($gcinv["errorcd"]) && $gcinv["errorcd"] != '000-000') {
                //    $this->gwLog("Error: " . $gcinv["errordesc"]);
                //    throw new \Exception($gcinv["errordesc"]);
                //} else {
                    continue;
                //}
            }
            
            
            $paramsDetail = new \ArrayObject(); //(object)array();  //this needs to be multidimensional
            if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ){
                $lineno=$lineno + 1;
                $supplier_id="";
                $gcsupplier=$this->ItemsInventorySupplierList('' ,'',$LocationId,$type);
                if(isset($gcsupplier['ItemsInventorySupplierListResponseContainerItems'])) {
                    foreach($gcsupplier['ItemsInventorySupplierListResponseContainerItems'] as $supplier) {
                        if($supplier['primary_supplier'] == 'Y') {
                            $supplier_id = $supplier['supplier_id'];
                            break;
                        }
                    }
                } else if (isset($gcsupplier["supplier_id"]) && !empty($gcsupplier["supplier_id"])){
                   $supplier_id=$gcsupplier["supplier_id"]; 
                }
                $paramsDetail[] = new \SoapVar(array('company_id' => $CompanyId), SOAP_ENC_OBJECT);//  new \SoapVar($CompanyId, XSD_STRING, null, null, 'company_id');
                $paramsDetail[] = new \SoapVar(array('line_no' => $lineno), SOAP_ENC_OBJECT);//  new \SoapVar($lineno, XSD_STRING, null, null, 'line_no');
                $paramsDetail[] = new \SoapVar(array('item_id' => $type), SOAP_ENC_OBJECT);//  new \SoapVar($type, XSD_STRING, null, null, 'item_id');
                $paramsDetail[] = new \SoapVar(array('qty_ordered' => $qty), SOAP_ENC_OBJECT);//  new \SoapVar($qty, XSD_STRING, null, null, 'qty_ordered');
                //$paramsDetail[] = new \SoapVar(array('qty_allocated' => $qty), SOAP_ENC_OBJECT);//  new \SoapVar($qty, XSD_STRING, null, null, 'qty_ordered');
                $paramsDetail[] = new \SoapVar(array('unit_price' => $price), SOAP_ENC_OBJECT);//  new \SoapVar($price, XSD_STRING, null, null, 'unit_price');
                $paramsDetail[] = new \SoapVar(array('source_loc_id' => $whse), SOAP_ENC_OBJECT);//  new \SoapVar($whse, XSD_STRING, null, null, 'source_loc_id');
                $paramsDetail[] = new \SoapVar(array('delete_flag' => "N"), SOAP_ENC_OBJECT);//  new \SoapVar("N", XSD_STRING, null, null, 'delete_flag');
                $paramsDetail[] = new \SoapVar(array('unit_of_measure' => $unit), SOAP_ENC_OBJECT);//  new \SoapVar($unit, XSD_STRING, null, null, 'unit_of_measure');
                $paramsDetail[] = new \SoapVar(array('extended_desc' => $description), SOAP_ENC_OBJECT);//  new \SoapVar($description, XSD_STRING, null, null, 'extended_desc');
                $paramsDetail[] = new \SoapVar(array('supplier_id' => $supplier_id), SOAP_ENC_OBJECT);//  new \SoapVar($supplier_id, XSD_STRING, null, null, 'supplier_id');
                $haslines = true;
            } else {
                $paramsDetail[] = new \SoapVar($CompanyId, XSD_STRING, null, null, 'CompanyId');
                $paramsDetail[] = new \SoapVar($lineno, XSD_STRING, null, null, 'LineNo');
                $paramsDetail[] = new \SoapVar($type, XSD_STRING, null, null, 'ItemId');
                $paramsDetail[] = new \SoapVar($qty, XSD_STRING, null, null, 'QtyOrdered');
                $paramsDetail[] = new \SoapVar($price, XSD_STRING, null, null, 'UnitPrice');
                $paramsDetail[] = new \SoapVar($whse, XSD_STRING, null, null, 'SourceLocId');
                $paramsDetail[] = new \SoapVar("N", XSD_STRING, null, null, 'Delete');
                $paramsDetail[] = new \SoapVar($unit, XSD_STRING, null, null, 'UnitOfMeasure');
                $paramsDetail[] = new \SoapVar($description, XSD_STRING, null, null, 'ExtendedDesc');
                $haslines = true;

            }
            //$paramsHead->append(new \SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'SalesOrderLinesInsertUpdateRequestContainer'));
            
            $thisparamLines= array(   'SalesOrderLinesInsertUpdateRequestContainer' => $paramsDetail->getArrayCopy()               );

            $paramsHead->append(new SoapVar(
               $thisparamLines,
                SOAP_ENC_OBJECT,
                null,
                null,
                'SalesOrderLinesInsertUpdateRequestContainer'
            ));
            
            
        }
       
        if ($haslines) {
             $this->gwLog('checking upcharge item');
             $this->gwLog('send_as_item = ' . $send_as_item);
             if (!empty($send_as_item)){
                $this->gwLog('Adding upcharge item');
                try {
                    $upcharge_total=0;
                    $this->gwLog('getting upcharge amount');
                    $dbConnection = $this->resourceConnection->getConnection();
                    $querycheck = "SELECT upcharge_total FROM sales_order WHERE increment_id='" . $orderincid . "'";
                    //$this->gwLog($querycheck);
                    $query_result = $dbConnection->fetchAll($querycheck);
                    if ($query_result !== false) {
                         foreach ($query_result as $row) {
                            $upcharge_total = $row["upcharge_total"];
                         }
                    } else {
                        $upcharge_total=0;
                    }
                    //$upcharge_total=$order->getShippingAddress()->getData('upcharge_total') ;
                    $this->gwLog('upcharge_total = ' . $upcharge_total);
                    if ($upcharge_total >0){
                        $lineno=$lineno + 1;
                        $qty=1;
                        $paramsDetail = new \ArrayObject();
                        //*****************************//
                        if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ){
                            $supplier_id="";
                            $gcsupplier=$this->ItemsInventorySupplierList('' ,'',$LocationId,$send_as_item);
                            if (isset($gcsupplier["supplier_id"]) && !empty($gcsupplier["supplier_id"])){
                               $supplier_id=$gcsupplier["supplier_id"]; 
                            }
                            $paramsDetail[] = new \SoapVar(array('company_id' => $CompanyId), SOAP_ENC_OBJECT);//  new \SoapVar($CompanyId, XSD_STRING, null, null, 'company_id');
                            $paramsDetail[] = new \SoapVar(array('line_no' => $lineno), SOAP_ENC_OBJECT);//  new \SoapVar($lineno, XSD_STRING, null, null, 'line_no');
                            $paramsDetail[] = new \SoapVar(array('item_id' => $send_as_item), SOAP_ENC_OBJECT);//  new \SoapVar($type, XSD_STRING, null, null, 'item_id');
                            $paramsDetail[] = new \SoapVar(array('qty_ordered' => 1), SOAP_ENC_OBJECT);//  new \SoapVar($qty, XSD_STRING, null, null, 'qty_ordered');
                            $paramsDetail[] = new \SoapVar(array('qty_allocated' => $qty), SOAP_ENC_OBJECT);//  new \SoapVar($qty, XSD_STRING, null, null, 'qty_ordered');
                            $paramsDetail[] = new \SoapVar(array('unit_price' => $upcharge_total), SOAP_ENC_OBJECT);//  new \SoapVar($price, XSD_STRING, null, null, 'unit_price');
                            $paramsDetail[] = new \SoapVar(array('source_loc_id' => $whse), SOAP_ENC_OBJECT);//  new \SoapVar($whse, XSD_STRING, null, null, 'source_loc_id');
                            $paramsDetail[] = new \SoapVar(array('delete_flag' => "N"), SOAP_ENC_OBJECT);//  new \SoapVar("N", XSD_STRING, null, null, 'delete_flag');
                            $paramsDetail[] = new \SoapVar(array('unit_of_measure' => 'ea'), SOAP_ENC_OBJECT);//  new \SoapVar($unit, XSD_STRING, null, null, 'unit_of_measure');
                            $paramsDetail[] = new \SoapVar(array('extended_desc' => $send_as_item), SOAP_ENC_OBJECT);//  new \SoapVar($description, XSD_STRING, null, null, 'extended_desc');
                            $paramsDetail[] = new \SoapVar(array('supplier_id' => $supplier_id), SOAP_ENC_OBJECT);//  new \SoapVar($supplier_id, XSD_STRING, null, null, 'supplier_id');
                            $haslines = true;
                        } else {
                            $paramsDetail[] = new \SoapVar($CompanyId, XSD_STRING, null, null, 'CompanyId');
                            $paramsDetail[] = new \SoapVar($lineno, XSD_STRING, null, null, 'LineNo');
                            $paramsDetail[] = new \SoapVar($send_as_item, XSD_STRING, null, null, 'ItemId');
                            $paramsDetail[] = new \SoapVar(1, XSD_STRING, null, null, 'QtyOrdered');
                            $paramsDetail[] = new \SoapVar($upcharge_total, XSD_STRING, null, null, 'UnitPrice');
                            $paramsDetail[] = new \SoapVar($whse, XSD_STRING, null, null, 'SourceLocId');
                            $paramsDetail[] = new \SoapVar("N", XSD_STRING, null, null, 'Delete');
                            $paramsDetail[] = new \SoapVar('ea', XSD_STRING, null, null, 'UnitOfMeasure');
                            $paramsDetail[] = new \SoapVar($send_as_item, XSD_STRING, null, null, 'ExtendedDesc');
                            $haslines = true;
            
                        }
                        //$paramsHead->append(new \SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'SalesOrderLinesInsertUpdateRequestContainer'));
                        
                        $thisparamLines= array(   'SalesOrderLinesInsertUpdateRequestContainer' => $paramsDetail->getArrayCopy()               );
            
                        $paramsHead->append(new SoapVar(
                           $thisparamLines,
                            SOAP_ENC_OBJECT,
                            null,
                            null,
                            'SalesOrderLinesInsertUpdateRequestContainer'
                        )); 
                        //*****************************//
                    }
                } catch (\Exception $e1) {
                        $this->gwLog('Caught exception: ' . $e1->getMessage());
                }
               
            }
  

            $gcnl = $this->SalesOrderInsertUpdate($paramsHead);
        } else {
            return true;
        }
        
        if (isset($gcnl["order_no"]) && $haslines ) {
            $orderno = $gcnl["order_no"];

            $this->gwLog("order=" . $orderno);
            if ($orderno != "0") {
                //update P21_OrderNo field

                $this->UpdateOrderWithERPOrderNo($order, $orderno);
                return true;
            }
        }
        if (isset($gcnl["OrderNo"]) && $haslines ) {
            $orderno = $gcnl["OrderNo"];

            $this->gwLog("order=" . $orderno);
            if ($orderno != "0") {
                //update P21_OrderNo field

                $this->UpdateOrderWithERPOrderNo($order, $orderno);
                return true;
            }
        }
    }
    public function SalesOrderInsertUpdate($header)
    {
        $apiname = "SalesOrderInsertUpdate";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        //$rootparams = new \ArrayObject();
       // $rootparams->append(new \SoapVar($header->getArrayCopy(), SOAP_ENC_OBJECT, null, null, 'SalesOrderInsertUpdateRequestContainer'));
            $rootparams = (object) [];
            $rootparams->SalesOrderInsertUpdateRequestContainer = $header->getArrayCopy();
            
       /*     ob_start();
var_dump($rootparams);
$result1 = ob_get_clean();
$this->gwLog($result1);*/


        try {
            $result = $client->SalesOrderInsertUpdate($rootparams);
//$this->gwLog("REQUEST:\n" . $client->__getLastRequest() . "");
            return  json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . $e->getMessage());
            $this->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()));
        }
    }

    public function UpdateOrderWithERPOrderNo($order, $ERPOrderNo, $moduleName = "")
    {
        $configs = $this->getConfigValue(['apikey', 'cono']);
        extract($configs);

        $orderid = $order->getIncrementId();
        $ordersuf = "";
        $dbConnection = $this->resourceConnection->getConnection();

        $this->gwLog("Updating order table with ERP #" . $ERPOrderNo);

        $order->setExtOrderId($ERPOrderNo);

        //***************
        try {
            $sql = "update sales_order set ext_order_id='" . $ERPOrderNo . "' where increment_id='" . $orderid . "';";
            $this->gwLog($sql);
            if ($dbConnection->query($sql)->execute() === true) {
                $this->gwLog("Order grid field  updated successfully for order " . $ERPOrderNo);
            } else {
                $this->gwLog("Failed to update Order grid field successfully for order " . $ERPOrderNo);
            }
        } catch (\Exception $e) {
            $this->gwLog("Catch error: " . json_encode($e->getMessage()));
        }
        //**********************
        try {
            $sql = "update sales_order_grid set ext_order_id='" . $ERPOrderNo . "' where increment_id='" . $orderid . "';";
            if ($dbConnection->query($sql)->execute() === true) {
                // echo "New record created successfully";
                $this->gwLog("Grid ext order no: " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog("Failed to insert update order field table");
        }
        try {
            $query = "ALTER TABLE `gws_GreyWolfOrderFieldUpdate` ADD COLUMN IF NOT EXISTS `suffix_list` varchar(255) AFTER `dateprocessed`;";

            if ($dbConnection->query($sql) === true) {
            }
        } catch (\Exception $e) {
        }
        //*********************
        //***************

        $sql = "select * FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderid . "'";

        $result = $dbConnection->fetchAll($sql);
        if (count($result)) {
            $sql = "update `gws_GreyWolfOrderFieldUpdate` set `ERPOrderNo`='" . $ERPOrderNo . "', `ERPSuffix`= '" . $ordersuf . "' where `orderid`='" . $orderid . "'";
        } else {
            $sql = "INSERT INTO `gws_GreyWolfOrderFieldUpdate` (`orderid`,`dateentered`,`ERPOrderNo`,`ERPSuffix`) VALUES ('$orderid',now(),'" . $ERPOrderNo . "', '" . $ordersuf . "')";
        }

        try {
            if ($dbConnection->query($sql)->execute() === true) {
                // echo "New record created successfully";
                $this->gwLog("Order field  table updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog("Failed to insert update order field table");
        }
  try {
        $payment = $order->getPayment();
        $paymentMethod = "";//(string) $payment->getMethod();
        //$method = $payment->getMethodInstance();
        //$this->gwLog($method);
        //$methodTitle = $method->getTitle();
        //$this->gwLog($methodTitle);
        //$paymentMethod=$methodTitle;
  } catch (\Exception $e) {
      $paymentMethod = '';
  }
        if (strpos($paymentMethod, "authorizenet") === false && strpos($paymentMethod, "anet_") === false) {
            $this->gwLog("not Authorize: $paymentMethod");
        } else {
            $additionalInfo = $payment->getData('additional_information');
            $authNo = "";

            if (isset($additionalInfo['authCode']) && $additionalInfo['authCode'] != "") {
                $authNo = $additionalInfo['authCode'];
                $transID = $payment->getData('last_trans_id');

                if ($transID == "" && isset($additionalInfo['transactionId'])) {
                    $transID = $additionalInfo['transactionId'];
                }

                $authAmount = $payment->getAmountAuthorized();
                $exp = $order->getPayment()->getCcExpMonth() . '/' . $order->getPayment()->getCcExpYear();

                $lastfour = $order->getPayment()->getCcLast4();
                $methodTitle = (isset($additionalInfo['cardType'])) ? $additionalInfo['cardType'] : "";

                switch ($methodTitle) {
                    case "visa":
                            $methodTitle = "12";
                        break;
                    case "mastercard":
                        $methodTitle = "13";
                        break;
                    case "amex":
                        $methodTitle = "14";
                        break;
                    case "discover":
                        $methodTitle = "15";
                        break;
                    default:
                        $methodTitle = "16";
                        break;
                }
                $sql = "update `gws_GreyWolfOrderFieldUpdate` set `TransactionID`='" . $transID . "', `AuthID`= '" . $authNo . "', `CCAuthNo`= '" . $authNo . "', `CCNo`= '" . $lastfour . "', `CardType`= '" . $methodTitle . "', `TxnAmt`= '" . $authAmount . "' where `orderid`='" . $orderid . "'";

                try {
                    if ($dbConnection->query($sql)->execute() === true) {
                        // echo "New record created successfully";
                        $this->gwLog("Order field  table updated successfully for order " . $orderid);
                    }
                } catch (\Exception $e) {
                    $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
                }
            }
        }
    }

    public function SalesPickTicketList($cono, $orderno, $ordersuf)
    {

        //no p21cloud analog
        if (strpos($this->getConfigValue('apiurl'),'p21cloud') !==false  ) return "";
        $apiname = "SalesPickTicketList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $cono;
        $params1->order_no = $orderno;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesPickTicketListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesPickTicketListRequest($rootparams);
        } catch (\Exception $e) {
        }
        try {
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }

    public function SalesOrderLinesSelect($company_id, $order_id)
    {
        $apiname = "SalesOrderLinesSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->order_id = $order_id;
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesOrderLinesSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesOrderLinesSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }
    public function SalesCustomerInvoiceLinesSelect($company_id, $order_id)
    {
        $apiname = "SalesCustomerInvoiceLinesSelect";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object)[];
         //no p21cloud analog
        if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ) {
            $params1->company_id = $company_id;
            $params1->order_id = $order_id;
        } else {
            $params1->StoreName = $company_id;
            $params1->invoice_no = $order_id;

        }
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesCustomerInvoiceLinesSelectRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesCustomerInvoiceLinesSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }

    public function SalesCustomerInvoiceList($cono, $custno, $location, $sdate, $edate)
    {
         //no p21cloud analog
        $apiname = "SalesCustomerInvoiceList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);
if (empty($custno)) return '';
        $params1 = (object)[];
        if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ) {
            $params1->company_id = $cono;
            $params1->customer_id = $custno;
            $params1->sales_location_id = $location;
            $params1->{'brs-b-invoice_date'} = $sdate;
            $params1->{'brs-e-invoice_date'} = $edate;
         } else {
            $params1->StoreName = $cono;
            $params1->customer_id = $custno;
            //$params1->sales_location_id = $location;
            $params1->{'brs-b-invoice_date'} = $sdate;
            $params1->{'brs-e-invoice_date'} = $edate;

        }
        $params1->APIKey = $apikey;

        $rootparams = (object)[];
        $rootparams->SalesCustomerInvoiceListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesCustomerInvoiceListRequest($rootparams);
            $response = json_decode(json_encode($result), true);
            //$this->gwLog(json_encode($rootparams));
            //$this->gwLog(json_encode($response));

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()));
        }
    }

    public function SalesOrderList($company_id, $customer_id, $location_id, $po_no, $cancel_flag, $completed, $startDate, $endDate, $Delete)
    {
        $apiname = "SalesOrderList";
        $this->LogAPICall($apiname);
        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);
        if (empty($customer_id)) return '';
        $params1 = (object)[];
        $params1->company_id = $company_id;
        $params1->customer_id = $customer_id;
        $params1->location_id = $location_id;
        $params1->po_no = $po_no;
        $params1->cancel_flag = $cancel_flag;
        $params1->completed = $completed;
        //these fields don't have a p21cloud analog
        //if (strpos($this->getConfigValue('apiurl'),'p21cloud') ===false  ) {
                $params1->{'brs-b-order_date'} = $startDate;
                $params1->{'brs-e-order_date'} = $endDate;
                $params1->Delete = $Delete;
        //}
        $params1->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesOrderListRequestContainer = $params1;
        $result = (object)[];

        try {
            $result = $client->SalesOrderListRequest($rootparams);
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
        }
    }

    public function SalesOrderPaymentInsert($custno, $invno, $amt)
    {

        if (strpos($this->getConfigValue('apiurl'),'p21cloud') !==false  ) return "";
        //no p21cloud analog
        $apiname = "SalesOrderPaymentAuthInsert	";
        $this->LogAPICall($apiname, $moduleName);

        $configs = $this->getConfigValue(['apikey', 'cono', 'operinit']);
        extract($configs);
        $client = $this->createSoapClient($apikey, $apiname);

        $params = (object)[];
        $params->company_id = $cono;
        $params->customer_id = $custno;
        $params->invoice_no = $invno;
        $params->payment_amount = $amt;
        $params->remitter_id = $operinit;
        $params->APIKey = $apikey;
        $rootparams = (object)[];
        $rootparams->SalesOrderPaymentAuthInsertRequestContainer = $params;

        try {
            $result = $client->SalesOrderPaymentAuthInsertRequest($rootparams);

            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()));
        }
    }

    public function SendToGreyWolf($invoice)
    {
        $sendtoerpinv = $this->getConfigValue('sendtoerpinv');
        try {
            if ($sendtoerpinv == 1) {
                if ($invoice->getUpdatedAt() == $invoice->getCreatedAt()) {
                    $this->gwLog("Creating ERP order by invoice");
                    if ($this->SubmitOrder($invoice) == true) {
                        $this->gwLog("Order Created");
                    } else {
                        //populate missing data table
                        $this->gwLog("Queueing  order");
                        $this->InsertOrderQueue($invoice);
                    }
                } else {
                    $this->gwLog("Order/Invoice dates do not match");
                }
            } elseif ($sendtoerpinv == 0) {
                $this->gwLog("Creating ERP order by order");
                if ($this->SubmitOrder($invoice) == true) {
                    $this->gwLog("Order Created");
                } else {
                    //populate missing data table
                    $this->gwLog("Queueing  order");
                    $this->InsertOrderQueue($invoice);
                }
            }
        } catch (\Exception $e) {
            $this->gwLog('<br>Caught exception: ' .  json_encode($e->getMessage()));
         //   $this->gwLog("<br>REQUEST:\n" . htmlentities($client->__getLastRequest()) . "\n");
        }

        return true;
    }

   public function InsertOrderQueue($invoiceno)
    {
        $dbConnection = $this->resourceConnection->getConnection();
        $sendtoerpinv = $this->getConfigValue('sendtoerpinv');

        if ($sendtoerpinv == 1) {
            $order = $invoiceno->getOrder();
        } else {
            $order = $invoiceno;
        }

        $orderid = $order->getIncrementId();

        $sql = "INSERT INTO `gws_GreyWolfOrderQueue` (`orderid`,`dateentered`) VALUES ('$orderid', now()) ";
        //$this->gwLog($sql);
        if ($dbConnection->query($sql)->execute() === true) {
            // echo "New record created successfully";
            $this->gwLog("New order queue record created successfully for order " . $orderid);
        } else {
            $this->gwLog("Error: " . $sql . "... order " . $orderid);
        }
    }
}
