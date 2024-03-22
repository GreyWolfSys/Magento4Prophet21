<?php

namespace Altitude\P21\Controller\Checkout;

use Magento\Framework\App\Action\Context;

class Message extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_resultJson;

    public function __construct(
        Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJson
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->_resultJson = $resultJson;
    }

    /**
     * Trigger to re-calculate the collect Totals
     *
     * @return bool
     */
    public function execute()
    {
        $response = [
            'errors' => false,
            'message' => ''
        ];
        try {
            $response['message'] = $this->scopeConfig->getValue('defaults/shoppingcart/payment_message', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        } catch (\Exception $e) {
            $response = [
                'errors' => true,
                'message' => ""
            ];
        }

        /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
        $resultJson = $this->_resultJson->create();

        return $resultJson->setData($response);
    }
}
