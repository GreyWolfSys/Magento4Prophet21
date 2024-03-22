<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Altitude\P21\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;

class View extends \Magento\Sales\Controller\Adminhtml\Order\View
{
    /**
     * View order detail
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $order = $this->_initOrder();
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($order) {
            try {
                $resultPage = $this->_initAction();
                $resultPage->getConfig()->getTitle()->prepend(__('Orders'));
            } catch (\Exception $e) {
                $this->logger->critical($e);
                $this->messageManager->addError(__('Exception occurred during order load'));
                $resultRedirect->setPath('sales/order/index');
                return $resultRedirect;
            }
            $extOrderId = "";

            if ($order->getExtOrderId()) {
                $resultPage->getConfig()->getTitle()->prepend(sprintf("#%s [%s]", $order->getIncrementId(), $order->getExtOrderId()));
            } else {
                $resultPage->getConfig()->getTitle()->prepend(sprintf("#%s", $order->getIncrementId()));
            }
            return $resultPage;
        }
        $resultRedirect->setPath('sales/*/');
        return $resultRedirect;
    }
}
