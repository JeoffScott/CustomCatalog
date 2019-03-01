<?php

namespace Test\CustomCatalog\Api;

interface CustomCatalogInterface
{
    /**
     * Returns list with products with custom VPN
     *
     * @api
     * @param string $vpn VPN.
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    public function getByVPN($vpn);


    /**
     * updates product attributes
     *
     * @api
     * @param mixed $product product data.
     * @return mixed $result
     */
    public function save($product);
}