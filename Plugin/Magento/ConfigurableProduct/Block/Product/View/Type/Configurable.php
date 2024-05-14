<?php
/**
 * Created by PhpStorm.
 * User: thomasnordkvist
 * Date: 17-01-30
 * Time: 08:15
 */

namespace Altitude\P21\Plugin\Magento\ConfigurableProduct\Block\Product\View\Type;

class Configurable
{
    public function afterGetJsonConfig(\Magento\ConfigurableProduct\Block\Product\View\Type\Configurable $subject, $result)
    {
        $jsonResult = json_decode($result, true);

        $jsonResult['skus'] = [];

        foreach ($subject->getAllowProducts() as $simpleProduct) {
            $jsonResult['skus'][$simpleProduct->getId()] = $simpleProduct->getSku(); //$this->p21->getAltitudeSKU($simpleProduct); //
        }

        $result = json_encode($jsonResult);

        return $result;
    }
}
