<?php

namespace Altitude\P21\Model\Total;

class UpchargeTotal extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
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
        \Magento\Quote\Model\QuoteValidator $quoteValidator,
        \Altitude\P21\Helper\Data $helper,
        \Altitude\P21\Model\P21 $p21
    )
    {
        $this->p21 = $p21;
        $this->quoteValidator = $quoteValidator;
        $this->helper = $helper;
        //$this->setCode('upcharge_total');
        //$this->_urlInterface = $urlInterface;
        //$this->request = $request;
    }

    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);
error_log("Starting upcharge collect... " . $total->getQuoteId());
//$url = $this->_urlInterface->getCurrentUrl();
//error_log("url=" . $url);
        $upchargeAmount = $this->getUpchargeAmount($quote, $total);

        $total->setTotalAmount('upcharge_total', $upchargeAmount);
        $total->setBaseTotalAmount('upcharge_total', $upchargeAmount);

        $total->setUpchargeTotal($upchargeAmount);
        $total->setBaseUpchargeTotal($upchargeAmount);

        $quote->setUpchargeTotal($upchargeAmount);
        $quote->setBaseUpchargeTotal($upchargeAmount);

        $total->setGrandTotal($total->getGrandTotal()+$upchargeAmount);
        $total->setBaseGrandTotal($total->getBaseGrandTotal()+$upchargeAmount);
        error_log("New Grand total: " . $quote->getGrandTotal());
error_log("ending upcharge collect" . $total->getQuoteId());        
        return $this;
    }

    protected function clearValues(Address\Total $total)
    {
       // error_log("clearing values");
       // $total->setTotalAmount('subtotal', 0);
       // $total->setBaseTotalAmount('subtotal', 0);
       // $total->setTotalAmount('tax', 0);
       // $total->setBaseTotalAmount('tax', 0);
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
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        error_log("Starting upcharge fetch... " . $total->getQuoteId());
//$url = $this->_urlInterface->getCurrentUrl();
//error_log("url=" . $url);
        $amount = $this->getUpchargeAmount($quote);
        error_log(" Grand total: " . $quote->getGrandTotal());
error_log("ending upcharge fetch" . $total->getQuoteId()); 
        return [
            'code' => 'upcharge_total',
            'title' => $this->helper->getUpchargeLabel(),
            'value' => $amount
        ];
          
    }

    public function getUpchargeAmount($quote)
    {
        $amount = 0;
        $shippingMethods = $this->helper->getUpchargeShipping();
        $paymentMethod = $this->helper->getUpchargePayment();
        $upchargePercent = $this->helper->getUpchargePercent();
        $postTax = $this->helper->getUpchargePostTax();
        $waiveAmount = $this->helper->getUpchargeWaiveAmount();
        $currentShippingMethod = $quote->getShippingAddress()->getShippingDescription();
        $currentPayment = "";
       // $url = $this->_urlInterface->getCurrentUrl();
        //error_log($url);
       // $controller = $this->request->getControllerName();
       //  error_log($controller);
       // if (strpos($url, 'checkout/cart/') !== false) {
            //error_log("skipping for cart");
            //return 0;
      //  }
        
        if ($quote->getPayment()->getMethod()) {
            $currentPayment = $quote->getPayment()->getMethodInstance()->getTitle();
        }
        if ((empty($waiveAmount) || $quote->getSubtotal() < $waiveAmount) &&
            (count($shippingMethods)==0 || in_array($currentShippingMethod, $shippingMethods) )&&
            $paymentMethod == $currentPayment
        ) {
            if ($posttax=0) {
                //error_log ("pretax");
                $amount = $quote->getSubtotal() * $upchargePercent / 100;
            } else{
                //$tax=$quote->getShippingAddress()->getData('tax_amount');
                //$ship=$quote->getShippingAmount();
               // $sub=$quote->getSubtotal();
               // $amount = ($tax + $ship + $sub) * $upchargePercent / 100;
             //  error_log("Grand total: " . $quote->getGrandTotal());
             //  error_log("Tax amt: " . $quote->getShippingAddress()->getData('tax_amount'));
             //  error_log("shipping amount: " . $quote->getShippingAddress()->getShippingAmount());
             //  error_log("subtotal: " . $quote->getSubtotal());
               $amount = ($quote->getShippingAddress()->getData('tax_amount') + $quote->getShippingAddress()->getShippingAmount() + $quote->getSubtotal()) * $upchargePercent / 100;
                //$amount = $quote->getGrandTotal() * $upchargePercent / 100;
                error_log ("Upcharge amount: " . $amount);
            }
        } else {
           
            error_log ("no upcharge!");
        }

        return $amount;
       // }
    }

    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return $this->helper->getUpchargeLabel();
    }
}
