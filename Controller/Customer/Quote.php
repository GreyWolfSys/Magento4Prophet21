<?php

namespace Altitude\P21\Controller\Customer;

class Quote extends \Altitude\P21\Controller\CustomerAbstract
{
    public function execute()
    {
        $this->_view->loadLayout();

        $this->_view->renderLayout();
    }
}
