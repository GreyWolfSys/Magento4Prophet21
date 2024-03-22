<?php

namespace Altitude\P21\Block;

class Balances extends OrderQuery
{
    protected $p21;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Altitude\P21\Model\P21 $p21,
        array $data = []
        ) {
        $this->_context = $context;
        $this->p21 = $p21;
        parent::__construct($context, $data);
    }

    public function getBalances()
    {
        $cono = $this->p21->getConfigValue('cono');
        $customer = $this->p21->getSession()->getCustomer();
        $p21CustNo = $customer->getData('p21_custno');

        $result = "";
        $p21Customer = $this->p21->SalesCustomerSelect($cono, $p21CustNo);

        return ['p21Customer' => $p21Customer];
    }
}
