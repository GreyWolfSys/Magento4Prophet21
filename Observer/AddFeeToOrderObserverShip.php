<?php

namespace Altitude\P21\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class AddFeeToOrderObserverShip implements ObserverInterface
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
        $shippingUpcharge = $quote->getUpchargeTotal();
        $baseShippingUpcharge = $quote->getBaseUpchargeTotal();
        if (!$shippingUpcharge || !$baseShippingUpcharge) {
            return $this;
        }

        $order = $observer->getOrder();
        $order->setData('upcharge_total', $shippingUpcharge);
        $order->setData('base_upcharge_total', $baseShippingUpcharge);

        return $this;
    }
}
