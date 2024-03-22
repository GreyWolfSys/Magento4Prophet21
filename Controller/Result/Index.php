<?php
namespace Altitude\P21\Controller\Result;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\PopularSearchTerms;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * Catalog session
     *
     * @var Session
     */
    protected $_catalogSession;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var QueryFactory
     */
    private $_queryFactory;

    /**
     * Catalog Layer Resolver
     *
     * @var Resolver
     */
    private $layerResolver;

    protected $p21;

    /**
     * @param Context $context
     * @param Session $catalogSession
     * @param StoreManagerInterface $storeManager
     * @param QueryFactory $queryFactory
     * @param Resolver $layerResolver
     */
    public function __construct(
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        Resolver $layerResolver,
        \Altitude\P21\Model\P21 $p21
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->_catalogSession = $catalogSession;
        $this->_queryFactory = $queryFactory;
        $this->layerResolver = $layerResolver;
        $this->p21 = $p21;
    }

    /**
     * Display search result
     *
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->layerResolver->create(Resolver::CATALOG_LAYER_SEARCH);

        /* @var $query \Magento\Search\Model\Query */
        $query = $this->_queryFactory->get();

        $storeId = $this->_storeManager->getStore()->getId();
        $query->setStoreId($storeId);

        $queryText = $query->getQueryText();

        if ($queryText != '') {
            $partNumber = $this->getERPPartNumber($queryText);
            if (isset($partNumber->item_id)) {
                $queryText = $partNumber->item_id;
            }

            $catalogSearchHelper = $this->_objectManager->get(\Magento\CatalogSearch\Helper\Data::class);

            $getAdditionalRequestParameters = $this->getRequest()->getParams();
            unset($getAdditionalRequestParameters[QueryFactory::QUERY_VAR_NAME]);

            if (empty($getAdditionalRequestParameters) &&
                $this->_objectManager->get(PopularSearchTerms::class)->isCacheable($queryText, $storeId)
            ) {
                $this->getCacheableResult($catalogSearchHelper, $query);
            } else {
                $this->getNotCacheableResult($catalogSearchHelper, $query);
            }
        } else {
            $this->getResponse()->setRedirect($this->_redirect->getRedirectUrl());
        }
    }

    private function getERPPartNumber($partNumber = "")
    {
        $configs = $this->p21->getConfigValue(['apiurl', 'cono', 'apikey', 'p21customerid']);
        extract($configs);
        $apiname = "ItemsProductxRefSelect";
        $this->p21->LogAPICall($apiname);

        if (strpos($apiurl, 'p21cloud') === false  ){
            return false;
        }

        $client = $this->p21->createSoapClient($apikey, $apiname);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        if ($customerSession->isLoggedIn()) {
            $customerData = $customerSession->getCustomer();
            $p21custno = $customerData['p21_custno'];
        } else {
            $p21custno = $p21customerid;
        }

        $params1 = (object) [];
        $params1->company_id = $cono;
        $params1->customer_id = $p21custno;
        $params1->their_item_id = $partNumber;
        $params1->APIKey = $apikey;

        $rootparams = (object) [];
        $rootparams->ItemsProductxRefSelectRequestContainer = $params1;
        $result = (object) [];

        $result = $client->ItemsProductxRefSelectResponseContainer($rootparams);

        if (isset($result->errorcd) && $result->errorcd == '000-000') {
            return $result;
        }


        return false;
    }

    /**
     * Return cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @return void
     */
    private function getCacheableResult($catalogSearchHelper, $query)
    {
        if (!$catalogSearchHelper->isMinQueryLength()) {
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }

    /**
     * Return not cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getNotCacheableResult($catalogSearchHelper, $query)
    {
        if ($catalogSearchHelper->isMinQueryLength()) {
            $query->setId(0)->setIsActive(1)->setIsProcessed(1);
        } else {
            $query->saveIncrementalPopularity();
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout();
        $this->getResponse()->setNoCacheHeaders();
        $this->_view->renderLayout();
    }
}
