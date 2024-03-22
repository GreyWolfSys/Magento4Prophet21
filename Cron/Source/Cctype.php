<?php
/**
 * Payment CC Types Source Model
 *
 * @category    Altitude
 * @package     Altitude_P21

 */

namespace Altitude\P21\Cron\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC', 'AE', 'DI', 'JCB', 'OT');
    }
}
