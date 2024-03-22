<?php

namespace Altitude\P21\Plugin\Checkout;

class ShippingInformationManagementShip
{
    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     */
    public function __construct(
        \Magento\Quote\Model\QuoteRepository $quoteRepository
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $cartId
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    ) {
        $upchargeTotal = $addressInformation->getExtensionAttributes()->getUpchargeTotal();
        $quote = $this->quoteRepository->getActive($cartId);
        if ($upchargeTotal) {
            $quote->setUpchargeTotal($upchargeTotal);
        } else {
            $quote->setUpchargeTotal(null);
        }
    }
}
