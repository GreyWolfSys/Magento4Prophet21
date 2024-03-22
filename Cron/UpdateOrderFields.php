<?php

namespace Altitude\P21\Cron;

use Magento\Sales\Api\Data\OrderInterface;

class UpdateOrderFields
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
        $this->p21->gwLog('Updating ERP Order Field Cron');
        //$this->p21->gwLog("Checking Order Field Table");
        //$this->p21->gwLog('Opening DB connection');

        $dbConnection = $this->resourceConnection->getConnection();

        //	$this->p21->gwLog('Checking table');
        $querycheck = 'SELECT 1 FROM `gws_GreyWolfOrderFieldUpdate`';
        $query_result = $dbConnection->query($querycheck)->execute();
        if ($query_result !== false) {
            // table exists, proceed
        } else {
            $this->p21->gwLog("Order field update table does not exist");
            exit;
        }

        try {
            $sqlUpdate="UPDATE sales_order_grid AS g INNER JOIN sales_order AS o ON o.entity_id=g.entity_id AND o.increment_id=g.increment_id SET g.ext_order_id=o.ext_order_id WHERE  o.ext_order_id IS NOT NULL AND o.ext_order_id !=''";
            $dbConnection->query($sqlUpdate);
        } catch (\Exception $e) {
            $this->p21->gwLog("ERROR updating data: " . $e->getMessage());
        }
        //check table for orders to process

        $this->p21->gwLog('Getting results');
        $sql = "SELECT *  FROM `gws_GreyWolfOrderFieldUpdate` WHERE `dateprocessed` is null; ";

        try {
            $result = $dbConnection->fetchAll($sql);
        } catch (\Exception $e) {
            $this->p21->gwLog("ERROR getting data: " . $e->getMessage());
            exit;
        }

        try {
           if (isset($result)) {
                //$this->p21->gwLog($result->num_rows . ' records found');
                // output data of each row
                foreach ($result as $row) {
                    try {
                        $DateEntered = $row["dateentered"];
                        if (isset($row["ERPOrderNo"])) {
                            $erpOrderNo = $row["ERPOrderNo"] . '';
                        } else {
                            $erpOrderNo = "";
                        }
                        if (isset($row["ERPSuffix"])) {
                            $ERPSuffix = $row["ERPSuffix"] . '';
                        } else {
                            $ERPSuffix = "";
                        }
                        if (isset($row["CCAuthNo"])) {
                            $CCauthNo = $row["CCAuthNo"] . '';
                        } else {
                            $CCauthNo = "";
                        }
                        // $this->p21->gwLog("Building query");
                        $sql = "update `sales_order` set `status`=`status` ";
                        if ($erpOrderNo != "") {
                            $sql .= ", `P21_OrderNo`='" . $erpOrderNo . "'";
                        }

                        //	$this->p21->gwLog($sql);
                        if ($ERPSuffix != "") {
                            $sql .= ", `P21_OrderSuf`='" . $ERPSuffix . "'";
                        }
                        //	$this->p21->gwLog($sql);

                        if ($CCauthNo != "") {
                            $sql .= ", `CC_AuthNo`='" . $CCauthNo . "'";
                        }
                        //	$this->p21->gwLog($sql);

                        $sql .= " where `increment_id`='" . $row["orderid"] . "' ";
                        //	$this->p21->gwLog($sql);
                        if ($dbConnection->query($sql)->execute() === true) {
                            // echo "New record created successfully";
                            $this->p21->gwLog("Order field  updated successfully for order " . $row["orderid"]);
                            $this->p21->UpdateOrderFieldProcessed($row["orderid"], $DateEntered);
                        } else {
                            // echo "Error: " . $sql . "<br>" . $dbConnection->error;
                            $this->p21->gwLog("Update  Error: " . $sql . "..." . $dbConnection->error . " ... order " . $row["orderid"]);
                        }
                    } catch (\Exception $e) {
                        $this->p21->gwLog("Failed to insert update order field table: " . $e->getMessage());
                    }

                    //	echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
                }
            } else {
                $this->p21->gwLog("0 results");
            }
        } catch (\Exception $e) {
            $this->p21->gwLog("ERROR! getting data: " . $e->getMessage());
            exit;
        }
    }
}
