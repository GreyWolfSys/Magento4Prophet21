<?php

namespace Altitude\P21\Plugin\Magento\Swatches\Block\Product\Renderer;

class Configurable
{
    public function afterGetJsonConfig(\Magento\Swatches\Block\Product\Renderer\Configurable $subject, $result)
    {
        $jsonResult = json_decode($result, true);
        $jsonResult['skus'] = [];

        foreach ($subject->getAllowProducts() as $simpleProduct) {
            $jsonResult['skus'][$simpleProduct->getId()] = $simpleProduct->getSku();
        }

        $jsonResult['prices']['basePrice']['amount'] = 0;

        $result = json_encode($jsonResult);

        return $result;
    }
}
