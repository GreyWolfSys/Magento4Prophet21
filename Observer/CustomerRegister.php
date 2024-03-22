<?php

namespace Altitude\P21\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;

class CustomerRegister implements ObserverInterface
{
    /** @var CustomerRepositoryInterface */
    protected $customerRepository;

    protected $p21;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        \Altitude\P21\Model\P21 $p21

    ) {
        $this->customerRepository = $customerRepository;
        $this->p21 = $p21;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $configs = $this->p21->getConfigValue(['cono', 'slsrepin', 'slsrepout', 'whse', 'defaultterms', 'shipviaty','credit_status','price_library']);
        extract($configs);

        $this->p21->gwLog("Customer registered");
        $customer = $observer->getEvent()->getCustomer();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Framework\App\Http\Context');
        $addresses = $customer->getAddresses();
        
        $customerAddress = array();
        foreach ($addresses as $address)
        {
            $customerAddress[] = $address->toArray();
        }
        try
        {
            $countrycd = $customerAddress['phys_country'];
            if (! isset($countrycd) || empty($countrycd)) {
                $countrycd = 'US';
            }
            $region = $objectManager->create('Magento\Directory\Model\Region');
            $regionId = $region->loadByCode($customerAddress['phys_state'], $countrycode)->getId();
    
            if (isset($region)) {
                try {
                    $statecd = $region->getData()['region_id'];
                } catch (\Exception $e) {
                    $statecd = '';
                }
            } else {
                $statecd = '';
            }
            if (isset($customerAddress['central_phone_number'])) {
                $phone = $customerAddress['central_phone_number'];
                if (strlen($phone) < 1) {
                    $phone = '';
                }
            } else {
                $phone = '';
            }    
            $FirstAddress=$customerAddress[0];
            $addr1 = $FirstAddress['street'];
            $addr2 = "";
            $addr3 = "";
            $city = $FirstAddress['city'];
            $state = $statecd;
            $zipcd = $FirstAddress['phys_postal_code'];
            $phoneno = $phone;
            $faxphoneno = $FirstAddress['central_fax_number'];
            $countrycd = $countrycd;
            $countycd = "";
            
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage());
            $addr1 = '';
            $addr2 = "";
            $addr3 = "";
            $city = '';
            $state = '';
            $zipcd = '';
            $phoneno = '';
            $faxphoneno = '';
            $countrycd ='';
            $countycd = "";
            $phone = '';
        }
        try {
            $this->p21->gwLog("Fetching customer defaults" );
            $GCCustomerDefault = $this->p21->SalesCustomerDefaultSelect($cono);
            if (isset($GCCustomerDefault)) {

                if ($GCCustomerDefault["company_id"]=="0") 
                {
                    $this->p21->gwLog("ERP Customer default retrieve failed." );
                    $source_price_cd ="";
                    $ar_account_no ="";
                    $revenue_account_no ="";
                    $cos_account_no ="";
                    $allowed_account_no ="";
                    $terms_account_no ="";
                    $freight_account_no ="";
                    $brokerage_account_no ="";
                    $deferred_revenue_account_no ="";
                   // $credit_status ="";
                    $price_library_id ="";
                    $invoice_type ="";
                    $default_branch ="";
                    $location_id =$whse;
                    $pricing_method_cd="";
                    $default_disposition="";
                    
                  
                } else {
                    $this->p21->gwLog("ERP Customer default retrieve succeeded." );
                    $source_price_cd =$GCCustomerDefault["source_price_cd"];
                    $ar_account_no =$GCCustomerDefault["ar_account_no"];
                    $revenue_account_no =$GCCustomerDefault["revenue_account_no"];
                    $cos_account_no =$GCCustomerDefault["cos_account_no"];
                    $allowed_account_no =$GCCustomerDefault["allowed_account_no"];
                    $terms_account_no =$GCCustomerDefault["terms_account_no"];
                    $freight_account_no =$GCCustomerDefault["freight_account_no"];
                    $brokerage_account_no =$GCCustomerDefault["brokerage_account_no"];
                    $deferred_revenue_account_no =$GCCustomerDefault["deferred_revenue_account_no"];
                    //$credit_status =$GCCustomerDefault["credit_status"];
                    $price_library_id =$GCCustomerDefault["price_library_id"];
                    $invoice_type =$GCCustomerDefault["invoice_type"];
                    $default_branch =$GCCustomerDefault["default_branch"];
                    $location_id =$GCCustomerDefault["location_id"];
                    $pricing_method_cd =$GCCustomerDefault["pricing_method_cd"];
                    $default_disposition =$GCCustomerDefault["default_disposition"];
                }

            }
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage());
        }
        
        //	var_dump($address);
        $statecd = "";
        $name = $customer->getFirstname() . " " . $customer->getLastname();
        $termstype = $defaultterms;
        $taxablety = "";

        //not so required fields
        $custno = "0";
        
        
        $email = $customer->getEmail();

        $custtype = "";
        $salester = "";
        $pricetype = "";

        $pricecd = "1";
        $minord = "0";
        $maxord = "0";
        $siccd = "0";
        $bofl = "Y";
        $subfl = "Y";
        $shipreqfl = "N";
        $transproc = "arscr";
        //safe to ignore these fields
        $nontaxtype = "";
        $taxcert = "";
        $creditmgr = "";
        $taxauth = "";
        $dunsno = "";
        $user1 = "";
        $user2 = "";
        $user3 = "";
        $user4 = "";
        $user5 = "";
        $user6 = "0";
        $user7 = "0";
        $user8 = "";
        $user9 = "";
        $addon1 = "0";
        $addon2 = "0";
        $addon3 = "0";
        $addon4 = "0";
        $addon5 = "0";
        $addon6 = "0";
        $addon7 = "0";
        $addon8 = "0";
        $custpo = "";
        $inbndfrtfl = "";
        $outbndfrtfl = "";

        try {
            $this->p21->gwLog("Inserting customer" );
            //$GCCustomer = $this->p21->SalesCustomerInsert($cono,$custno, $name, $addr1, $addr2, $city, $state,$zipcd, $phoneno,$faxphoneno, "GWS",$defaultterms, "1203","N", 1,1, 1,$slsrepin, "Y", $email,"", "N","");
           $GCCustomer =  $this->p21->SalesCustomerInsert($cono,$custno, $name, $addr1, $addr2, $city, $state,$zipcd, $phoneno,$faxphoneno, "GWS", $defaultterms, "1203", "N", 1, 1, 1, $slsrepin, "Y", $email,"", "N"
           ,$source_price_cd ,$ar_account_no,$revenue_account_no,$cos_account_no,$allowed_account_no,$terms_account_no,$freight_account_no,$brokerage_account_no,$deferred_revenue_account_no
        ,$credit_status,$price_library_id,$invoice_type,$default_branch,$location_id,$pricing_method_cd,$default_disposition,$price_library);
       
       
        
        
        
            //	$this->p21->gwLog("postcust");

            if (isset($GCCustomer)) {
             
                $custno = $GCCustomer["customer_id"];
                if ($custno=="0") {
                    $this->p21->gwLog("ERP Customer insert failed." );
                } else {
                $this->p21->gwLog("New custno: " . $custno);
                $customer->setCustomAttribute("P21_CustNo", $custno);

                $this->customerRepository->save($customer);

                $customer2 = $this->customerRepository->getById($customer->getId());
                $customer2->setCustomAttribute('p21_custno', $custno);
                $this->customerRepository->save($customer2);
                  }

            //	}
            }
        } catch (\Exception $e) {
            $this->p21->gwLog($e->getMessage());
        }
    }
}
