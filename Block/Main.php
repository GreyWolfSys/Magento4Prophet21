<?php

namespace Altitude\P21\Block;

use Magento\Framework\View\Element\Template;

class Main extends Template
{
    protected function _prepareLayout()
    {
        $this->setMessage('Cart Integrate');
    }

    public function getGoodbyeMessage()
    {
        return 'Goodbye Cart';
    }

//     protected function _prepareLayout()
// {
//     $this->setMessage('Get P21 Price');

// }
// public function getGoodbyeMessage()
// {
//     return 'Goodbye Price';
// }
//  public function getCacheLifetime()
//     {
//         return null;
//     }
}
