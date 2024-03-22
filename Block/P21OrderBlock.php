<?php

namespace Altitude\P21\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Template;

class P21OrderBlock extends Template
{
    protected $_product = null;

    protected $_registry;

    protected $_productFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
       \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        array $data = []
        ) {
        $this->_registry = $registry;
        $this->_productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->_context = $context;
        $this->_cart = $cart;
        parent::__construct($context, $data);
    }

    public function DisplayBalances($customer)
    {
        global $apikey, $apiurl, $p21customerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $invstartdate;
        global $maxrecall,$maxrecalluid,$maxrecallpwd;

        $result = "";
        $gcnl = SalesCustomerSelect($cono, $customer);
        if (isset($gcnl["errordesc"])) {
            if ($gcnl["errordesc"] != "") {
                $nocust = true;
            } else {
                $nocust = false;
            }
        } else {
            $nocust = false;
        }
        //    ob_start();
        //var_dump($gcnlLine);
        //$result .= ob_get_clean();
        //      return $result;
        if ($nocust) {
            $result .= "Error retrieving results.";
        } else {
            // var_dump ($gcnl);
            $result .= "<br>";
            $result .= "<span class='gwslabel'><strong>Name: </strong></span>";
            $result .= "<span class='gwsvalue'>" . $gcnl["customer_name"] . "</span>";
            $result .= "<br>";

            $result .= "<span class='gwslabel'><strong>Credit Limit: </strong></span>";
            $result .= "<span class='gwsvalue'>$" . money_format('%.2n', (floatval($gcnl["credit_limit"]))) . "</span>";
            $result .= "<br>";

            $result .= "<span class='gwslabel'><strong>Credit Limit Used: </strong></span>";
            $result .= "<span class='gwsvalue'>" . money_format('%.2n', (floatval($gcnl["credit_limit_used"]))) . "</span>";
            $result .= "<br>";

            $result .= "<span class='gwslabel'><strong>Last Check Number: </strong></span>";
            $result .= "<span class='gwsvalue'>$" . $gcnl["last_check_number"] . "</span>";
            $result .= "<br>";

            $result .= "<span class='gwslabel'><strong>Last Check Amount: </strong></span>";
            $result .= "<span class='gwsvalue'>$" . money_format('%.2n', (floatval($gcnl["last_check_amount"]))) . "</span>";
            $result .= "<br>";

            $result .= "<span class='gwslabel'><strong>Balance: </strong></span>";
            $result .= "<span class='gwsvalue'>$" . money_format('%.2n', (floatval($gcnl["total_balance"]))) . "</span>";
            $result .= "<br><br>";

            return $result;
            //************************************************************************
            try {
                $GCShip = SalesShipToList($cono, $customer);//,'','','','','');

                if (isset($GCShip)) {
                    //  var_dump($GCShip);
                    $result .= "<form method='get' action = '#'><span class='gwslabel'><strong>Ship To: </strong></span>";
                    $result .= "<span class='gwsvalue'><select width='384px' style='width:384px;' id=shipto name=shipto><option value=''></option>";
                    if (!isset($GCShip["errordesc"])) {
                        foreach ($GCShip["SalesShipToListResponseContainerItems"] as $item) {
                            //   var_dump($item);
                            $result .= "<option value='" . $item["ship_to_id"] . "'";
                            if (isset($_GET["ship_to_id"])) {
                                if ($item["ship_to_id"] == $_GET["ship_to_id"]) {
                                    $result .= " selected ";
                                }
                            }

                            $result .= ">" . $item["name"] . " (" . $item["ship_to_id"] . ")</option>";
                        }
                    } elseif (isset($GCShip["errordesc"])) {
                        $result .= "<option value='" . $GCShip["ship_to_id"] . "' selected>" . $GCShip["name"] . "</option>";
                    }

                    $result .= "</select>";//<input type='submit' text='Filter'></span>";
                    $result .= '<input class="action subscribe primary" title="Filter" type="submit"></form>';
                } else {
                    //  $result .= "!!!";
                }
            } catch (\Exception $e) {
                $result .= ($e->getMessage());
            }
            $result .= "<br>";
            try {
                if (isset($_GET["shipto"])) {
                    $shipto = $_GET["shipto"];
                    $GCShip2 = SalesShipToSelect($cono, $customer, $shipto);
                    if (isset($GCShip2)) {
                        if (isset($GCShip2["errordesc"])) {
                            $totalbalance = 0;
                            $result .= "<span class='gwslabel'>Name: </span>";
                            $result .= "<span class='gwsvalue'>" . $GCShip2["name"] . "</span>";
                            $result .= "<br>";
                            // var_dump($GCShip2);
                            $result .= "<span class='gwslabel'>Terms: </span>";
                            $result .= "<span class='gwsvalue'>" . $GCShip2["terms_desc"] . "</span>";
                            $result .= "<br>";
                            if (!empty($GCShip2["lastagedt"])) {
                                $result .= "<span class='gwslabel'>Last Aged: </span>";
                                $result .= "<span class='gwsvalue'>" . $GCShip2["lastagedt"] . "</span>";
                                $result .= "<br>";
                            }
                            if (!empty($GCShip2["lastsaleamt"])) {
                                $result .= "<span class='gwslabel'>Last Sale Amount: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["lastsaleamt"] . "</span>";
                                $result .= "<br>";
                            }


                            if (!empty($GCShip2["futinvbal"])) {
                                $result .= "<span class='gwslabel'>Future Invoice Balance: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["futinvbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance += $GCShip2["futinvbal"];
                            }
                            if (!empty($GCShip2["codbal"])) {
                                $result .= "<span class='gwslabel'>COD Balance: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["codbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance += $GCShip2["codbal"];
                            }
                            if (!empty($GCShip2["ordbal"])) {
                                $result .= "<span class='gwslabel'>On Order Balance: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["ordbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance += $GCShip2["ordbal"];
                            }
                            if (!empty($GCShip2["misccrbal"])) {
                                $result .= "<span class='gwslabel'>Unapplied Credit: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["misccrbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance -= $GCShip2["misccrbal"];
                            }
                            if (!empty($GCShip2["uncashbal"])) {
                                $result .= "<span class='gwslabel'>Unapplied Cash: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["uncashbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance -= $GCShip2["uncashbal"];
                            }
                            if (!empty($GCShip2["servchgbal"])) {
                                $result .= "<span class='gwslabel'>Service Charge Balance: </span>";
                                $result .= "<span class='gwsvalue'>$" . $GCShip2["servchgbal"] . "</span>";
                                $result .= "<br>";
                                $totalbalance += $GCShip2["servchgbal"];
                            }

                            $result .= "<span class='gwslabel'>Total Balance: </span>";
                            $result .= "<span class='gwsvalue'>$" . $totalbalance . "</span>";
                            $result .= "<br>";
                        }
                    } else {
                        //$result .= "!!!";
                        var_dump($GCShip2);
                    }
                }
            } catch (\Exception $e) {
                $result .= ($e->getMessage());
            }
        }

        //************************************************************************

        //  $result .= shell_exec(dirname(__FILE__) . '/../../../../../' . '/fixit');
        return $result;
    }

    public function ShowOrder($item, $invstartdate, $invenddate, &$total)
    {
        $didthis = "|";
        $result = "";
        //  error_log("start: " . $invstartdate . " end: " . $invenddate);
        $orderhead = $item;
        if (!isset($orderhead["order_date"]) || !isset($orderhead["order_no"])) {
            return $result;
        }

        if ((strtotime($invstartdate) <= strtotime($orderhead["order_date"])) && (strtotime($invenddate) >= strtotime($orderhead["order_date"]) && (strpos($didthis, $orderhead["order_no"]) === false))) {
            $didthis .= $orderhead["order_no"] . "|";
            //$result .= $orderhead["order_no"] . ' ' . $orderhead["amount"];
            //var_dump ($orderhead);
            //echo '<br><Br>';
            try {
                if (isset($orderhead["paymtdt"])) {
                    $paymtdt = $orderhead["paymtdt"];
                } else {
                    $paymtdt = "";
                }
            } catch (Exception $ee) {
                $paymtdt = "";
            }
            $result .= '<tr class="orderheader">';
            $result .= '<td data-th="Order Number"><a href="?order=' . $orderhead["order_no"] . '&detail=true" alt="View Order" title="View Order">' . $orderhead["order_no"] . '</a></td>';
            $result .= '<td>' . '</td>';
            $result .= '<td data-th="Date">' . $orderhead["order_date"] . '</td>';
            $result .= '<td data-th="PO&nbsp;#">' . $orderhead["po_no"] . '</td>';
            $result .= '<td>' . '</td>';
            $result .= '<td data-th="Terms">' . $orderhead["terms_desc"] . '</td>';
            //$result .= '<td>' . $orderhead["BillingDescription"] . '</td>';
            $result .= '<td data-th="Promise&nbsp;Date">' . $orderhead["promise_date"] . '</td>';
            //  $result .= '<td align=right>$' . money_format('%.2n', (floatval($orderhead["totlineamt"]))) . '</td>';
            $result .= '<td>' . '</td>';
            //$total += $orderhead["totlineamt"];

            $result .= '</tr>';
        } //if date

        return $result;
    }

    public function ShowInvoice($item, $invstartdate, $invenddate, &$total, &$paidtotal)
    {
        $didthis = "|";
        $result = "";
        error_log("start: " . $invstartdate . " end: " . $invenddate);
        $invoice = $item;
        if (!isset($invoice["invoice_date"]) || !isset($invoice["invoice_no"])) {
            return $result;
        }
        if ((strtotime($invstartdate) <= strtotime($invoice["invoice_date"])) && (strtotime($invenddate) >= strtotime($invoice["invoice_date"]) && (strpos($didthis, $invoice["invoice_no"]) === false))) {
            $didthis .= $invoice["invoice_no"] . "|";
            //$result .= $invoice["invoice_no"] . ' ' . $invoice["amount"];
            //var_dump ($invoice);
            //echo '<br><Br>';
            try {
                if (isset($invoice["paymtdt"])) {
                    $paymtdt = $invoice["paymtdt"];
                } else {
                    $paymtdt = "";
                }
            } catch (Exception $ee) {
                $paymtdt = "";
            }
            $result .= '<tr class=invoiceheader>';
            if (!empty($invoice["order_no"])) {
                $result .= '<td><a href="?invoice=' . $invoice["order_no"] . '&detail=true" alt="View Invoice" title="View Invoice">' . $invoice["invoice_no"] . '</a></td>';
            } else {
                $result .= '<td>' . $invoice["invoice_no"] . '</td>';
            }
            $result .= '<td>' . $invoice["invoice_date"] . '</td>';
            $result .= '<td>' . $invoice["terms_desc"] . '</td>';

            $result .= '<td>' . $invoice["terms_due_date"] . '</td>';
            $result .= '<td align=right>$' . money_format('%.2n', (floatval($invoice["total_amount"]))) . '</td>';
            $total += $invoice["total_amount"];

            $result .= '<td /><td align=right>$' . money_format('%.2n', (floatval($invoice["amount_paid"]))) . '</td>';
            $paidtotal += $invoice["amount_paid"];
            $result .= '</tr>';
        } //if date
        return $result;
    }

    public function DisplayOrders($customer, $ordertype)
    {
        global $apikey, $apiurl, $p21customerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $invstartdate;

        if ($ordertype == 'balance') {
            return $this->DisplayBalances($customer);
        }

        $displaytype = "order"; //will add ui to switch this
        $result = "";
        if (isset($_POST["ordertype"])) {
            $displaytype = $_POST["ordertype"];
        //$result .= $displaytype . "!!!!!!!!!!!!!!!!!!!!";
        } else {
            // $result .= " no order!!!!!!!!!!!!!!!!!!!!";
        }

        $displaytype = $ordertype;
        $invtodetail = "";
        //     $invstartdate="01/01/1971";
        $invenddate = date("m/d/Y", time()); //"01/01/2025";
        if (isset($_GET["order"])) {
            $invtodetail = $_GET["order"];
            $invorder_no = $_GET["order"];
        }
        if (isset($_GET["invoice"])) {
            $invtodetail = $_GET["invoice"];
            $invorder_no = $_GET["invoice"];
        }
        if (isset($_POST["startdate"])) {
            $frmstartdate = $_POST["startdate"];
            if ($frmstartdate != "") {
                $invstartdate = $frmstartdate;
            }
        }
        if (isset($_POST["enddate"])) {
            $frmenddate = $_POST["enddate"];
            if ($frmenddate != "") {
                $invenddate = $frmenddate;
            }
        }
        $result .= "<form action=# method=post>";

        if ($invtodetail == "" && !isset($_GET["order"])) {
            $total = 0;
            $paidtotal = 0;
            $result .= '<table class="gwinvoicetable" border=0>';
            $result .= '<tr><td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>Start Date:</td><td style="vertical-align: middle;"><input type=text id=startdate name=startdate value="' . $invstartdate . '"></td>';
            $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>End Date:</td><td style="vertical-align: middle;"><input type=text id=enddate name=enddate value="' . $invenddate . '">';
            $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>';
            $result .= '<input type=hidden name="ordertype" value="' . $displaytype . '">';
            /*    if ($displaytype == "order") $result .= ' checked ';
                $result .= '>Order<br>';
                $result .= '<input type=radio name="ordertype" value="invoice"';
                if ($displaytype == "invoice") $result .= ' checked ';
                $result .= '>Invoice';
                $result .= '</td>';*/
            $result .= '<input type=submit></td></tr>';
        }
        if ($displaytype == "order" || $displaytype == "quote") {
            if ($invtodetail == "" && !isset($_GET["order"])) {
                $total = 0;
                $paidtotal = 0;
                $result .= '<thead><tr><th>Order Number</th>';
                $result .= '<th></th>';
                $result .= '<th>Date</th>';
                $result .= '<th>PO&nbsp;#</th>';
                $result .= '<th></th>';
                $result .= '<th>Terms</th>';
                $result .= '<th>Promise&nbsp;Date</th>';
                $result .= '<th></th>';

                //    $result .='<th>Payment Amount</th>';

                $result .= '</tr></thead>';
                $didthis = "|";
                try {
                    if ($displaytype == "order") {
                        $bStage = 0;
                        $eStage = 6;
                        $transtype = 'so';
                    } else {
                        $bStage = 0;
                        $eStage = 6;
                        $transtype = 'qu';
                    }

                    $gcnl = SalesOrderList($cono, $customer, $whse, "", "N", "", "", "", "N", "", "", "");
                    //var_dump ($gcnl);
                    //exit;
                    if (isset($gcnl["errordesc"])) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {
                        if (isset($gcnl["SalesOrderListResponseContainerItems"])) {
                            foreach ($gcnl["SalesOrderListResponseContainerItems"] as $item) {
                                $result .= $this->ShowOrder($item, $invstartdate, $invenddate, $total);
                            }
                        } else {
                            $result .= $this->ShowOrder($gcnl, $invstartdate, $invenddate, $total);
                        }
                    }
                } catch (Exception $e) {
                    error_log("GWS Error: " . $e->getMessage());
                    $result .= "<br>Error found.<br>";
                }

                $result .= '<tr><td colspan=8 align=right><strong>Order&nbsp;Total:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>'; //<td align=right><strong>Paid&nbsp;Total:</strong></td><td align=right>$' . money_format('%.2n',(floatval($paidtotal))) . '</td></tr>';
                $result .= '</table>';
            } else { //show details
                try {
                    //    $gcnl=SalesOrderList($cono,$customer, "" ,"", "", "","","", 0,0,0,$invstartdate, $invenddate,"","","","","");
                    //var_dump ($gcnl);
                    //exit;
                    //  $result="";
                    if (1 == 2) { // (isset($gcnl["errordesc"])){
                        //$result .= "<tr><td colspan=9>" .  $gcnl["errordesc"] . "</td></tr>";
                    } else {

                        //reorder ******************************************************
                        if (isset($_POST["reorderitems"])) {
                            //  $result .= "reorder";
                            if ($_POST["reorderitems"] == "yes") {
                                //reorder selected items
                                $iTotal = $_POST["totalitems"];
                                $paramsHead = new \ArrayObject(); //(object)array();

                                $itemsadded = 0;
                                $lineno = 0;
                                for ($i = 1; $i <= $iTotal; $i++) {

                                   // $result .= ("Checking: " . $i);

                                    if (isset($producttoadd)) {
                                        unset($producttoadd);
                                    }
                                    // $result .= $lineno;
                                    //***********************************reorder loop
                                    if (isset($_POST['reorder' . $i])) {
                                        //$result .= ($_POST['reorder' . $i]);

                                        $lineno = $lineno + 1;
                                        $itemsadded += 1;

                                        $type = $_POST["reorderitem" . $i]; // $item->getSku();
                                        $qty = $_POST["reorderqty" . $i]; //$item->getQty();
                                        $unit = $_POST["reorderunit" . $i]; //'ea';

                                       // $result .= ('t ' . $type . ' q ' . $qty . ' u ' . $unit);

                                        try {
                                            $producttoadd = $this->productRepository->get($type);
                                            error_log($i . "reorder product: " . $producttoadd->getId());
                                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e5) {
                                            $this->_logger->addDebug('Product Error: ' . $e5->getMessage());
                                        }
                                        if (isset($producttoadd)) {
                                            $this->_logger->addDebug('Getting prod id ' . $producttoadd->getId());
                                            $params = [
                                                'product' => $producttoadd->getId(),
                                                'qty' => $qty
                                            ];
                                            $this->_cart->addProduct($producttoadd, $params);
                                            $this->_cart->save();
                                        }
                                    }

                                    //***********************************close reorder loop
                                }
                            }
                        } else {

                            //$result .= " no reorder";
                        }

                        $total = 0;

                        $Order = SalesOrderSelect($cono, $invorder_no);
                        $Customer = SalesCustomerSelect($cono, $Order["customer_id"]);
                        //  $Package   = SalesPackagesSelect($cono, $invorder_no, $invordersuf);
                        $orderhead = $Order;

                        $url = "/altitudep21/customer/order/";
                        if (1 == 1) {
                            try {
                                if (isset($orderhead["po_no"])) {
                                    $custpo = $orderhead["po_no"];
                                } else {
                                    $custpo = "";
                                }
                            } catch (Exception $epo) {
                                $custpo = "";
                            }
                            $result .= '<div style="text-align: right;float: right;margin-top: -70px;"><input type="button" value="Back" class="action subscribe primary" onclick="window.location.href=';
                            $result .= "'" . $url . "'";
                            $result .= '"></div>';

                            $result .= '<table class="gworderbodytable data">';
                            $result .= "<tr><td width=50%/><td style='font-weight:bold;' align=right>Date:</td><td>" . $orderhead["order_date"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Order&nbsp;Number:</td><td width:150px>" . $orderhead["order_no"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>PO&nbsp;Number:</td><td>" . $custpo . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Customer&nbsp;Number:</td><td>" . $customer . "</td></tr>";
                            $result .= '</table>';

                            $result .= '<div class="block block-order-details-view">';
                            $result .= '<div class="block-title"><strong>Order Information</strong></div>';
                            $result .= '<div class="block-content">';
                            $result .= '<div class="box box-order-billing-address">';
                            $result .= '<strong class="box-title"><span>Billing Address</span></strong>';
                            $result .= '<div class="box-content"><address>';
                            $result .= $Customer["customer_name"] . "<br />";
                            $result .= (isset($Customer["mail_address1"]) ? $Customer["mail_address1"] . "<br>" : "");
                            if ($Customer["mail_address2"] != "") {
                                $result .= $Customer["mail_address2"] . "<br>";
                            }
                            $result .= (isset($Customer["mail_city"]) ? $Customer["mail_city"] . ", " : "");
                            $result .= (isset($Customer["mail_state"]) ? $Customer["mail_state"] . " " : "");
                            $result .= (isset($Customer["10012"]) ? $Customer["10012"] : "");
                            $result .= '</address>';
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '<div class="box box-order-shipping-address">';
                            $result .= '<strong class="box-title"><span>Shipping Address</span></strong>';
                            $result .= '<div class="box-content"><address>';
                            $result .= (isset($Order["ship2_name"]) ? $Order["ship2_name"] . "<br>" : "");
                            $result .= (isset($Order["ship2_add1"]) ? $Order["ship2_add1"] . "<br>" : "");
                            if ($Order["ship2_add2"] != "") {
                                $result .= $Order["ship2_add2"] . "<br>";
                            }
                            $result .= (isset($Order["ship2_city"]) ? $Order["ship2_city"] . ", " : "");
                            $result .= (isset($Order["ship2_state"]) ? $Order["ship2_state"] . " " : "");
                            $result .= (isset($Order["ship2_zip"]) ? $Order["ship2_zip"] . "<br />" : "");
                            $result .= preg_replace('/\d{3}/', '$0-', str_replace('.', null, trim($Customer["central_phone_number"])), 2);
                            $result .= '</address>';
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '<div class="box box-order-shipping-method">';
                            $result .= '<div class="box-content">';
                            $result .= '<strong>Ship Date:</strong> ';
                            $result .= (isset($Order["requested_date"]) ? $Order["requested_date"] : "") . "<br />";
                            $result .= '<strong>Terms:</strong> ';
                            $result .= (isset($Order["terms_desc"]) ? $Order["terms_desc"] : "") . "<br />";
                            $result .= '</div>';
                            $result .= '</div>';

                            /*$result .= '<div class="box box-order-shipping-method">';
                            $result .= '<div class="box-content">';
                            $result .= '<strong>Tracking Number:</strong> ';
                            $result .= (isset($Order["trackerno"]) ? $Order["trackerno"] : "N/A") . "<br />";
                            $result .= '<strong>Shipped:</strong> ';
                            $result .= (isset($Order["shippedfl"]) ? $Order["shippedfl"] : "N/A") . "<br />";
                            $result .= '</div>';
                            $result .= '</div>';*/

                            $result .= '</div>';
                            $result .= '</div>';

                            try { //lines
                                // exit;
                                $gcnlLine = SalesOrderLinesSelect($cono, $orderhead["order_no"]);
                                // var_dump($gcnlLine);
                                // exit;
                                if (isset($gcnlLine["ErrorDescription"]) && !empty($gcnlLine["ErrorDescription"])) {
                                    $result .= "<p>No lines available.</p>";
                                // $this->_logger->addDebug ("GWS Error: " . $gcnl["errordesc"] );
                                } else {
                                    $result .= '<table class="gworderlinetable data table">';
                                    $result .= '<thead><tr class=orderlinehead style="font-weight:bold;" align=center><th>Reorder!</th>';
                                    $result .= '<th align=left>SKU</th>';
                                    $result .= '<th align=left>Description</th>';
                                    $result .= '<th align=right>Price</th>';
                                    $result .= '<th align=center>Unit</th>';
                                    $result .= '<th align=right>Qty&nbsp;Ordered</th>';
                                    $result .= '<th align=right>Qty&nbsp;Shipped</th>';
                                    $result .= '<th align=right>Net&nbsp;Amt</th>';
                                    $result .= '</tr></thead>';
                                    $chkCounter = 1;
                                    //   error_log("????");
                                    //    ob_start();
                                    //   var_dump($gcnlLine);
                                    //   $result = ob_get_clean();
                                    //    error_log($result);
                                    if (isset($gcnlLine["company_no"])) {
                                        //error_log("conoline");
                                        $itemLine = $gcnlLine;
                                        //foreach($gcnlLine as $itemLine){
                                        $result .= '<tr class=orderline ><td data-th="Reorder" class="qty" align=center><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["item_id"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["item_id"] . '"></td>';
                                        //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["item_id"] . '" alt="View Item" title="View Item">' . $itemLine["item_id"] . '</a></td>';
                                        $result .= '<td data-th="SKU">' . $itemLine["item_id"] . '</td>';
                                        $result .= '<td data-th="Description">' . $itemLine["item_desc"] . '</td>';
                                        $result .= '<td data-th="Price" class="qty">$' . money_format('%.2n', $itemLine["unit_price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["unit_price"] . '"></td>';
                                        $result .= '<td data-th="Unit" class="qty">' . $itemLine["unit_of_measure"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit_of_measure"] . '"></td>';
                                        $result .= '<td data-th="Qty Ordered" class="qty">' . number_format($itemLine["qty_ordered"], 2) . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qty_ordered"] . '"></td>';
                                        $result .= '<td data-th="Qty Shipped" class="qty">' . number_format($itemLine["qty_invoiced"], 2) . '</td>';
                                        $result .= '<td data-th="Net Amt" class="qty">$' . money_format('%.2n', $itemLine["unit_price"] * $itemLine["qty_ordered"]) . '</td>';
                                        $total += $itemLine["unit_price"] * $itemLine["qty_ordered"];
                                        $chkCounter += 1;
                                        $result .= '</tr>';
                                    // }//foreach itemline
                                    } else {
                                        if (isset($gcnlLine["SalesOrderLinesSelectResponseContainerItems"])) {
                                            // error_log("multline");
                                            foreach ($gcnlLine["SalesOrderLinesSelectResponseContainerItems"] as $itemLine) {
                                                $result .= '<tr class=orderline ><td data-th="Reorder" class="qty"><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["item_id"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["item_id"] . '"></td>';
                                                //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["item_id"] . '" alt="View Item" title="View Item">' . $itemLine["item_id"] . '</a></td>';
                                                $result .= '<td data-th="SKU">' . $itemLine["item_id"] . '</td>';
                                                $result .= '<td data-th="Description">' . $itemLine["item_desc"] . '</td>';
                                                $result .= '<td data-th="Price" class="qty">$' . money_format('%.2n', $itemLine["unit_price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["unit_price"] . '"></td>';
                                                $result .= '<td data-th="Unit" class="qty">' . $itemLine["unit_of_measure"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit_of_measure"] . '"></td>';
                                                $result .= '<td data-th="Qty Ordered" class="qty">' . number_format($itemLine["qty_ordered"], 2) . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qty_ordered"] . '"></td>';
                                                $result .= '<td data-th="Qty Shipped" class="qty">' . number_format($itemLine["qty_invoiced"], 2) . '</td>';
                                                $result .= '<td data-th="Net Amt" class="qty">$' . money_format('%.2n', $itemLine["unit_price"] * $itemLine["qty_ordered"]) . '</td>';
                                                $total = ($itemLine["unit_price"] * $itemLine["qty_ordered"]) + $total;
                                                $chkCounter += 1;
                                                $result .= '</tr>';
                                            } //foreach itemline
                                        }
                                    }
                                    $chkCounter -= 1;
                                    $result .= '<tr><td><input type=hidden id=reorderitems name=reorderitems value="yes"><input type=hidden id=totalitems name=totalitems value=' . $chkCounter . '><input type=submit value="Reorder" ></td><td/><td colspan=5 align=right><strong>Subotal:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>';
                                    // $result .= '<tr><td colspan=7 align=right><strong>Tax:</td><td align=right>$' . money_format('%.2n', (floatval((isset($Order["taxamt"]) ? $Order["taxamt"] : "")))) . '</td></tr>';
                                    $result .= '<tr><td/><td colspan=6 align=right><strong>Total:</td><td align=right>$' . money_format('%.2n', (floatval($total + 0))) . '</td></tr></table></td></tr>';
                                } //if not line error
                            } //try lines
                            catch (Exception $e) {
                                error_log("GWS Error: " . $e->getMessage());
                                $result .= "<br>Error found.<br>";
                            }
                            //  break;
                        } //if active order
                        //    }//foreach

                        $result .= '</table>';
                    } // if not header error
                } //try header
                catch (Exception $eheader) {
                    $this->_logger->addDebug("GWS Error: " . $eheader->getMessage());
                    error_log("GWS Error: " . $eheader->getMessage());
                    $result .= "<br>Error found.<br>";
                }

                // *******************************************
            } //detail or header
        } else { // order or invoice
            if ($invtodetail == "" && !isset($_GET["invoice"])) {
                $total = 0;
                $paidtotal = 0;

                $result .= '<tr><th>Invoice Number</th>';

                $result .= '<th>Date</th>';

                $result .= '<th>Terms</th>';

                $result .= '<th>Due Date</th>';
                $result .= '<th>Total Amount</th>';

                $result .= '<th></th>';
                $result .= '<th>Payment Amount</th>';

                $result .= '</tr>';
                $didthis = "|";
                try {
                    $gcnl = SalesCustomerInvoiceList($cono, $customer, $whse, $invstartdate, $invenddate);
                    //var_dump ($gcnl);
                    //exit;
                    if (isset($gcnl["errordesc"])) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {
                        if (isset($gcnl["SalesCustomerInvoiceListResponseContainerItems"])) {
                            foreach ($gcnl["SalesCustomerInvoiceListResponseContainerItems"] as $item) {
                                $result .= $this->ShowInvoice($item, $invstartdate, $invenddate, $total, $paidtotal);
                            }
                        } else {
                            $result .= $this->ShowInvoice($gcnl, $invstartdate, $invenddate, $total, $paidtotal);
                        }
                    }
                } catch (Exception $e) {
                    error_log("GWS Error: " . $e->getMessage());
                    $result .= "<br>Error found.<br>";
                }

                $result .= '<tr><td colspan=4 align=right><strong>Invoice&nbsp;Total:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td><td align=right><strong>Paid&nbsp;Total:</strong></td><td align=right>$' . money_format('%.2n', (floatval($paidtotal))) . '</td></tr>';
                $result .= '</table>';
            } else { //show details
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************

                try {
                    $gcnl = SalesCustomerInvoiceList($cono, $customer, $whse, $invstartdate, $invenddate);
                    //var_dump ($gcnl);
                    //exit;
                    //  $result="";
                    if (isset($gcnl["errordesc"])) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {

                             //reorder ******************************************************
                        if (isset($_POST["reorderitems"])) {
                            // $result .= "reorder";
                            if ($_POST["reorderitems"] == "yes") {
                                //reorder selected items
                                $iTotal = $_POST["totalitems"];
                                $paramsHead = new \ArrayObject();//(object)array();

                                $itemsadded = 0;
                                $lineno = 0;
                                for ($i = 1; $i <= $iTotal; $i++) {
                                    error_log("Checking: " . $i);
                                    if (isset($producttoadd)) {
                                        unset($producttoadd);
                                    }
                                    // $result .= $lineno;
                                    //***********************************reorder loop
                                    if (isset($_POST['reorder' . $i])) {
                                        $lineno = $lineno + 1;
                                        $itemsadded += 1;

                                        $type = $_POST["reorderitem" . $i];  // $item->getSku();
                                              $qty = $_POST["reorderqty" . $i];  //$item->getQty();
                                              $unit = $_POST["reorderunit" . $i];  //'ea';

                                              try {
                                                  $producttoadd = $this->productRepository->get($type);
                                                  error_log($i . "reorder product: " . $producttoadd->getId());
                                              } catch (\Magento\Framework\Exception\NoSuchEntityException $e5) {
                                                  $this->_logger->addDebug('Product Error: ' . $e5->getMessage());
                                              }
                                        if (isset($producttoadd)) {
                                            $this->_logger->addDebug('Getting prod id ' . $producttoadd->getId());
                                            $params = [
                                                        'product' => $producttoadd->getId(),
                                                        'qty' => $qty
                                                    ];
                                            $this->_cart->addProduct($producttoadd, $params);
                                            $this->_cart->save();
                                        }
                                    }

                                    //***********************************close reorder loop
                                }
                            }
                        } else {

                             //$result .= " no reorder";
                        }

                        //done with reorder ********************************************
                        //   $result.="<style>gwinvoicebodytable, gwinvoicebodytable table, gwinvoicebodytable td{ padding:2px; }";

                        //   $result .="</style>";

                        $total = 0;
                        foreach ($gcnl["SalesCustomerInvoiceListResponseContainerItems"] as $item) {
                            $invoice = $item;
                            if ($invoice["invoice_no"] == $invtodetail) {
                                $Order = SalesOrderSelect($cono, $invoice["invoice_no"]);
                                $Customer = SalesCustomerSelect($cono, $invoice["customer_id"]);
                                //   $Package=SalesPackagesSelect($cono,$invoice["invoice_no"],"");
                            }

                            if ($invoice["invoice_no"] == $invtodetail) {
                                try {
                                    if (isset($Order["custpo"])) {
                                        $custpo = $Order["custpo"];
                                    } else {
                                        $custpo = "";
                                    }
                                } catch (Exception $epo) {
                                    $custpo = "";
                                }
                                $result .= '<table class="gwinvoicebodytable"border=1 cellpadding=0 cellspacing=3px>';
                                $result .= '<tr><td><table >';
                                $result .= "<tr><td width=50%/><td style='font-weight:bold;' align=right>Date:</td><td>" . $invoice["invoice_date"] . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>Invoice&nbsp;Number:</td><td width:150px>" . $invoice["invoice_no"] . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>PO&nbsp;Number:</td><td>" . $custpo . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>Customer&nbsp;Number:</td><td>" . $customer . "</td></tr>";
                                $result .= '</table></td></tr>';
                                $result .= '<tr><td><table border=0>';
                                $result .= '<tr><td style="font-weight:bold;" align=center colspan=3>Billing</td><td style="font-weight:bold;" align=center colspan=3>Shipping</td></tr>';
                                $result .= "<tr><td width='10%'/><td class=invoicelinehead>" . $Customer["customer_name"] . "</td><td /><td width='10%'/><td class=invoicelinehead>" . (isset($Order["shiptonm"]) ? $Order["shiptonm"] : "") . "</td><td/></tr>";
                                $result .= "<tr><td/><td class=invoicelinehead>" . $Customer["mail_address1"] . "</td><td /><td/><td class=invoicelinehead>" . (isset($Order["shiptoaddr1"]) ? $Order["shiptoaddr1"] : "") . "</td><td/></tr>";
                                if ($Customer["mail_address2"] . (isset($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") != "") {
                                    $result .= "<tr><td class=invoicelinehead class=invoicelinehead>" . $Customer["addr2"] . "</td><td /><td>" . (isset($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") . "</td><td/></tr>";
                                }
                                //   $result .="<tr><td class=invoicelinehead>" . $Customer["addr3"] . "</td><td /><td>" . $Order["shiptoaddr3"] . "</td></tr>";
                                $result .= "<tr><td/><td class=invoicelinehead>" . $Customer["mail_city"] . ", " . $Customer["mail_state"] . "  " . $Customer["mail_postal_code"] . "</td><td/><td /><td class=invoicelinehead>" . (isset($Order["ship2_city"]) ? $Order["ship2_city"] : "") . "," . (isset($Order["shiptost"]) ? $Order["shiptost"] : "") . "  " . (isset($Order["shiptozip"]) ? $Order["shiptozip"] : "") . "</td><td/></tr>";
                                $result .= "<tr><td/><td class=invoicelinehead>" . preg_replace('/\d{3}/', '$0-', str_replace('.', null, trim($Customer["central_phone_number"])), 2) . "</td><td/><td /><td></td><td/></tr>";
                                $result .= '</table></td></tr>';

                                $result .= '<tr><td><table>';
                                $result .= '<tr><td style="font-weight:bold;" align=center>Ship Date</td><td style="font-weight:bold;" align=center>Ship Via</td><td style="font-weight:bold;" align=center>Terms</td></tr>';
                                $result .= "<tr><td align=center>" . (isset($Order["shipdt"]) ? $Order["shipdt"] : "") . "</td><td align=center>" . (isset($Order["shipviadesc"]) ? $Order["shipviadesc"] : "") . "</td><td align=center>" . (isset($Order["termsdesc"]) ? $Order["termsdesc"] : "") . "</td></tr>";
                                $result .= "</table><table><tr><td align=center width='50%' style='font-weight:bold;'>Tracking Number</td><td align=center style='font-weight:bold;' width='50%' >Shipped</td></tr>";
                                $result .= "<tr><td  align=center>" . (isset($Order["trackerno"]) ? $Order["trackerno"] : "N/A") . "</td><td  align=center>" . (isset($Order["shippedfl"]) ? $Order["shippedfl"] : "N/A") . "</td></tr>";

                                $result .= '</table></td></tr>';

                                try { //lines
                                    $gcnlLine = SalesOrderLinesSelect($cono, $invoice["invoice_no"], "");
                                    // var_dump($gcnlLine);
                                   // exit;
                                        if (1 == 2) { //(isset($gcnlLine["errordesc"])  ){
                                         $result .= "<tr><td colspan=9>Error retrieving lines</td></tr>";
                                        // $this->_logger->addDebug ("GWS Error: " . $gcnl["errordesc"] );
                                        } else {
                                            $result .= '<tr><td colspan=5><table class="gwinvoicelinetable">';
                                            $result .= '<tr class=invoicelinehead style="font-weight:bold;" align=center><td>Reorder</td>';
                                            $result .= '<td align=left>SKU</td>';
                                            $result .= '<td align=left>Description</td>';
                                            $result .= '<td align=right>Price</td>';
                                            $result .= '<td align=center>Unit</td>';
                                            $result .= '<td align=right>Qty&nbsp;Ordered</td>';
                                            $result .= '<td align=right>Qty&nbsp;Shipped</td>';
                                            $result .= '<td align=right>Net&nbsp;Amt</td>';

                                            $result .= '</tr>';
                                            $chkCounter = 1;
                                            if (isset($gcnlLine["cono"])) {
                                                $itemLine = $gcnlLine;
                                                //foreach($gcnlLine as $itemLine){
                                                $result .= '<tr class=invoiceline ><td align=center><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["item_id"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["item_id"] . '"></td>';
                                                //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["item_id"] . '" alt="View Item" title="View Item">' . $itemLine["item_id"] . '</a></td>';
                                                $result .= '<td>' . $itemLine["item_id"] . '</td>';
                                                $result .= '<td>' . $itemLine["item_desc"] . '</td>';
                                                $result .= '<td align=right>$' . money_format('%.2n', $itemLine["unit_price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["unit_price"] . '"></td>';
                                                $result .= '<td  align=center>' . $itemLine["unit_of_measure"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit_of_measure"] . '"></td>';
                                                $result .= '<td align=right >' . $itemLine["qty_ordered"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qty_ordered"] . '"></td>';
                                                $result .= '<td align=right >' . $itemLine["qty_invoiced"] . '</td>';
                                                $result .= '<td align=right>$' . money_format('%.2n', $itemLine["unit_price"]) . '</td>';
                                                $total += $itemLine["unit_price"];
                                                $chkCounter += 1;
                                                $result .= '</tr>';
                                            // }//foreach itemline
                                            } elseif (isset($gcnlLine["ErrorDescription"]) && !empty($gcnlLine["ErrorDescription"])) {
                                                // ob_start();
                                                //var_dump($gcnlLine);
                                                //$result .= ob_get_clean();
                                                $result .= '<tr class=invoiceline ><td align=center colspan=8>No rows found</td></tr>';
                                            } else {
                                                foreach ($gcnlLine["SalesOrderLinesSelectResponseContainerItems"] as $itemLine) {
                                                    $result .= '<tr class=invoiceline ><td align=center><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["item_id"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["item_id"] . '"></td>';
                                                    //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["item_id"] . '" alt="View Item" title="View Item">' . $itemLine["item_id"] . '</a></td>';
                                                    $result .= '<td>' . $itemLine["item_id"] . '</td>';
                                                    $result .= '<td>' . $itemLine["item_desc"] . '</td>';
                                                    $result .= '<td align=right>$' . money_format('%.2n', $itemLine["unit_price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["unit_price"] . '"></td>';
                                                    $result .= '<td align=center>' . $itemLine["unit_of_measure"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit_of_measure"] . '"></td>';
                                                    $result .= '<td align=right >' . $itemLine["qty_ordered"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qty_ordered"] . '"></td>';
                                                    $result .= '<td align=right >' . $itemLine["qty_invoiced"] . '</td>';
                                                    $result .= '<td align=right>$' . money_format('%.2n', $itemLine["unit_price"]) . '</td>';
                                                    $total += $itemLine["unit_price"];
                                                    $chkCounter += 1;
                                                    $result .= '</tr>';
                                                }//foreach itemline
                                            }
                                            $chkCounter -= 1;
                                            $result .= '<tr><td><input type=hidden id=reorderitems name=reorderitems value="yes"><input type=hidden id=totalitems name=totalitems value=' . $chkCounter . '><input type=submit value="Reorder"></td><td colspan=6 align=right><strong>Subotal:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>';
                                            $result .= '<tr><td colspan=7 align=right><strong>Tax:</td><td align=right>$' . money_format('%.2n', (floatval((isset($Order["total_amount"]) ? $Order["taxamt"] : "")))) . '</td></tr>';
                                            $result .= '<tr><td colspan=7 align=right><strong>Total:</td><td align=right>$' . money_format('%.2n', (floatval($invoice["total_amount"]))) . '</td></tr></table></td></tr>';
                                        } //if not line error
                                } //try lines
                                    catch (Exception $e) {
                                        error_log("GWS Error: " . $e->getMessage());
                                        $result .= "<br>Error found.<br>";
                                    }
                                break;
                            }//if active invoice
                        }//foreach

                         $result .= '</table>';
                    } // if not header error
                } //try header
                catch (Exception $eheader) {
                    $this->_logger->addDebug("GWS Error: " . $eheader->getMessage());
                    error_log("GWS Error: " . $eheader->getMessage());
                    $result .= "<br>Error found.<br>";
                }

                // *******************************************
            } //detail or header
        }

        $result .= "</form>";

        return $result;
    }

    //function
}
