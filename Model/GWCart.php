<?php

namespace Altitude\P21\Model;

use Magento\Framework\Event\ObserverInterface;

class GWCart implements ObserverInterface
{
    protected $p21;

    public function __construct(
        \Altitude\P21\Model\P21 $p21
    ) {
        $this->p21 = $p21;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->p21->gwLog("GWCart observer executing");
        $sendtoerpinv = $this->p21->getConfigValue('sendtoerpinv');
        if ($sendtoerpinv == 1) {
            $this->p21->gwLog("SendToGreywolf for sales_order_invoice_save_after");
            $invoice = $observer->getEvent()->getInvoice();
            $this->p21->SendToGreyWolf($invoice);
        } else {
            $this->p21->gwLog("SendToGreywolf not sending for sendtoerpinv = 0 ");
        }

        return true;
    }
}
