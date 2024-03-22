<?php

namespace Altitude\P21\Block;

class Totals extends \Magento\Sales\Block\Order\Totals
{
    protected $_coreRegistry;
    protected $_scopeConfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_coreRegistry = $registry;
    	$data=[];
        parent::__construct($context, $registry, $data);
    }

    public function hideShipping()
    {
        if ($this->getShippingText() != "" || $this->getShippingTaxText() != "") {
            return true;
        }

        return false;
    }

    public function getShippingText()
    {
        $shippingTaxText = $this->getShippingTaxText();

        if ($shippingTaxText != "") {
            return $shippingTaxText;
        }

        return $this->_scopeConfig->getValue('defaults/orderemail/shipping_text');
    }

    public function getShippingTaxText()
    {
        return $this->_scopeConfig->getValue('defaults/orderemail/shipping_tax_text');
    }
}
