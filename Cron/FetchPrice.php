<?php
declare(strict_types=1);

namespace Altitude\P21\Cron;

class FetchPrice extends \Magento\Framework\View\Element\Template
{

    protected $logger;

    /*Product collection variable*/
    protected $_productCollection;
    protected $stockFilter;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger,
        \Altitude\P21\Model\P21 $p21,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\CatalogInventory\Helper\Stock $stockFilter,
        array $data = []
  )
    {
        $this->logger = $logger;
        $this->p21 = $p21;
        $this->_productCollection= $productCollection;
        $this->stockFilter = $stockFilter;
        parent::__construct($context, $data);
    }

    public function getProductCollection()
        {

            $collection = $this->_productCollection->create();
            $collection->addAttributeToSelect('*');
            $collection->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

            // ADD THIS CODE IF YOU WANT IN-STOCK-PRODUCT
            $this->stockFilter->addInStockFilterToCollection($collection);

            return $collection;
        }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Cronjob FetchPrice is executing.");
         //$configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse', 'listorbase']);
        $configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse']);
        extract($configs);
        //  // $this->p21->gwLog( $listorbase);
        // if (!empty($listorbase)){
         $productCollection = $this->getProductCollection();
           $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "got products");
            foreach ($productCollection as $product) {
               // // $this->p21->gwLog($product->getData());
                // $this->p21->gwLog( $product->getId());
                // $this->p21->gwLog( $product->getName());
                $sku= $this->p21->getAltitudeSKU($product); //$product->getSku();
                $uom= $product->getData('sales_uom');
                if (empty($uom)) $uom="ea";
                $this->p21->gwLog( "sku=" . $sku);
                $this->p21->gwLog( "cono=" . $cono);
                $this->p21->gwLog( "whse=" . $whse);
                $this->p21->gwLog( "p21customerid=" . $p21customerid);
                try {

                    //    public function SalesCustomerPricingSelect($cono, $prod, $whse, $custno, $shipto, $qty, $moduleName = "")
                    $gcnl = $this->p21->SalesCustomerPricingSelect($cono, $sku, $whse, $p21customerid, '', '1', 'PriceCache');
                    if (!isset($gcnl) || isset($gcnl["fault"])) {
                         $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "error from pricing");
                        $this->p21->getSession()->setApidown(true);
                    // } elseif (!empty($gcnl[$listorbase]) && false) {
                    } elseif (false) {
                        $listorbase = 'base_price';
                        $price = $gcnl[$listorbase];
                            if (!empty($gcnl["pround"])){
                                switch($gcnl["pround"])
                                {
                                    case 'u';
                                        $price=\ceil($price);
                                        break;
                                    case 'd';
                                        $price=\floor($price);
                                        break;
                                    case 'n';
                                        $price=\round($price);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                         $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price=:=" . $price);
                        $product->setData("unitstock", $gcnl["oe_pricing_unit_size"]);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        if ($price > 0) {
                            //$product->setSpecialPrice($price);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();
                    } else{
                        $price = $gcnl["base_price"];
                        /*$qtybrkfl= $gcnl["qtybrkfl"] . "";
                        if (empty($qtybrkfl)){
                            $qtybrkfl='N';
                        }
                        if (!empty($qtybrkfl)){
                            $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting qtybrkfl to " . $qtybrkfl);
                            $product->setData("qtybrkfl", $qtybrkfl);
                        }
                            if (!empty($gcnl["pround"])){
                                switch($gcnl["pround"])
                                {
                                    case 'u';
                                        $price=\ceil($price);
                                        break;
                                    case 'd';
                                        $price=\floor($price);
                                        break;
                                    case 'n';
                                        $price=\round($price);
                                        break;
                                    default;
                                        break;
                                }
                            } //end pround check
                        */
                         $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price=:==" . $price);
                        $product->setData("unitstock", $gcnl["oe_pricing_unit_size"]);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        if ($price > 0) {
                            //$product->setSpecialPrice($price);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();

                    }
                } catch (\Exception $e1) {
                    $this->p21->gwLog($e1->getMessage());
                }
            }
        // }
            try{
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $_cacheTypeList = $objectManager->create('Magento\Framework\App\Cache\TypeListInterface');
                $_cacheFrontendPool = $objectManager->create('Magento\Framework\App\Cache\Frontend\Pool');
                $types = array('config','layout','block_html','collections','reflection','db_ddl','eav','config_integration','config_integration_api','full_page','translate','config_webservice');
                foreach ($types as $type) {
                    $_cacheTypeList->cleanType($type);
                }
                foreach ($_cacheFrontendPool as $cacheFrontend) {
                    $cacheFrontend->getBackend()->clean();
                }
            } catch (\Exception $e1) {
                $this->p21->gwLog($e1->getMessage());
            }
          $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Cronjob FetchPrice is complete.");

          return true;
    }
}
