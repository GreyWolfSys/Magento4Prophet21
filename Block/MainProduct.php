<?php

namespace Altitude\P21\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Template;

class MainProduct extends Template
{
    protected $_registry;
    private $p21;
    private $helper;
    private $productRepository;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Altitude\P21\Model\P21 $p21,
        \Altitude\P21\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        array $data = []
    ) {
        $this->_registry = $registry;
        $this->p21 = $p21;
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        parent::__construct($context, $data);
    }

    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getCurrentCategory()
    {
        return $this->_registry->registry('current_category');
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }


  function ShowInfo($item) {
    $qtyonhand=$item["qtyonhand"];
	$qtyreservd=$item["qtyreservd"];
	$qtycommit=$item["qtycommit"];

	$AvailQty=$qtyonhand-$qtyreservd-$qtycommit;
	$itemresult = "<strong>" . TrimWHSEName($item["whsename"],"-") . ":</strong> " . $AvailQty . "<br>";

    return $itemresult;
}


    public function getQtyInfo($product)
    {
        return $this->helper->getQtyInfo($product);
    }

    public function getPriceInfo($product)
    {
        return $this->helper->getPriceInfo($product);
    }
 public function getProduct($sku)
    {
        if ($sku) {
            $product = $this->productRepository->get($sku);

            if ($product->getId()) {
                return $product;
            }
        }

        return false;
    }
}
