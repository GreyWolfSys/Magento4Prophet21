<?php

namespace Altitude\P21\Cron;

// auth.net include
//require 'vendor/autoload.php';

 /*need to make these work with M2 for auth.net*/
use Magento\Framework\App\Request\Http;

use Magento\Sales\Api\Data\OrderInterface;
//use net\authorize\api\contract\v1 as AnetAPI;
//use net\authorize\api\controller as AnetController;
use Psr\Log\LoggerInterface;

/*this is for auth.net*/
define("AUTHORIZENET_LOG_FILE", "phplog");
//merchant credentials -- will need to come from auth.net settings in admin
const MERCHANT_LOGIN_ID = "5KP3u95bQpv";
const MERCHANT_TRANSACTION_KEY = "346HZ32z3fP4hTG2";

class UpdatePackageShipping
{
    protected $order;

    protected $p21;

    protected $resourceConnection;

    public function __construct(
        \Altitude\P21\Model\P21 $p21,
        OrderInterface $order,
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
        $this->p21->gwLog('Updating shipping from erp');
        $this->p21->gwLog("Checking Packages Queue");
        $dbConnection = $this->resourceConnection->getConnection();
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid','autoinvoice']);
        extract($configs);

$this->p21->gwLog('Updating shipping from erp');
        $this->p21->gwLog("Checking Packages Queue");
        if (1==1) {
            //$sql = "select * from `sales_order` where `CC_AuthNo` is not null and `CC_AuthNo` != '' and `status` != 'complete';";
			$sql = "select distinct sales_order.* from `sales_order` LEFT JOIN gws_GreyWolfOrderFieldUpdate ON sales_order.increment_id=gws_GreyWolfOrderFieldUpdate.orderid where `ext_order_id` is not null and `ext_order_id` != '' and `status` != 'complete' and `status` !='canceled'  AND updated_at > DATE_SUB(CURDATE(),INTERVAL 60 day) ORDER BY entity_id desc;";
          
            $result = $dbConnection->fetchAll($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if (count($result)) {
                // output data of each row
                $this->p21->gwLog(count($result) . ' CC records found');
                foreach ($result as $row) {
                    $incrementid = $row["increment_id"];
                    $authno = $row["CC_AuthNo"];
                    $orderfields=$row["ext_order_id"];
                    //$erpOrderNo = $row["P21_OrderNo"];
                    $erpOrderNo = $row["ext_order_id"];
                    $P21_OrderSuf = $row["P21_OrderSuf"];
					$this->p21->gwLog("checking " . $erpOrderNo);

                    $order = $this->order->loadByIncrementId($incrementid);// order->loadByIncrementId
                    if ($order->canShip()) {
                        
                        $this->p21->gwLog("Getting packages");
////don't need invoices in place for this
                            // $invIncrementId = array();

                            $gcOrder = $this->p21->SalesOrderSelect($cono, $erpOrderNo);
                            if (isset($gcOrder)) {
                                if(isset($gcOrder['cancel_flag']) && $gcOrder['cancel_flag'] == 'Y') {
                                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "closing order"); 
                                    $orderidnow = $order->getIncrementId();
                                    $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
                                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "got order..");
                                    //set order to complete
                                    $statusCode = "canceled";
                                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "setting status..");
                                    $order1->setState($statusCode)->setStatus($statusCode);
                                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "saving order..");
                                    $order1->save();
                                    $this->p21->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order canceled..");
                                    unset($order1);
                                    continue;
                                }
                            }

                            $gcpackage = $this->p21->SalesPickTicketList($cono, $erpOrderNo, $P21_OrderSuf);

                            if (isset($gcpackage)) {
                                if (isset($gcpackage["company_id"]) && $gcpackage["company_id"] != "0") {
                                    //foreach ($gcpackage["SalesPackagesSelectResponseContainerItems"] as $item){
                                    if ($autoinvoice=="0") { //invoice if shipped...for paradox labs, will auto-capture
                                        $payment = $order->getPayment();
                                        try{
                                            if (isset($payment)) {
                                                $method = $payment->getMethodInstance();
                                                $methodTitle = $method->getTitle();
                                                $methodcode = $payment->getMethod(); 
                                                if (strpos($methodcode, "authnetcim")!== false && false){
                                                    if ($order->canInvoice()) {
                                                        $invoiceItems = [];
                                                        $this->p21->gwLog('Order"  . $order->getIncrementId() . " invoicing  now');
                                                        
                                                        //*********
                                                        //get list of orders from ERP
                                                        try 
                                                        {

                                                            $gcnl = $this->p21->SalesOrderList($cono, $custno, "", "", $order->getIncrementId() , "N", "","","","N");
                                                            $noorder = false;
                                        
                                                            if (isset($gcnl["ErrorCode"])) {
                                                                if ($gcnl["ErrorCode"] != "") {
                                                                    $noorder = true;
                                                                } else {
                                                                    $noorder = false;
                                                                }
                                                            } else {
                                                                $noorder = false;
                                                            }
                                        
                                                            if ($noorder == true) {
                                                                continue;
                                                            
                                                            }
                                                            //check if suffix already processed
                                                            
                                                            //end suffix check
                                                                    $shipQty=[];
                                                                    $orderDetail = $this->p21->SalesOrderLinesSelect($cono, $erpOrderNo);
                                                                    if (isset($orderDetail["SalesOrderLinesSelectResponseContainerItems"])) {
                                                                        foreach ($orderDetail["SalesOrderLinesSelectResponseContainerItems"] as $lineitem) {
                                                                            //$ordersuflist[]=str_pad($item["ordersuf"], 2, "0", STR_PAD_LEFT);
                                                                            $shipQty[]=array($lineitem["item_id"],$lineitem["qty_on_pick_tickets"]);
                                                                        }
                                                                    } else {
                                                                        //$ordersuflist[]=str_pad($orderDetail["ordersuf"], 2, "0", STR_PAD_LEFT);
                                                                        $shipQty[]=array($orderDetail["item_id"],$orderDetail["qty_on_pick_tickets"]);
                                                                    }

                                                            if (count($shipQty)>0) {
                                                                foreach ($order->getAllVisibleItems() as $orderItem) {
                                                                    $invoiceItems[$orderItem->getOrderItemId()] = $orderItem->getQty();
                                                                }
                                                            }
                                                        } catch (\Exception $e) {
                                                            $this->p21->gwLog("GWS autoinvoice on ship Error: " . $e->getMessage());
                                                           
                                                        }
                                                        
                                                        //end get list of orders
                                                        try {
                                                            $invoices = $this->invoiceCollectionFactory->create()->addAttributeToFilter('order_id', ['eq' => $order->getId()]);
                                                            //$this->p21->gwLog("order " . $order->getId());
                                                            $invoices->getSelect()->limit(1);
                                
                                                           /* if ((int)$invoices->count() !== 0) {
                                                                //return null;
                                                            }*/
                                                            
      
                                //https://webkul.com/blog/how-to-programmatically-create-invoice-in-magento2/
                                                            if (count($invoiceItems)>0){
                                                                $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);
                                                            } else {
                                                                $invoice = $this->invoiceService->prepareInvoice($order);    
                                                            }
                                                            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                                                            $invoice->register();
                                                            $invoice->getOrder()->setCustomerNoteNotify(false);
                                                            $invoice->getOrder()->setIsInProcess(true);
                                                            $order->addStatusHistoryComment('Automatically INVOICED', false);
                                                            $transactionSave = $this->transactionFactory->create()
                                                                ->addObject($invoice)
                                                                ->addObject($invoice->getOrder());
                                                            $transactionSave->save();
                                                        } catch (\Exception $e) {
                                                            $this->p21->gwLog('Exception message: ' . $e->getMessage());
                                                            $order->addStatusHistoryComment('Exception message:: ' . $e->getMessage(), false);
                                                            $order->save();
                                                            //return null;
                                                        }
                                                        //*********
                                                    }
                                                } else {
                                                    
                                                }
                                            }
                                        } catch (\Exception $ePO) {
                                            $this->p21->gwLog("Payment error: " . $ePO->getMessage());
                                        }
                                         
                                    }//end if autoinvoice==0
                                }
                            }
                        if ($order->hasInvoices() ) {
                            $gcpackage = $this->p21->SalesPickTicketList($cono, $erpOrderNo, $P21_OrderSuf);

                            if (isset($gcpackage)) {
                                if (isset($gcpackage["company_id"]) && $gcpackage["company_id"] != "0") {
                                    //foreach ($gcpackage["SalesPackagesSelectResponseContainerItems"] as $item){
                                    $item = $gcpackage;
                                    if (isset($item["tracking_no"])) {
                                        $this->p21->gwLog("track: " . $item["tracking_no"]);
                                        foreach ($order->getInvoiceCollection() as $invoice) {
                                            try {
                                                // Initialize the order shipment object
                                                $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
                                                $shipment = $convertOrder->toShipment($order);
                                                $ordertotal = 0;
                                                $qtyShipped = 0;
                                                // Loop through order items
                                                foreach ($order->getAllItems() as $orderItem) {
                                                    // Check if order item has qty to ship or is virtual
                                                    if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                                                        continue;
                                                    }
                                                    unset($qtyshipped);
                                                    unset($price);
                                                    unset($linetotal);
                                                    $this->p21->gwLog("getting lines");
                                                    $gcLines = $this->p21->SalesOrderLinesSelect($cono, $erpOrderNo);
                                                    if (isset($gcLines)) {
                                                        if (!isset($gcLines["errordesc"])) {
                                                            //foreach ($gcLines as $itemline) {
                                                                $itemline=$gcLines;
                                                                if ($itemline["item_id"] == $orderItem->getSku()) {
                                                                    $qtyShipped = $itemline["qty_on_pick_tickets"];
                                                                    $price = $itemline["unit_price"];
                                                                    $linetotal = $qtyShipped * $price;
                                                                    $ordertotal = $ordertotal + $linetotal;
                                                                }
                                                            //}
                                                        } else {
                                                            
                                                            foreach ($gcLines as $itemline) {
                                                                //$itemline=$gcLines;
                                                                if ($itemline["item_id"] == $orderItem->getSku()) {
                                                                    $qtyShipped = $itemline["qty_on_pick_tickets"];
                                                                    $price = $itemline["unit_price"];
                                                                    $linetotal = $qtyShipped * $price;
                                                                    $ordertotal = $ordertotal + $linetotal;
                                                                }
                                                            }
                                                        }
                                                    } 
                                                    $qtyShipped = $orderItem->getQtyToShip();

                                                    // Create shipment item with qty
                                                    $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                                                    // Add shipment item to shipment
                                                    $shipment->addItem($shipmentItem);
                                                }

                                                // Register shipment
                                                $shipment->register();
                                                $shipment->getOrder()->setIsInProcess(true);

                                                try {
                                                    // Save created shipment and order
                                                    $shipment->save();
                                                    $shipment->getOrder()->save();
                                                    $shipment->save();
                                                } catch (\Exception $e) {
                                                    $this->p21->gwLog("Shipment error: " . $e->getMessage());
                                                }
                                            } catch (\Exception $e) {
                                                $this->p21->gwLog('Exception message: ' . $e->getMessage());
                                                // $order->save();
                                            }
                                        }
                                    }

                                    $checkstage = true;
                                } else {
                                    $this->p21->gwLog("item set fail");
                                    $checkstage = true;
                                }
                            } else {
                                $this->p21->gwLog("GC call fail");
                                $checkstage = true;
                            } //end is set gc call

                            if ($checkstage == true) {
                                // $this->p21->gwLog ("Checking stages");
                                // $gcOrder = $this->p21->SalesOrderSelect($cono, $erpOrderNo);

                                if (isset($gcOrder)) { //
                                    if (isset($gcOrder["company_id"]) && $gcOrder["company_id"] != "0") {
                                        $item = $gcOrder;
                                        if (isset($item["order_no"])) {
                                            #if ($item["stagecd"] >= 4) {
                                            $this->p21->gwLog("Order " . $erpOrderNo . " is good to process...stage " . $item["stagecd"]);
                                            //settle order
                                            $cust = $item["customer_id"];
                                            $this->Settlement($order, $cust);
                                            #}
                                        }
                                    }
                                }
                            }
                        } //end has invoices
                    }//end canship
                }
            } else {
                $this->p21->gwLog("0 results");
            }
        }

        return 1;
    }

    //end process tracking

    public function Settlement($order, $cust)
    {
        $this->p21->gwLog("settlement processing");

        global $p21customerid;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $processor = $this->p21->getConfigValue('payments/payments/processor');

        $orderincid = $order->getIncrementId();
        $orderid = $order->getId();
        $payment = $order->getPayment();

        $sendpaymenttoERP = false;
        // $this->p21->gwLog("settlement data:");
        $SavedFieldData = $this->GetSavedFieldData($orderincid);
        if ($processor == "Authorize.NET") {
            $transactionID = $payment->getData('last_trans_id');
            $response = $this->capturePreviouslyAuthorizedAmount($order, $transactionID);
            $pos = strpos($response, "This transaction has been approved.");
            if ($pos === false) { //https://developer.authorize.net/api/reference/index.html#payment-transactions
                $sendpaymenttoERP == false;
                $this->p21->gwLog("Payment settlement failed. " . $response);
            } else {
                $sendpaymenttoERP = true;
            }
        }
        //    $this->p21->gwLog("Payment push:");
        if ($sendpaymenttoERP == true) {
            $this->p21->gwLog("Sending payment:");
            if (isset($cust)) {
                $custno = $cust;
            } else {
                $custno = $p21customerid;
            }

            $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
            $customerData = $customerSession2->getCustomer();
            if ($order->getCustomerIsGuest()) {
                //  $this->p21->gwLog ("customer is guest");
                $custno = $p21customerid;
            } else {
                $CustomerID = $order->getCustomerId();

                if (!$custno) {
                    // Not Logged In
                    $custno = $p21customerid;
                    //	$this->p21->gwLog ("p21 custno is default");
                }
            } //if ($order->getCustomerIsGuest()){
            // $this->p21->gwLog ("payment sending");
            $gcPay = $this->p21->SalesOrderPaymentInsert($custno, $SavedFieldData["ERPOrderNo"], $amt);
            // $this->p21->gwLog ("payment set");
            if (isset($gcPay)) { //
                // $this->p21->gwLog ("payment data rcvd");
                if ($gcPay["company_id"] != "0") {
                    //	$this->p21->gwLog ("pmt valid");
                    if (isset($gcPay["invoice_no"])) {
                        //     	$this->p21->gwLog ("pmt has order");
                        if ($gcPay["invoice_no"] != "0") {
                            //   	$this->p21->gwLog ("pmt still has order");
                            $this->p21->gwLog("Order " . $gcPay["invoice_no"] . "  has payment applied");
                            //settle order

                            //insert payment to erp
                            //processing is not done yet.
                            $orderidnow = $SavedFieldData["orderid"];

                            $this->p21->gwLog("order closing " . $orderidnow);
                            // $order1 = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($orderidnow);
                            $order1 = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderidnow);
                            $this->p21->gwLog("got order");
                            //set order to complete
                            $statusCode = "complete";
                            $this->p21->gwLog("setting status");
                            $order1->setState($statusCode)->setStatus($statusCode);
                            $this->p21->gwLog("saving order");
                            $order1->save();
                            $this->p21->gwLog("order complete");
                            // $payment->setIsTransactionClosed(1);
                        }
                    }
                }
            }
        }
    }

    public function GetSavedFieldData($orderincid)
    {
        $dbConnection = $this->resourceConnection->getConnection();

        try {
            $sql = "select orderid,ERPOrderNo,ERPSuffix, CCAuthNo, dateentered, dateprocessed, TransactionID, STAN, LocalDateTime, TXNDateTime, CCNo, CCExp, CCCCV, AuthID, TxnAmt, RefNum, ClientRef, CardType, ResponseCode,CardLevelResult,ACI FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderincid . "' and TransactionID is not null";

            $this->p21->gwLog($sql);
            $result = $dbConnection->query($sql)->execute();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if (count($result)) {
                // output data of each row
                $this->p21->gwLog($result->num_rows . ' CC records found');
                foreach ($result as $row) {
                    unset($CCSaveFields);
                    //$incrementid=$row[""];

                    $CCSaveFields = [
                        'TransactionID' => $row["TransactionID"] . "",
                        'STAN' => $row["STAN"] . "",
                        'LocalDateTime' => $row["LocalDateTime"] . "",
                        'TXNDateTime' => $row["TXNDateTime"] . "",
                        'CCNo' => $row["CCNo"] . "",
                        'CCExp' => $row["CCExp"] . "",
                        'CCCCV' => $row["CCCCV"] . "",
                        'AuthID' => $row["AuthID"] . "",
                        'TxnAmt' => $row["TxnAmt"] . "",
                        'RefNum' => $row["RefNum"] . "",
                        'ClientRef' => $row["ClientRef"] . "" ,
                        'ResponseCode' => $row["ResponseCode"] . "",
                        'ERPOrderNo' => $row["ERPOrderNo"] . "" ,
                        'ERPSuffix' => $row["ERPSuffix"] . "",
                        'CardType' => $row["CardType"] . "",
                        'CardLevelResult' => $row["CardLevelResult"],
                        'ACI' => $row["ACI"],
                        'orderid' => $row["orderid"],
                        'TXNResponse' => $row["TXNResponse"]
                    ];
                }

                return $CCSaveFields;
            }
        } catch (\Exception $e) {
            $this->p21->gwLog("Failed to open update order table:: " . $e->getMessage());
        }
    }

   /* public function GenerateClientRef(\GMFMessageVariants $gmfMesssageObj)
    {
        $rctppid = $this->p21->getConfigValue('payments/rapidconnect/rctppid');
        $length = 12 - strlen('V' . $rctppid);
        $rand = rand(pow(10, $length - 1), pow(10, $length) - 1);
        $clientRef = '00' . $rand . 'V' . $rctppid;

        return $clientRef;
    }*/

   /* public function CreateCreditSaleRequest($SavedFieldData)
    {
        $currdatestr = date('Ymdhis', time());
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rctppid = $this->p21->getConfigValue('payments/rapidconnect/rctppid');
        $rcgroupid = $this->p21->getConfigValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $this->p21->getConfigValue('payments/rapidconnect/rcmerchantid');
        $rctid = $this->p21->getConfigValue('payments/rapidconnect/rctid');
        $rcdid = $this->p21->getConfigValue('payments/rapidconnect/rddid');

        $VarResponse = $this->DeSerializeXMLString($SavedFieldData["TxnResponse"]);

        //GMF - create object for GMFMessageVariants
        $obj_GMFMessageVariants = new \GMFMessageVariants();

        //Credit Request - create object for CreditRequestDetails
        $obj_CreditRequestDetails = new \CreditRequestDetails();

        //Common Group - create object for CommonGrp
        $obj_CommonGrp = new \CommonGrp();
        $cardname = "";

        switch (strtoupper($SavedFieldData["CardType"])) {
            case "VISA":
                $cardname = "Visa";
                break;
            case "MASTERCARD":
                $cardname = "MasterCard";
                break;
            case "AMERICAN EXPRESS":
                $cardname = "American Express";
                break;
            case "DISCOVER":
                $cardname = "Discover";
                break;
        }

        $this->p21->gwLog("building request:");
        try {
            $stan = rand(pow(10, 6 - 1), pow(10, 6) - 1);

            //populate common transaction fields
            $obj_CommonGrp->setPymtType("Credit");	//Payment Type = Credit
            $obj_CommonGrp->setTxnType("Completion");	//Transaction Type = Sale
            $obj_CommonGrp->setLocalDateTime($currdatestr);	//Local Txn Date-Time
            $obj_CommonGrp->setTrnmsnDateTime($currdatestr);	//Local Transmission Date-Time

            $obj_CommonGrp->setSTAN($stan);	//System Trace Audit Number"100003"
            $obj_CommonGrp->setRefNum($SavedFieldData["RefNum"]);	//Reference Number
            $obj_CommonGrp->setOrderNum($SavedFieldData["orderid"]);
            $obj_CommonGrp->setTPPID($rctppid);	//TPP ID		//This is dummy value. Please use the actual value
            $obj_CommonGrp->setTermID($rctid);	//Terminal ID		//This is dummy value. Please use the actual value
            $obj_CommonGrp->setMerchID($rcmerchantid);	//Merchant ID	//This is dummy value. Please use the actual value x

            $obj_CommonGrp->setMerchCatCode("5965");

            $obj_CommonGrp->setPOSEntryMode("011");	//Entry Mode for the transaction
            $obj_CommonGrp->setPOSCondCode("00");		// POS Cond Code = 00-Normal Presentment
            $obj_CommonGrp->setTermCatCode("01");		// Terminal Category Code = 01-POS
            $obj_CommonGrp->setTermEntryCapablt("04");	// Terminal Entry Capability for the POS
            $obj_CommonGrp->setTxnAmt($SavedFieldData["TxnAmt"]);	//Transaction Amount = $8.68

            $obj_CommonGrp->setTxnCrncy("840");	// Transaction Currency = 840-US Country Code
            $obj_CommonGrp->setTermLocInd("1");	// Location Indicator for the POS
            $obj_CommonGrp->setCardCaptCap("1");	// Card capture capibility for the terminal
            $obj_CommonGrp->setGroupID($rcgroupid);	// Group ID 	//This is dummy value. Please use the actual value x
            $obj_CreditRequestDetails->setCommonGrp($obj_CommonGrp);

            //Card Group - create object for CardGrp
            $obj_CardGrp = new \CardGrp();//cc_number
            $obj_CardGrp->setAcctNum($SavedFieldData["CCNo"]);	//Card Acct Number 4012000033330026
            $obj_CardGrp->setCardExpiryDate($SavedFieldData["CCExp"]);	//Card Exp Date "20200412"
            $obj_CardGrp->setCardType($cardname);	//Card Type

            $obj_CreditRequestDetails->setCardGrp($obj_CardGrp);

            //Additional Amount Group - create object for AddtlAmtGrp
            $obj_AddtlAmtGrp = new \AddtlAmtGrp();
            $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
            $obj_AddtlAmtGrp->setAddAmtCrncy("840");
            $obj_AddtlAmtGrp->setAddAmtType("FirstAuthAmt");
            //add AddtlAmtGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setAddtlAmtGrp($obj_AddtlAmtGrp);

            //Additional Amount Group - create object for AddtlAmtGrp
            $obj_AddtlAmtGrp = new \AddtlAmtGrp();
            $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
            $obj_AddtlAmtGrp->setAddAmtCrncy("840");
            $obj_AddtlAmtGrp->setAddAmtType("TotalAuthAmt");
            //add AddtlAmtGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setAddtlAmtGrp2($obj_AddtlAmtGrp);

            // ECOMMGrp - create object for ECOMMGrp
            $obj_ECOMMGrp = new \ECOMMGrp();
            $obj_ECOMMGrp->setEcommTxnIndData("03");	//ACI Indicator
            $obj_ECOMMGrp->setEcommURLData("unknown");

            if ($cardname == "Visa") {
                $obj_VisaGrp = new \VisaGrp();
                if (isset($VarResponse["CreditResponse"]["VisaGrp"]["CardLevelResult"])) {
                    $CLR = $VarResponse["CreditResponse"]["VisaGrp"]["CardLevelResult"];
                }
                if (isset($VarResponse["CreditResponse"]["VisaGrp"]["TransID"])) {
                    $TransID = $VarResponse["CreditResponse"]["VisaGrp"]["TransID"];
                }
                $obj_VisaGrp->setACI($SavedFieldData["ACI"]);	//ACI Indicator
                if (!empty($CLR)) {
                    $obj_VisaGrp->setCardLevelResult($CLR);
                }
                if (!empty($TransID)) {
                    $obj_VisaGrp->setTransID($TransID);
                }
                $obj_VisaGrp->setVisaBID("12345");	//Visa Business ID
                $obj_VisaGrp->setVisaAUAR("111111111111");	//Visa AUAR

                //add VisaGrp to CreditRequestDetails object
                $obj_CreditRequestDetails->setVisaGrp($obj_VisaGrp);
            } elseif ($cardname == "Mastercard") {
                $obj_MCGrp = new \MCGrp();

                if (!empty($TranIntgClass)) {
                    $obj_MCGrp->setTranIntgClassData("1");
                }
                $obj_CreditRequestDetails->setMCGrp($obj_MCGrp);
            } elseif ($cardname == "Discover") {
                $obj_DSGrp = new \DSGrp();
                if (isset($VarResponse ["CreditResponse"]["DSGrp"])) { //
                    $ds = $VarResponse ["CreditResponse"]["DSGrp"];

                    $obj_DSGrp->setDiscProcCodeData($ds["DiscProcCode"]);
                    $obj_DSGrp->setDiscPOSEntrydData($ds["DiscPOSEntry"]);
                    $obj_DSGrp->setDiscRespCodeData($ds["DiscRespCode"]);
                    $obj_DSGrp->setDiscPOSDataData($ds["DiscPOSData"]);
                    $obj_DSGrp->setDiscTransQualifierData($ds["DiscTransQualifier"]);
                    $obj_DSGrp->setDiscNRIDData($ds["DiscNRID"]);
                    $obj_CreditRequestDetails->setDSGrp($obj_DSGrp);
                }
            } elseif ($cardname == "Amex") {

                $AmExPOSData = $VarResponse["CreditResponse"]["AmexGrp"]["AmExPOSData"];
                $AmExTranID = $VarResponse["CreditResponse"]["AmexGrp"]["AmExTranID"];

                $obj_AmexGrp = new \AmexGrp();
                //setAmExPOSDataData
                if (!empty($AmExPOSData)) {
                    $obj_AmexGrp->setAmExPOSDataData($AmExPOSData);
                }
                if (!empty($AmExTranID)) {
                    $obj_AmexGrp->setAmExTranIDData($AmExTranID);
                }

                $obj_CreditRequestDetails->setAmexGrp($obj_AmexGrp);
            }
            //   $this->p21->gwLog("visa built:");

            //Orig Group
            $obj_OrigGrp = new \OrigAuthGrp();//cc_number
            $obj_OrigGrp->setOrigAuthIDData($SavedFieldData["AuthID"]);
            $obj_OrigGrp->setOrigLocalDateTimeData($SavedFieldData["LocalDateTime"]);
            $obj_OrigGrp->setOrigTranDateTimeData($SavedFieldData["TXNDateTime"]);
            $obj_OrigGrp->setOrigSTANData($SavedFieldData["STAN"]);
            $obj_OrigGrp->setOrigRespCodeData($SavedFieldData["ResponseCode"]);

            $obj_CreditRequestDetails->setOrigAuthGrp($obj_OrigGrp);
        } catch (\Exception $e) {
            $this->p21->gwLog("Error!!! : " . $e->getMessage());
        }

        //assign CreditRequest to the GMF object
        $obj_GMFMessageVariants->setCreditRequest($obj_CreditRequestDetails);

        return $obj_GMFMessageVariants;
    }*/

    //Serialize GMF object to XML payload
   /* public function SerializeToXMLString(\GMFMessageVariants $gmfMesssageObj)
    {	//create XML serializer instance using PEAR
        $serializer = new \XML_Serializer(["indent" => ""]);

        $serializer->setOption("rootAttributes",[
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            "xmlns" => "com/firstdata/Merchant/gmfV1.1"
        ]);

        //perform serialization
        $result = $serializer->serialize($gmfMesssageObj);

        //check result code and return XML Payload
        if ($result == true) {
            return str_replace("GMFMessageVariants", "GMF", $serializer->getSerializedData());
        } else {
            return "Serizalion Failed";
        }
    }*/

    //deSerialize response
    public function DeSerializeXMLString($response)
    {
        $arr = explode('<Payload>', $response);
        $important = $arr[1];
        $arr = explode('</Payload>', $important);
        $important = $arr[0];
        $response = trim($important);

        //  $this->p21->gwLog ( $response );
        $serializer = new \XML_Unserializer();

        $serializer->setOption("rootAttributes", [
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            "xmlns" => "com/firstdata/Merchant/gmfV1.1"
        ]);

        //perform serialization
        $result = $serializer->unserialize($response, false);

        //check result code and return XML Payload
        if ($result == true) {
            $response1 = $serializer->getUnserializedData();

            return $response1;
        } else {
            return "Deserizalion Failed";
        }
    }

    //Send GMF transaction to Datawire using HTTP POST
    public function SendMessage($gmfXMLPayload, $clientRef)
    {
        $this->p21->gwLog("sending message");
        //Build GMF XML Payload to be sent to Datawire
        $gmfXMLPayload = '<?xml version="1.0" encoding="UTF-8"?>' . $gmfXMLPayload;
        $gmfXMLPayload = str_replace('&', '&amp;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('<', '&lt;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('>', '&gt;', $gmfXMLPayload);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rctppid = $this->p21->getConfigValue('payments/rapidconnect/rctppid');
        $rcgroupid = $this->p21->getConfigValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $this->p21->getConfigValue('payments/rapidconnect/rcmerchantid');
        $rctid = $this->p21->getConfigValue('payments/rapidconnect/rctid');
        $rcdid = $this->p21->getConfigValue('payments/rapidconnect/rddid');
        $rcurl = $this->p21->getConfigValue('payments/rapidconnect/rdurl');

        $auth = $rcgroupid . $rcmerchantid . '|' . str_pad($rctid, 8, "0", STR_PAD_LEFT);

        $theReqData = '<?xml version="1.0" encoding="utf-8"?>
        <Request xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:xsd="http://www.w3.org/2001/XMLSchema" Version="3" ClientTimeout="30"
        xmlns="http://securetransport.dw/rcservice/xml">
        <ReqClientID><DID>' . $rcdid . '</DID><App>RAPIDCONNECTSRS</App><Auth>' . $auth . '</Auth>
        <ClientRef>' . $clientRef . '</ClientRef></ReqClientID><Transaction><ServiceID>160</ServiceID>
        <Payload>' . $gmfXMLPayload . '
        </Payload></Transaction>
        </Request>';

        $url = $rcurl;//'https://stg.dw.us.fdcnet.biz/rc';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $theReqData); //set POST data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $resp_error = curl_error($ch);
            $this->p21->gwLog('Curl error: ' . $resp_error);
        } else {
            //Send transaction to Datawire and wait for response
            $this->p21->gwLog($response);

            //Replace XML tags in response payload, for readability
            $response = str_replace('&amp;', '&', $response);
            $response = str_replace('&lt;', '<', $response);
            $response = str_replace('&gt;', '>', $response);
        }

        //Release CURL PHP http handle
        curl_close($ch);

        //Return the XML Response Payload
        return $response;
    }

    public function capturePreviouslyAuthorizedAmount($order)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $merchantLoginID = $this->p21->getConfigValue('payment/authorizenet_acceptjs/login');
        $transactionKey = $this->p21->getConfigValue('payment/authorizenet_acceptjs/trans_key');
        $environment = $this->p21->getConfigValue('payment/authorizenet_acceptjs/environment');

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($merchantLoginID);
        $merchantAuthentication->setTransactionKey($transactionKey);

        // Set the transaction's refId
        $refId = 'Order #' . $order->getIncrementId();
        $transactionid = $order->getPayment()->getData('last_trans_id');

        // Now capture the previously authorized  amount
        #$this->p21->gwLog("Capturing the Authorization with transaction ID : " . $transactionid);
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("priorAuthCaptureTransaction");
        $transactionRequestType->setRefTransId($transactionid);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setTransactionRequest($transactionRequestType);

        $controller = new AnetController\CreateTransactionController($request);
        if ($environment == 'sandbox') {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        } else {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }

        if ($response != null) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                } else {
                    $this->p21->gwLog("Transaction Failed ");
                    if ($tresponse->getErrors() != null) {
                        $this->p21->gwLog(" Error code  : " . $tresponse->getErrors()[0]->getErrorCode());
                        $this->p21->gwLog(" Error message : " . $tresponse->getErrors()[0]->getErrorText());
                    }
                }
            } else {
                $this->p21->gwLog("Transaction Failed ");
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $this->p21->gwLog(" Error code  : " . $tresponse->getErrors()[0]->getErrorCode());
                    $this->p21->gwLog(" Error message : " . $tresponse->getErrors()[0]->getErrorText());
                } else {
                    $this->p21->gwLog(" Error code  : " . $response->getMessages()->getMessage()[0]->getCode());
                    $this->p21->gwLog(" Error message : " . $response->getMessages()->getMessage()[0]->getText());
                }
            }
        } else {
            $this->p21->gwLog("No response returned ");
        }

        return $response;
    }
}
