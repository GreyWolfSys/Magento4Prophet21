<?php

namespace Altitude\P21\Block;

class Invoices extends OrderQuery
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

    function array_sort_by_column($arr, $col, $dir = SORT_ASC) {
      return  $this->p21->array_sort_by_column($arr,$col,$dir);
        
    }
    
    public function getInvoices()
    {
         error_log("invoice .php");
        $configs = $this->p21->getConfigValue(['cono', 'whse', 'p21customerid', 'invstartdate','apiurl']);
        extract($configs);
        $processor =$this->p21->getConfigValue('payments/payments/processor');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $customer = $customerSession->getCustomer();
        $invtodetail = "";
        $invenddate = date("m/d/Y", time());

        if (isset($_GET["order"])) {
            $invtodetail = $_GET["order"];
            $invorder_no = $_GET["order"];
        }

        if (isset($_GET["invoice"])) {
            $invtodetail = $_GET["invoice"];
            $invorder_no = $_GET["invoice"];
        }

        if (isset($_POST["order"])) {
            $invtodetail = $_POST["order"];
            $invorder_no = $_POST["order"];
        }

        if (isset($_POST["invoice"])) {
            $invtodetail = $_POST["invoice"];
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

        $invoicesList = $this->p21->SalesCustomerInvoiceList($cono, $customer['p21_custno'], "", $invstartdate, $invenddate);

        return [
            'invstartdate' => $invstartdate,
            'invenddate' => $invenddate,
            'invoicesList' => $invoicesList,
            'apiurl' => $apiurl,
            'processor' => $processor
        ];
    }
}
