<?php

namespace Altitude\P21\Block;

class Tax extends \Magento\Tax\Block\Sales\Order\Tax
{
    protected $_config;
    protected $_scopeConfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Tax\Model\Config $taxConfig,
        array $data,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_config = $taxConfig;
    	$data=[];
        parent::__construct($context, $taxConfig, $data);
    }

    public function hideTax()
    {
        if ($this->getTaxText() != "" || $this->getShippingTaxText() != "") {
            return true;
        }

        return false;
    }

    public function getTaxText()
    {
        return $this->_scopeConfig->getValue('defaults/orderemail/tax_text');
    }

    public function getShippingTaxText()
    {
        return $this->_scopeConfig->getValue('defaults/orderemail/shipping_tax_text');
    }
}
