<?php

namespace Altitude\P21\Observer\Sales;

class OrderInvoiceSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Execute observer.
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        //Your observer code

        //this claims funds that are previoulsy authorized
        global $p21customerid, $sendtoerpinv;
        if ($sendtoerpinv == '0') {
            error_log('settlement processing');
            $invoice = $observer->getEvent()->getInvoice();
            $order = $invoice->getOrder();
            $orderincid = $order->getIncrementId();
            $orderid = $order->getId();
            $payment = $order->getPayment();

            $sendpaymenttoERP = false;

            $SavedFieldData = $this->GetSavedFieldData($orderincid);

            if ($processor == 'Rapid Connect') {
               /* $obj_GMFMessageVariants = $this->CreateCreditSaleRequest($SavedFieldData);
                $clientRef = $this->GenerateClientRef($obj_GMFMessageVariants);
                $result = $this->SerializeToXMLString($obj_GMFMessageVariants);

                error_log('Settlement request:');
                error_log($result);

                $TxnResponse = $this->SendMessage($result, $clientRef);
                $VarResponse = $this->DeSerializeXMLString($TxnResponse);
                error_log('Settlement response:');
                error_log($VarResponse);

                $RespGrp = $VarResponse['CreditResponse']['RespGrp'];
                error_log('respcode=' . $RespGrp['RespCode']);

                if ($RespGrp['RespCode'] != '000') {
                    error_log('Failed auth request.' . $RespGrp['ErrorData']);
                    // throw new \Exception('Failed credit card authorization request.');
                    throw new \Magento\Framework\Exception\LocalizedException(__('Failed auth request.'));
                } else {
                    error_log('Auth= ' . $RespGrp['AuthID']);
                    $sendpaymenttoERP = true;
                }*/
            } elseif ($processor == 'Chase') {
            }
            if ($sendpaymenttoERP == true) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $custno = $p21customerid;

                $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
                $customerData = $customerSession2->getCustomer();
                if ($order->getCustomerIsGuest()) {
                    error_log('customer is guest');
                    $custno = $p21customerid;
                } else {
                    $CustomerID = $order->getCustomerId();

                    if ($custno) {
                        error_log('set p21 custno');
                    } else {
                        // Not Logged In
                        $custno = $p21customerid;
                        error_log('p21 custno is default');
                    }
                }

                SalesOrderPaymentInsert($custno, $invno, $invsuf, $amt);
                //processing is not done yet.
                $payment->setIsTransactionClosed(1);
            }
        }

        return true;
    }

    public function GetSavedFieldData($orderincid)
    {
        global $db_host,$db_port,$db_username,$db_password, $db_primaryDatabase;
        global $apikey,$apiurl,$p21customerid,$cono,$whse,$slsrepin, $defaultterms,$operinit,$transtype,$shipviaty,$slsrepout;

        try {
            $sql = "select orderid,ERPOrderNo,ERPSuffix, CCAuthNo, dateentered, dateprocessed, TransactionID, STAN, LocalDateTime, TXNDateTime, CCNo, CCExp, CCCCV, AuthID, TxnAmt, RefNum, ClientRef, CardType, ResponseCode FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderincid . "'";
            $dbConnection = new \mysqli($db_host, $db_username, $db_password, $db_primaryDatabase);
            //	error_log($sql);
            $result = $dbConnection->query($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if ($result->num_rows > 0) {
                // output data of each row
                error_log($result->num_rows . ' CC records found');
                while ($row = $result->fetch_assoc()) {
                    $CCSaveFields = [
                        'TransactionID' => $row['TransactionID'],
                        'STAN' => $row['STAN'],
                        'LocalDateTime' => $row['LocalDateTime'],
                        'TXNDateTime' => $row['TXNDateTime'],
                        'CCNo' => $row['CCNo'],
                        'CCExp' => $row['CCExp'],
                        'CCCCV' => $row['CCCCV'],
                        'AuthID' => $row['AuthID'],
                        'TxnAmt' => $row['TxnAmt'],
                        'RefNum' => $row['RefNum'],
                        'ClientRef' => $row['ClientRef'],
                        'CardType' => $row['CardType'],
                        'ResponseCode'->$row['ResponseCode'],
                        'ERPOrderNo'->$row['ERPOrderNo'],
                        'ERPSuffix'->$row['ERPSuffix'],
                    ];
                }

                return $CCSaveFields;
            }
        } catch (\Exception $e) {
            error_log('Failed to open update order table: ' . $e->getMessage());
        }
        try {
            $dbConnection->close();
        } catch (\Exception $e) {
            error_log('Failed to close db connection: ' . $e->getMessage());
        }
    }

    

    //Serialize GMF object to XML payload
   /* public function SerializeToXMLString(\GMFMessageVariants $gmfMesssageObj)
    {	//create XML serializer instance using PEAR
        $serializer = new \XML_Serializer(['indent' => '']);

        $serializer->setOption('rootAttributes', ['xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                                                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                                                    'xmlns' => 'com/firstdata/Merchant/gmfV1.1', ]);

        //perform serialization
        $result = $serializer->serialize($gmfMesssageObj);

        //check result code and return XML Payload
        if ($result == true) {
            return str_replace('GMFMessageVariants', 'GMF', $serializer->getSerializedData());
        } else {
            return 'Serizalion Failed';
        }
    }*/

    //deSerialize response
    public function DeSerializeXMLString($response)
    {	//create XML serializer instance using PEAR

        //  error_log ( "Response Payload: ");
        // error_log ( $response );
        $arr = explode('<Payload>', $response);
        $important = $arr[1];
        $arr = explode('</Payload>', $important);
        $important = $arr[0];
        $response = trim($important);

        //  error_log ( $response );
        $serializer = new \XML_Unserializer();

        $serializer->setOption('rootAttributes', ['xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                                                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                                                    'xmlns' => 'com/firstdata/Merchant/gmfV1.1', ]);
        //perform serialization
        $result = $serializer->unserialize($response, false);

        //check result code and return XML Payload
        if ($result == true) {
            $response1 = $serializer->getUnserializedData();

            return $response1;
        } else {
            return 'Deserizalion Failed';
        }
    }

    //Send GMF transaction to Datawire using HTTP POST
    public function SendMessage($gmfXMLPayload, $clientRef)
    {
        //Build GMF XML Payload to be sent to Datawire
        $gmfXMLPayload = '<?xml version="1.0" encoding="UTF-8"?>' . $gmfXMLPayload;
        $gmfXMLPayload = str_replace('&', '&amp;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('<', '&lt;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('>', '&gt;', $gmfXMLPayload);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rctppid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctppid');
        $rcgroupid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcmerchantid');
        $rctid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctid');
        $rcdid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rddid');

        $auth = $rcgroupid . $rcmerchantid . '|' . str_pad($rctid, 8, '0', STR_PAD_LEFT);
        //auth 10001RCTST0000056668
        //  error_log ("00018090839698053142");
        //  error_log ($rcdid);
        //Build request message
        // DID and App values are dummy values. Please use the actual values
        $theReqData = '<?xml version="1.0" encoding="utf-8"?>
            <Request xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" Version="3" ClientTimeout="30"
            xmlns="http://securetransport.dw/rcservice/xml">
            <ReqClientID><DID>' . $rcdid . '</DID><App>RAPIDCONNECTSRS</App><Auth>' . $auth . '</Auth>
            <ClientRef>' . $clientRef . '</ClientRef></ReqClientID><Transaction><ServiceID>160</ServiceID>
            <Payload>' . $gmfXMLPayload . '
            </Payload></Transaction>
            </Request>';

        //error_log (  $theReqData );
        //exit;
        //Initiate HTTP Post using CURL PHP library
        $url = 'https://stg.dw.us.fdcnet.biz/rc';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $theReqData); //set POST data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $resp_error = curl_error($ch);
            error_log('Curl error: ' . $resp_error);
        } else {
            //Send transaction to Datawire and wait for response
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
}
