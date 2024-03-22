<?php

namespace Altitude\P21\Cron;

class Payments
{
    protected $p21;

    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Altitude\P21\Model\P21 $p21,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->p21 = $p21;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $configs = $this->p21->getConfigValue(['apikey', 'cono']);
        extract($configs);

        $this->p21->gwLog("Starting payment cron---");
        $dbConnection = $this->resourceConnection->getConnection();
        try {
            $sql = "select * from `sales_order` where `CC_AuthNo` is not null and `CC_AuthNo` != '' and `status` != 'complete' and `status` != 'closed';";
            $this->p21->gwLog("checking orders to invoice");
            $result = $dbConnection->query($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $collection = $objectManager->create('Magento\Sales\Model\Order');
            try {
                if ((count($result) > 0)) {
                    //this was successful
                }
            } catch (\Exception $e) {
                //$this->p21->gwLog("Failed to open update order table 1: " . $e->getMessage());
                return "";
            }
            if (count($result) > 0) {
                // output data of each row
                $this->p21->gwLog($result->num_rows . ' CC records found');
                foreach ($result as $row) {
                    $incrementid = $row["increment_id"];
                    $authno = $row["CC_AuthNo"];
                    $erpOrderNo = $row["P21_OrderNo"];
                    //$P21_OrderSuf = $row["P21_OrderSuf"];
                    $this->p21->gwLog("inc=" . $incrementid);
                    $this->p21->gwLog("order=" . $erpOrderNo);
                    $order = $collection->loadByIncrementId($incrementid);// order->loadByIncrementId

                    if ($order->canInvoice()) {
                        // $invIncrementId = array();

                        $gcOrder = $this->p21->SalesOrderSelect($cono, $erpOrderNo);

                        if (isset($gcOrder)) { //
                            if ($gcOrder["cono"] != "0") {
                                $item = $gcOrder;
                                if (isset($item["orderno"])) {
                                    if ($item["stagecd"] >= 3) {
                                        $this->p21->gwLog("Order is good to process...stage " . $item["stagecd"]);
                                        if (!$order->hasInvoices()) {
                                            $this->p21->gwLog("Already invoiced");
                                            $invoice = $this->_invoiceService->prepareInvoice($order);
                                            $invoice->register();
                                            $invoice->save();
                                            $transactionSave = $this->_transaction
                                                ->addObject($invoice)
                                                ->addObject($invoice->getOrder());
                                            $transactionSave->save();
                                            $this->invoiceSender->send($invoice);
                                            //send notification code
                                            $order->addStatusHistoryComment(
                                                __('Notified customer about invoice #%1.', $invoice->getId())
                                            )
                                            ->setIsCustomerNotified(true)
                                            ->save();
                                        } else {
                                            $this->p21->gwLog("Getting order status");
                                        }
                                    } else {
                                        $this->p21->gwLog("Order will not process...stage " . $item["stagecd"]);
                                    }
                                }
                            } else {
                                $this->p21->gwLog("no order found");
                            } //is set item
                        } else {
                            $this->p21->gwLog("GC call fail");
                        } //end is set gc call
                    } //end can invoice
                }//end has no invoice
            } else {
                $this->p21->gwLog("0 results");
            }
        } catch (\Exception $e) {
            $this->p21->gwLog("Failed to open update order table: " . $e->getMessage());
        }

        return true;
    }
}
