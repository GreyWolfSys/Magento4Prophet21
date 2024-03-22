<?php

namespace Altitude\P21\Model;

use Magento\Framework\Event\ObserverInterface;

class GWCartOrder implements ObserverInterface
{
    protected $p21;

    protected $resourceConnection;

    public function __construct(
        \Altitude\P21\Model\P21 $p21,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->p21 = $p21;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $sendtoerpinv = $this->p21->getConfigValue('sendtoerpinv');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderids = $observer->getEvent()->getOrderIds();
        $dbConnection = $this->resourceConnection->getConnection();

        try {
            foreach ($orderids as $orderid) {
                $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderid);
                $payment = $order->getPayment();
                $paymentMethod = (string) $payment->getMethod();

                if (strpos($paymentMethod, "authorizenet") === false && strpos($paymentMethod, "anet_") === false) {
                    $this->p21->gwLog("not Authorize: $paymentMethod");
                } else {
                    $additionalInfo = $payment->getData('additional_information');
                    $authNo = "";

                    if (isset($additionalInfo['authCode']) && $additionalInfo['authCode'] != "") {
                        $authNo = $additionalInfo['authCode'];
                        $sql = "UPDATE `sales_order` SET `CC_AuthNo`='$authNo' WHERE `entity_id`=$orderid";
                        $dbConnection->query($sql);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage());
        }

        if ($sendtoerpinv == "0") {
            try {
                foreach ($orderids as $orderid) {
                    $this->p21->gwLog("oid == " . $orderid);
                    $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderid);
                    $this->p21->gwLog("inc id = " . $order->getIncrementId());
                    $this->p21->SendToGreyWolf($order);
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Error logic
                $this->p21->gwLog("Error 1 - " . $e->getMessage());
            } catch (\Exception $e) {
                // Generic error logic
                $this->p21->gwLog("Error 2 - " . $e->getMessage());
                $order = $observer->getEvent()->getOrder();
                $this->p21->SendToGreyWolf($order);
            }
        }

        return true;
    }
}
