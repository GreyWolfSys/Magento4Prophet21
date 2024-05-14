<?php
declare(strict_types=1);

namespace Altitude\P21\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchPrice extends Command
{

    protected $logger;
    private $state;
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
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\CatalogInventory\Helper\Stock $stockFilter,   
        \Magento\Framework\App\State $state,
        array $data = []
  )
    {
        $this->logger = $logger;
        $this->state = $state;
        $this->p21 = $p21;
        $this->_productCollection= $productCollection;
        $this->stockFilter = $stockFilter;    
        parent::__construct();
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
    protected function configure()
   {
       $this->setName('P21Pricing:fetchPrice');
       $this->setDescription('Fetch Prices');
       
       parent::configure();
   }
    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Console FetchPrice is executing.");
         //$configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse', 'listorbase']);
        $configs = $this->p21->getConfigValue(['apikey', 'cono', 'p21customerid', 'whse']);
        extract($configs);
        //  // $this->p21->gwLog( $listorbase);
        //if (!empty($listorbase)){
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
                    $gcnl = $this->p21->SalesCustomerPricingSelect($cono, $p21customerid, $sku, $whse, $whse, '', '', '', '1', $sku, "", $uom);
                    $this->p21->gwLog( "gcnl= " . json_encode($gcnl));
                    if (!isset($gcnl) || isset($gcnl["fault"])) 
                    {
                        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "error from pricing");
                        $this->p21->getSession()->setApidown(true);
                    } elseif (false) {
                        $listorbase = 'base_price';
                        $price = $gcnl[$listorbase];
                           
                      
                         $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "price=:=" . $price);
                        $product->setData("unitstock", $gcnl["oe_pricing_unit_size"]);
                        $product->setPrice($price);
                        $product->setFinalPrice($price);
                        if ($price > 0) {
                            //$product->setSpecialPrice(null);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();
                    } 
                    else
                    {
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
                            //$product->setSpecialPrice(null);
                        } else {
                            //$product->setSpecialPrice(null);
                        }
                        $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving");
                        $product->save();  

                    }
                } catch (\Exception $e1) {
                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR!!!" . $e1->getMessage());
                }
        //    }
        }
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
          $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Console FetchPrice is complete."); 
         
          return 1;
    }
}
