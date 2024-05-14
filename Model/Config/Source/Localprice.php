<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Altitude\P21\Model\Config\Source;

class Localprice implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return [['value' => 'P21', 'label' => __('P21')],['value' => 'Magento', 'label' => __('Magento')],['value' => 'Hybrid', 'label' => __('Hybrid')]];
    }

    public function toArray()
    {
        return ['P21' => __('P21'),'Magento' => __('Magento'),'Hybrid' => __('Hybrid')];
    }
}