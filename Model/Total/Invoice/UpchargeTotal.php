<?php

namespace Altitude\P21\Model\Total\Invoice;

class UpchargeTotal extends \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
{
    public function collect(
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {
        $invoice->setUpchargeTotal(0);
        $invoice->setBaseUpchargeTotal(0);

        $amount = $invoice->getOrder()->getUpchargeTotal();
        $baseAmount = $invoice->getOrder()->getBaseUpchargeTotal();

        $invoice->setUpchargeTotal($amount);
        $invoice->setBaseUpchargeTotal($baseAmount);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);

        return $this;
    }
}
