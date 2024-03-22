<?php

namespace Altitude\P21\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class AddFeeToOrderObserver implements ObserverInterface
{
    /**
     * Set payment fee to order
     *
     * @param EventObserver $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getQuote();
        $authorizedAmount = $quote->getAuthorizedAmount();
        $baseAuthorizedAmount = $quote->getBaseAuthorizedAmount();
        if (!$authorizedAmount || !$baseAuthorizedAmount) {
            return $this;
        }

        $order = $observer->getOrder();
        $order->setData('authorized_amount', $authorizedAmount);
        $order->setData('base_authorized_amount', $baseAuthorizedAmount);

        return $this;
    }
}
