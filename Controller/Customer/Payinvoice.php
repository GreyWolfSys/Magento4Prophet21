<?php

namespace Altitude\P21\Controller\Customer;

class Payinvoice extends \Altitude\P21\Controller\CustomerAbstract
{
    protected $_product = null;

    protected $_registry;

    protected $_productFactory;

    protected $io;

    protected $p21;

    protected $dir;

    protected $checkoutSession;

    protected $storeManager;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Altitude\P21\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Altitude\P21\Model\P21 $p21
    ) {
        parent::__construct($context, $customerSession);
        $this->_registry = $registry;
        $this->_productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->_context = $context;
        $this->_cart = $cart;
        $this->p21 = $p21;
        $this->directoryList = $dir;
        $this->io = $io;
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $formater = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
        $moduleName = $this->p21->getModuleName(get_class($this));
        $customer = $this->p21->getSession()->getCustomer();
        $data = $this->getRequest()->getPost();
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'invstartdate']);
        extract($configs);

        if (isset($data["payinvoice"])) {
            $p21CustNo = ($customer['p21_custno'] > 0) ? $customer['p21_custno'] : $p21customerid;
            $emptyCart = $this->p21->getConfigValue('defaults/shoppingcart/emptyallnoninvoice');
            $webID = $this->storeManager->getStore()->getWebsiteId();

            if ($emptyCart) {
                $cart = $this->_cart;
                $quoteItems = $this->checkoutSession->getQuote()->getItemsCollection();
                foreach ($quoteItems as $item) {
                    if (strpos($item->getName(), "Invoice") === false) {
                        $cart->removeItem($item->getId())->save();
                    }
                }
                #$this->_cart->save();
            }

            #$invoicesList = $this->p21->SalesCustomerInvoiceList($cono, $p21CustNo, $moduleName);
            #$invoice = null;
//var_dump($data);
//exit;
            foreach ($data['payinvoice'] as $invorderno) {
                $sku = $p21CustNo . '-' . $invorderno;
                $amount = $data['invoiceamount'][$invorderno];

                try {
                    $_product = $this->productRepository->get($sku);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $_product = $this->_objectManager->create('Magento\Catalog\Model\Product');
                }

                $this->p21->gwLog('Adding to cart');
                $this->p21->gwLog('Customer ' . $p21CustNo . ' Invoice ' . $invorderno);
                $this->p21->gwLog('Amount ' . $amount);

                $_product->setName('Customer ' . $p21CustNo . ' Invoice ' . $invorderno);
                //$_product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
                $_product->setAttributeSetId(4);
                $_product->setSku($sku);
                $_product->setWebsiteIds([$webID]);
                $_product->setTaxClassId(0);
                $_product->setTypeId('virtual'); 
                $_product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
                $_product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
                $_product->setPrice($amount);

                $imageData = $this->helper->getProductImageData();
                $imageFile = $this->directoryList->getPath('media') . '/import/paid_invoice.jpg';

                if (!$this->io->fileExists($imageFile)) {
                    $this->io->write($imageFile, $this->helper->getProductImageData(), 0644);
                }

                $_product->addImageToMediaGallery($imageFile, ['image', 'small_image', 'thumbnail'], false, false);

                $params = [
                    'product' => $sku,
                    'price' => $amount,
                    'qty' => 1
                ];

                $_product->setStockData([
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'min_sale_qty' => 1,
                    'max_sale_qty' => 1,
                    'is_in_stock' => 1,
                    'qty' => 1
                ]);

                $_product->setPrice($amount);
                $_product->setIsSuperMode(true);
                $_product->setCustomPrice($amount);
                $_product->setOriginalCustomPrice($amount);
                $_product->save();

                try {
                  //  $this->p21->gwLog('Adding to cart for reelzies');
                    $this->_cart->addProduct($_product, $params);
                } catch (\Exception $e) {
                  //  $this->p21->gwLog('Error adding to cart' . $e->getMessage());
                }
            }

            $this->_cart->save();
            $this->_redirect('checkout/cart');
            return;
        }

        $this->_redirect("/");
        return;
    }
}
