<?php

namespace Altitude\P21\Controller\Index;

class GetAjax extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;

    protected $_resultJsonFactory;

    protected $customerSession;

    protected $p21;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->p21 = $p21;

        return parent::__construct($context);
    }

    public function execute()
    {
        if ($this->p21->botDetector() || !$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('/');

            return;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $controller = $request->getControllerName();
        $uom="";
        if ($controller != 'product') {
         //   return;
        }
     
        $url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        $bSkip = 'false';
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'whse','localpriceonly']);
        extract($configs);

        $this->p21->gwLog('ajax!!c: ' . $controller . ' / u: ' . $url);

        if ($this->p21->getSession()->getApidown()) {
            $apidown = $this->p21->getSession()->getApidown();
        } else {
            $apidown = false;
        }

        $this->p21->gwLog('config price started');
        $newprice = 0;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        if ($this->p21->getSession()->isLoggedIn()) {
            // Logged In
            $customerSession = $this->customerSession;
            $customerData = $customerSession->getCustomer();
            $custno = $customerData['p21_custno'];
        } else {
            // Not Logged In
            $custno = $p21customerid;
        }
        if (empty($custno)) {
            $custno = $p21customerid;
        }

        $prod = $this->getRequest()->getParam('sku'); //"1-002";
        try {
            $this->p21->gwLog('getting uom1');
            $productRepository = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
            $this->p21->gwLog('getting uom2');
            $productObj = $productRepository->get($prod);
            $this->p21->gwLog('getting uom3');
    
            $uom= $productObj->getData('sales_uom'); //$productObj->getCustomAttribute("sales_uom")-getValue();
            $this->p21->gwLog('getting uom4');
        } catch (\Exception $e1) {
                $this->p21->gwLog($e1->getMessage());
        }
        if (!isset($uom)) $uom="EA";
        if ($localpriceonly=="Magento") {
            $newprice = $productObj->getPrice();
        }
        elseif ($apidown == false || $apidown == 'false') {
            $this->p21->gwLog('calling config price api');
            try {
                $sku= $this->p21->getAltitudeSKU($productObj);
                $this->p21->gwLog('uom=' . $uom);
                $this->p21->gwLog('sku=' . $sku);
                $gcnl = $this->p21->SalesCustomerPricingSelect($cono, $custno, $sku, $whse, $whse,  '', '', '', '1',$sku,"",$uom);
            } catch (\Exception $e1) {
                $this->p21->gwLog($e1->getMessage());
            }
            try {
                if (!isset($gcnl) || isset($gcnl['fault'])) {
                    $this->p21->gwLog('error from pricing');
                    $this->p21->getSession()->setApidown(true);
                    if ($localpriceonly=="Hybrid") {
                        $newprice = $productObj->getPrice();
                    } else{
                        $newprice = 0;
                    }
                }
                $this->p21->gwLog('no error from pricing 1');
                if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') ===false   ) {
                    $newprice = $gcnl['unit_price'];
                } else {
                    $newprice = $gcnl['UnitPrice'];
                }
                $this->p21->gwLog('no error from pricing 2');
            } catch (\Exception $e1) {
                $this->p21->gwLog($e1->getMessage());
            }
        }
        if ($newprice==0 && $localpriceonly=="Hybrid") {
            $newprice = $productObj->getPrice();
        } 
        $result = $this->_resultJsonFactory->create();
        $result->setData($newprice);

        return $result;
    }
}
