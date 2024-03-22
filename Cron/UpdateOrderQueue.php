<?php

namespace Altitude\P21\Cron;

use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class UpdateOrderQueue
{
    protected $p21;

    protected $order;

    protected $resourceConnection;

    public function __construct(
        OrderInterface $order,
        \Altitude\P21\Model\P21 $p21,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->order = $order;
        $this->p21 = $p21;
        $this->resourceConnection = $resourceConnection;
    }


    /**
       * Write to system.log
       *
       * @return void
       */
    public function execute()
    {
      //  $this->p21->gwLog('Updating ERP Order Insert Cron');
        $this->p21->gwLog("Checking Order Queue");

      //  $this->p21->gwLog('Opening DB connection');
        // Connect to the database, using the predefined database variables in /assets/repository/mysql.php

        $dbConnection = $this->resourceConnection->getConnection();

        //	$this->p21->gwLog('Checking table');
        $querycheck = 'SELECT 1 FROM `gws_GreyWolfOrderQueue`';
        $query_result = $dbConnection->query($querycheck)->execute();
        if ($query_result !== false) {
            // table exists, proceed
        } else {
            $this->p21->gwLog("Order queue table does not exist");
            exit;
        }

        //check table for orders to process

        $this->p21->gwLog('Getting results');
        $sql = "SELECT *  FROM `gws_GreyWolfOrderQueue` WHERE `dateprocessed` is null ";

        try {
            $result = $dbConnection->query($sql)->execute();
        } catch (\Exception $e) {
            $this->p21->gwLog("ERROR getting data: " . $e->getMessage());
            exit;
        }

        if (is_array($result) && count($result)) {
            $this->p21->gwLog('Records found');
            // output data of each row
            foreach ($result as $row) {
                //submit orders
                $order1 = $this->order->loadByIncrementId($row["orderid"]);// order->loadByIncrementId
                if ($order1->hasInvoices()) {
                    $invIncrementId = [];
                    foreach ($order1->getInvoiceCollection() as $invoice) {
                        //$invoiceIncId[] = $invoice->getIncrementId();
                        $this->p21->gwLog('Submitting Order');
                        if ($this->p21->SubmitOrder($invoice) == true) {
                            //update queue table to not check future orders
                            $this->p21->UpdateOrderQueue($row["orderid"]);
                            break;
                        }
                    }
                }
            }
        } else {
            $this->p21->gwLog("0 results");
        }
    }
}
