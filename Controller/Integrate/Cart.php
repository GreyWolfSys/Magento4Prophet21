<?php

namespace Altitude\P21\Controller\Integrate;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Cart extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory)
    {
        $this->pageFactory = $pageFactory;

        return parent::__construct($context);
    }

    public function execute()
    {
        //   var_dump(__METHOD__);

        $page_object = $this->pageFactory->create();

        return $page_object;
    }
}
