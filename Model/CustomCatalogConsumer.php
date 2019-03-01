<?php

namespace Testm\CustomCatalog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\MessageQueue\MessageLockException;
use Magento\Framework\MessageQueue\ConnectionLostException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\MessageQueue\CallbackInvoker;
use Magento\Framework\MessageQueue\ConsumerConfigurationInterface;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Magento\Framework\MessageQueue\LockInterface;
use Magento\Framework\MessageQueue\MessageController;
use Magento\Framework\MessageQueue\ConsumerInterface;

/**
 * Class CustomCatalogConsumer used to process  messages.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomCatalogConsumer implements ConsumerInterface
{
    /**
     * @var \Magento\Framework\MessageQueue\CallbackInvoker
     */
    private $invoker;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var \Magento\Framework\MessageQueue\ConsumerConfigurationInterface
     */
    private $configuration;

    /**
     * @var \Magento\Framework\MessageQueue\MessageController
     */
    private $messageController;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $_productRepository;
    /**
     * @var  \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    /**
     * Initialize dependencies.
     *
     * @param CallbackInvoker $invoker
     * @param ResourceConnection $resource
     * @param MessageController $messageController
     * @param ConsumerConfigurationInterface $configuration
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     */
    public function __construct(
        CallbackInvoker $invoker,
        ResourceConnection $resource,
        MessageController $messageController,
        ConsumerConfigurationInterface $configuration,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
    ) {
        $this->invoker = $invoker;
        $this->resource = $resource;
        $this->messageController = $messageController;
        $this->configuration = $configuration;
        $this->_productRepository = $productRepository;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * {@inheritdoc}
     */
    public function process($maxNumberOfMessages = null)
    {
        $queue = $this->configuration->getQueue();
        if (!isset($maxNumberOfMessages)) {
            $queue->subscribe($this->getTransactionCallback($queue));
        } else {
            $this->invoker->invoke($queue, $maxNumberOfMessages, $this->getTransactionCallback($queue));
        }
    }

    /**
     * Get transaction callback. This handles the case of async.
     *
     * @param QueueInterface $queue
     * @return \Closure
     */
    private function getTransactionCallback(QueueInterface $queue)
    {
        return function (EnvelopeInterface $message) use ($queue) {
            /** @var LockInterface $lock */
            $lock = null;
            try {
                $lock = $this->messageController->lock($message, $this->configuration->getConsumerName());
                $messageBody = $this->jsonSerializer->unserialize($this->jsonSerializer->unserialize($message->getBody()));
                $product =  $this->_productRepository->getById($messageBody['entity_id']);
                $product->setData('copy_write_info', $messageBody['copyright_info']);
                $product->setData('vpn', $messageBody['vpn']);
                $product = $this->_productRepository->save($product);
                if ($product === false) {
                    $queue->reject($message);
                }
                $queue->acknowledge($message);
            } catch (MessageLockException $exception) {
                $queue->acknowledge($message);
            } catch (ConnectionLostException $e) {
                $queue->acknowledge($message);
                if ($lock) {
                    $this->resource->getConnection()
                        ->delete($this->resource->getTableName('queue_lock'), ['id = ?' => $lock->getId()]);
                }
            } catch (NotFoundException $e) {
                $queue->acknowledge($message);
            } catch (\Exception $e) {
                $queue->reject($message, false, $e->getMessage());
                $queue->acknowledge($message);
                if ($lock) {
                    $this->resource->getConnection()
                        ->delete($this->resource->getTableName('queue_lock'), ['id = ?' => $lock->getId()]);
                }
            }
        };
    }
}
