<?php
declare(strict_types=1);

namespace Altitude\P21\Model\Carrier;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Async\CallbackDeferred;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\AsyncClient\HttpResponseDeferredInterface;
use Magento\Framework\HTTP\AsyncClient\Request;
use Magento\Framework\HTTP\AsyncClientInterface;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory;
use Magento\Shipping\Model\Simplexml\Element;
use Magento\Ups\Helper\Config;
use Magento\Shipping\Model\Shipment\Request as Shipment;

class Ups extends \Magento\Ups\Model\Carrier
{
    private $deferredProxyFactory;

    /**
     * Collect and get rates/errors
     *
     * @param RateRequest $request
     * @return Result|Error|bool
     */
    public function collectRates(RateRequest $request)
    {
        $items = $whAvail = $whRates = [];
        $result = null;
        $wh = 0;

        $objectManager = ObjectManager::getInstance();
        $this->deferredProxyFactory = $objectManager->get(ProxyDeferredFactory::class);

        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        $_helper = $objectManager->create('Altitude\P21\Helper\Data');
        $itemsCollection = $cart->getQuote()->getAllItems();
        $warehouses = $_helper->getWarehouses($itemsCollection);

        foreach ($warehouses as $whID => $_warehouse) {
            if (count($_warehouse) == count($itemsCollection)) {
                $whAvail[] = $whID;
            }
        }

        if (count($whAvail) == 1) {
            $wh = $whAvail[0];
        } else if (count($whAvail) > 1) {
            foreach ($warehouses as $whID => $items) {
                $_warehouse = $_helper->getWarehouseInfo($_warehouse);
                $request->setOrigPostcode($_warehouse->zipcd);
                $this->setRequest($request);

                $whRates[$whID] = $this->_getQuotes();
            }

            $wh = $_helper->cheapestWh($whRates);
        } else {
            $wh = $_helper->defaultWh();

            if ($_helper->useShippingCostPerWH()) {
                $itemWh = [];
                $tmpResult = [];

                foreach ($itemsCollection as $_item) {
                    foreach ($warehouses as $_whID => $whItems) {
                        if (in_array($_item->getSku(), $whItems)) {
                            $itemWh[$_item->getSku()] = $_whID;
                        }
                    }
                }

                foreach ($itemWh as $_itemSku => $_whID) {
                    $_warehouse = $_helper->getWarehouseInfo($_warehouse);
                    $request->setOrigPostcode($_warehouse->zipcd);
                    $this->setRequest($request);
                    $_getQuotes = $this->_getQuotes();

                    if (empty($tmpResult)) {
                        foreach ($_getQuotes as $_rate) {
                            $tmpResult[$_rate->getMethod()] = $_rate;
                        }
                    } else {
                        foreach ($_getQuotes as $_rate) {
                            $resultRate = $tmpResult[$_rate->getMethod()];
                            $resultRate->setCost($resultRate->getCost() + $_rate->getCost());
                            $resultRate->setPrice($resultRate->getPrice() + $_rate->getPrice());

                            $tmpResult[$_rate->getMethod()] = $resultRate;
                        }
                    }
                }

                $result = $this->_rateFactory->create();

                foreach ($resultRate as $rate) {
                    $result->append($rate);
                }

                $this->_result = $result;
            }
        }

        $warehouse = $_helper->getWarehouseInfo($wh);

        if (isset($warehouse->zipcd)) {
            $request->setOrigPostcode($warehouse->zipcd);
        }

        $this->setRequest($request);
        if (!$this->canCollectRates()) {
            return $this->getErrorMessage();
        }

        $this->setRequest($request);
        //To use the correct result in the callback.
        if (count($whAvail) == 1) {
            $this->_result = $result = $this->_getQuotes();
        } else if (count($whAvail) > 1) {
            $this->_result = $result = $whRates[$wh];
        }

        if ($result) {
            return $this->deferredProxyFactory->create(
                [
                    'deferred' => new CallbackDeferred(
                        function () use ($request, $result) {
                            $this->_result = $result;
                            $this->_updateFreeMethodQuote($request);
                            return $this->getResult();
                        }
                    )
                ]
            );
        }
    }
}
