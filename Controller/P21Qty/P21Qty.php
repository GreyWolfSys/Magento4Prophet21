<?php
namespace Altitude\P21\Controller\P21Qty;

class P21Qty extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;
    private $productRepository;
    private $p21;

    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Altitude\P21\Model\P21 $p21
    )
    {
        $this->productRepository = $productRepository;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->p21 = $p21;

        return parent::__construct($context);
    }

    public function execute()
    {
        if ($this->p21->botDetector() || !$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('/');
            return;
        }

        $result = $this->_resultJsonFactory->create();
        $resultPage = $this->_resultPageFactory->create();
        $sku = $this->getRequest()->getParam('sku');
       
        $block = $resultPage->getLayout()
                ->createBlock('Altitude\P21\Block\MainProduct')
                ->setTemplate('Altitude_P21::qtyajax.phtml')
                ->setData('sku',$sku)
                ->toHtml();

        $result->setData(['output' => $block]);
        return $result;
    }
}
