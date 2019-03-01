<?php

namespace Test\CustomCatalog\Model;

class CustomCatalog implements \Test\CustomCatalog\Api\CustomCatalogInterface
{
    const TOPIC_NAME = 'customcatalog.product.update';
    /**
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private $publisher;

    /** @var \Magento\Catalog\Api\ProductRepositoryInterface */
    protected $_productRepository;

    /** @var \Magento\Framework\Api\SearchCriteriaBuilder */
    protected $_searchCriteriaBuilder;

    /**
     * @var  \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    /**
     * CustomCatalog constructor.
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\MessageQueue\PublisherInterface $publisher
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\MessageQueue\PublisherInterface $publisher,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
    )
    {
        $this->_productRepository = $productRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->publisher = $publisher;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Returns list with products with custom VPN
     *
     * @api
     * @param string $vpn VPN.
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    public function getByVPN($vpn)
    {
        $searchCriteria  = $this->_searchCriteriaBuilder->addFilter('vpn',$vpn,'eq')->create();
        $products = $this->_productRepository->getList($searchCriteria);
        return $products;
    }

    /**
     * updates product attributes
     *
     * @api
     * @param mixed $productData product data.
     * @return mixed $result
     */
    public function save($productData)
    {
       $result = array();
        try{

            $this->publisher->publish(self::TOPIC_NAME,$this->jsonSerializer->serialize( $productData));
            $result['success'] = 'Product with Id: '.$productData['entity_id'].' was updated';
        }  catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }
        return $result;

    }
}