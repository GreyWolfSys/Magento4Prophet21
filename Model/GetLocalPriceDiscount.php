<?php

namespace Altitude\P21\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use SoapVar;

class GetLocalPriceDiscount implements ObserverInterface
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

        if ($this->p21->df_is_admin()) return "";

        $moduleName = $this->p21->getModuleName(get_class($this));
        $configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse', 'onlycheckproduct','localpriceonly','localpricediscount' ]);
        extract($configs);
        if (empty($localpricediscount) || !isset($localpricediscount) || $localpricediscount==0) {
            $localpricediscount=1;
        } else {
            $localpricediscount=(100-$localpricediscount)/100;
        }

        $url = $this->p21->urlInterface()->getCurrentUrl();
        $ip = $this->remoteAddress->getRemoteAddress();
        $displayText = $observer->getEvent()->getName();
        $controller = $this->request->getControllerName();
        $skipAPI=false;
        $this->p21->gwLog("Discount price check starts: " . $url);
        if (strpos($url, 'cart/add/') !== false ) return "";

        $products = $productsCollection = [];
        try {
            $singleProduct = $observer->getEvent()->getProduct();
            if (is_null($singleProduct)) {
                $productsCollection = $observer->getCollection();
                $singleitem = "false";
            } else {

                $products = [];
                $productsCollection[] = $singleProduct;
                $singleitem = "true";
            }
        } catch (exception $e) {
        }
     

        foreach ($productsCollection as $product) {
            $price = 0;
            $prod=$product->getSku();
            $this->p21->gwLog("Discount price sku: " . $prod);
            $price = $product->getPrice();
            $this->p21->gwLog("Discount starting price : " . $price);
            if ($localpriceonly=="Magento" ) {
                if ($localpricediscount<>1) {
                    $price = $price * $localpricediscount;
                }
                
                $this->p21->gwLog("Discount ending price : " . $price);
                $product->setPrice($price);
                //return $price;
            }



            
        }

 
    }
}
