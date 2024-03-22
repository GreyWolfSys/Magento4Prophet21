<?php

namespace Altitude\P21\Block\Invoice;

use Altitude\P21\Block\OrderQuery;

class Detail extends OrderQuery
{
    protected $p21;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
       \Magento\Framework\View\Element\Template\Context $context,
        \Altitude\P21\Model\P21 $p21,
        array $data = []
    ) {
        $this->_context = $context;
        $this->p21 = $p21;
        parent::__construct($context, $data);
    }

    public function getInvoice()
    {
        
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'invstartdate', 'whse', 'apiurl']);
        
        $processor =$this->p21->getConfigValue('payments/payments/processor');
        extract($configs);

        if (strpos($this->p21->getConfigValue('apiurl'),'p21cloud') !==false  ){
            return"";
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $customer = $customerSession->getCustomer();

        //     $invstartdate="01/01/1971";
        $invenddate = date("m/d/Y", time()); //"01/01/2025";
        if (isset($_GET["order"])) {
            $order_no = $_GET["order"];
        }
        if (isset($_GET["invoice"])) {
            $invorder_no = $_GET["invoice"];
        }
        if (isset($_POST["order"])) {
            $order_no = $_POST["order"];
        }
        if (isset($_POST["invoice"])) {
            $invorder_no = $_POST["invoice"];
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
        if (isset($_GET["startdate"])) {
            $frmstartdate = $_GET["startdate"];
            if ($frmstartdate != "") {
                $invstartdate = $frmstartdate;
            }
        }
        if (isset($_GET["enddate"])) {
            $frmenddate = $_GET["enddate"];
            if ($frmenddate != "") {
                $invenddate = $frmenddate;
            }
        }

        $order = $this->p21->SalesOrderSelect($cono, $order_no);
        $customerSelect = $this->p21->SalesCustomerSelect($cono,  (isset($order["customer_id"]) ? $order["customer_id"] : $order["CustomerId"]));
        $orderItems = $this->p21->SalesCustomerInvoiceLinesSelect($cono, $invorder_no);
        $invoicesList = $this->p21->SalesCustomerInvoiceList($cono,  (isset($order["customer_id"]) ? $order["customer_id"] : $order["CustomerId"]), '', (isset($order["order_date"]) ? $order["order_date"] : $order["OrderDate"]) , '' );
        $shipping =  $this->p21->SalesShipToList($cono,   (isset($order["customer_id"]) ? $order["customer_id"] : $order["CustomerId"]) );
        $invoice = null;



        if (isset($invoicesList["SalesCustomerInvoiceListResponseContainerItems"])) {
            foreach ($invoicesList["SalesCustomerInvoiceListResponseContainerItems"] as $item) {
                if ($item["invoice_no"] == $invorder_no) {
                    $invoice = $item;
                    break;
                }
            }
            
        } else {
            $invoice=$invoicesList;
        }


        return [
            'order' => $order,
            'customerNumber' => $customer['p21_custno'],
            'customerSelect' => $customerSelect,
            'orderItems' => $orderItems,
            'invoice' => $invoice,
            'shipping' => $shipping,
            'apiurl' => $apiurl,
            'processor' => $processor
        ];
    }
}
