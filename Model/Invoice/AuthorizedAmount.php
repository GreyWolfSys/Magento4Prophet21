<?php

namespace Altitude\P21\Model\Invoice;

class AuthorizedAmount extends \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
{
    public function collect(
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {
        $invoice->setAuthorizedAmount(0);
        $invoice->setBaseAuthorizedAmount(0);

        $amount = $invoice->getOrder()->getAuthorizedAmount();
        $baseAmount = $invoice->getOrder()->getBaseAuthorizedAmount();

        $invoice->setAuthorizedAmount($amount);
        $invoice->setBaseAuthorizedAmount($baseAmount);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);

        return $this;
    }
}
