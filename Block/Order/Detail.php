<?php

namespace Altitude\P21\Block\Order;

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

    public function getOrder()
    {
        $configs = $this->p21->getConfigValue(['cono', 'p21customerid', 'invstartdate']);
        extract($configs);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $customer = $customerSession->getCustomer();

        #$displaytype = $ordertype;
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

        if (isset($_POST["order"])) {
            $invtodetail = $_POST["order"];
            $invorder_no = $_POST["order"];
        }
        if (isset($_POST["invoice"])) {
            $invtodetail = $_POST["invoice"];
            $invorder_no = $_POST["invoice"];
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

        $order = $this->p21->SalesOrderSelect($cono, $invorder_no);
        $customerSelect = $this->p21->SalesCustomerSelect($cono,  (isset($order["customer_id"]) ? $order["customer_id"] : $order["CustomerId"]) );
        $orderItems = $this->p21->SalesOrderLinesSelect($cono,  (isset($order["order_no"]) ? $order["order_no"] : $order["OrderNo"])) ;
        $shipping =  $this->p21->SalesShipToList($cono,   (isset($order["customer_id"]) ? $order["customer_id"] : $order["CustomerId"]) );

        return [
            'order' => $order,
            'customerNumber' => $customer['p21_custno'],
            'customerSelect' => $customerSelect,
            'orderItems' => $orderItems,
            'shipping' => $shipping
        ];
    }
}
