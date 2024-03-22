<?php
namespace Altitude\P21\Model\Config\Source;

class Processor implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'none', 'label' => __('none')],
            // ['value' => 'Rapid Connect', 'label' => __('Rapid Connect')],
            ['value' => 'Chase', 'label' => __('Chase')],
            ['value' => 'Authorize.NET', 'label' => __('Authorize.NET')]
        ];
    }

    public function toArray()
    {
        return [
            'none' => __('none'),
            // 'Rapid Connect' => __('Rapid Connect'),
            'Chase' => __('Chase'),
            'Authorize.NET' => __('Authorize.NET')];
    }
}
