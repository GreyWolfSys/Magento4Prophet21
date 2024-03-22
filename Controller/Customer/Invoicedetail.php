<?php

namespace Altitude\P21\Controller\Customer;

class Invoicedetail extends \Altitude\P21\Controller\CustomerAbstract
{/**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    protected $productRepository;

    protected $_cart;

    protected $p21;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->_customerSession = $customerSession;
        parent::__construct($context, $customerSession);
        $this->p21 = $p21;
        $this->productRepository = $productRepository;
        $this->_cart = $cart;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPost();

        if (isset($data["reorderitems"]) && $data["reorderitems"] == "yes") {
            $itemsadded = 0;
            $lineno = 0;

            for ($i = 1; $i <= $data["totalitems"]; $i++) {

                if (isset($product)) {
                    unset($product);
                }

                if (isset($data['reorder' . $i])) {
                    $lineno = $lineno + 1;
                    $itemsadded += 1;

                    $sku = $data["reorderitem" . $i];
                    $qty = $data["reorderqty" . $i];

                    try {
                        $product = $this->productRepository->get($sku);
                        $this->p21->gwLog($i . "reorder product: " . $product->getId());
                    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                        $this->p21->gwLog('Product Error: ' . json_encode($e->getMessage()));
                        $this->messageManager->addErrorMessage( __('Product is not found in the catalog.') );
                    }

                    if (isset($product)) {
                        $this->p21->gwLog('Getting prod id ' . $product->getId());
                        $params = [
                            'product' => $product->getId(),
                            'qty' => $qty
                        ];
                        $this->_cart->addProduct($product, $params);
                        $this->_cart->save();
                    }
                }
            }
        }

        $this->_view->loadLayout();

        $this->_view->renderLayout();
    }
}
