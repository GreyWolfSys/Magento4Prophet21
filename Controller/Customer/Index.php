<?php

namespace Altitude\P21\Controller\Customer;

class Index extends \Altitude\P21\Controller\CustomerAbstract
{
    public function execute()
    {
        $this->_view->loadLayoutx();

        $this->_view->renderLayout();
    }
}
