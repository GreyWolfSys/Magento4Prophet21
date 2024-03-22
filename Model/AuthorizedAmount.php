<?php

namespace Altitude\P21\Model;

class AuthorizedAmount extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /**
      * Collect grand total address amount
      *
      * @param \Magento\Quote\Model\Quote $quote
      * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
      * @param \Magento\Quote\Model\Quote\Address\Total $total
      * @return $this
      */
    protected $quoteValidator = null;

    public function __construct(
        \Magento\Quote\Model\QuoteValidator $quoteValidator
    )
    {
        $this->quoteValidator = $quoteValidator;
        $this->setCode('authorized_amount');
    }

    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $authorizedAmount = $this->getAuthorizedAmount($quote, $total);

        $total->setTotalAmount('authorized_amount', $authorizedAmount);
        $total->setBaseTotalAmount('authorized_amount', $authorizedAmount);

        $total->setAuthorizedAmount($authorizedAmount);
        $total->setBaseAuthorizedAmount($authorizedAmount);

        $quote->setAuthorizedAmount($authorizedAmount);
        $quote->setBaseAuthorizedAmount($authorizedAmount);

        $total->setGrandTotal($total->getGrandTotal());
        $total->setBaseGrandTotal($total->getBaseGrandTotal());

        return $this;
    }

    protected function clearValues(Address\Total $total)
    {
        $total->setTotalAmount('subtotal', 0);
        $total->setBaseTotalAmount('subtotal', 0);
        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $total->setTotalAmount('discount_tax_compensation', 0);
        $total->setBaseTotalAmount('discount_tax_compensation', 0);
        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setSubtotalInclTax(0);
        $total->setBaseSubtotalInclTax(0);
    }

    /**
     * Assign subtotal amount and label to address object
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param Address\Total $total
     * @return array
     */
    public function fetch(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $authorizedAmount = $this->getAuthorizedAmount($quote);

        return [
            'code' => 'authorized_amount',
            'title' => $this->getLabel(),
            'value' => $authorizedAmount
        ];
    }

    public function getAuthorizedAmount($quote)
    {
        $amount = 0;
        $subTotals = $quote->getSubtotal();
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress) {
            $subTotals += $shippingAddress->getTaxAmount();
            $subTotals += $shippingAddress->getShippingAmount();
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $authorizedAmount = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('defaults/authorized_amount/authorized_amount');

        if (isset($authorizedAmount) && strpos($authorizedAmount, "%") !== false) {
            $authorizedAmount = str_replace("%", "", $authorizedAmount);
            $amount = $subTotals * $authorizedAmount / 100;
        } else {
            $amount = (float) $authorizedAmount;
        }

        return $amount;
    }

    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return "Authorized Amount";
    }
}
