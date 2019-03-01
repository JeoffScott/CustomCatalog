<?php
namespace Testm\CustomCatalog\Plugin\Product;

class Save
{
    public function afterExecute(\Magento\Catalog\Controller\Adminhtml\Product\Save $subject, $result)
    {
            return $result->setPath('customcatalog/*/');
    }
}