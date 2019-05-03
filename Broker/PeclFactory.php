<?php

namespace Swarrot\SwarrotBundle\Broker;

use Swarrot\Broker\MessageProvider\PeclPackageMessageProvider;
use Swarrot\Broker\MessagePublisher\PeclPackageMessagePublisher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PeclFactory implements FactoryInterface
{
    use UrlParserTrait;

    protected $logger;
    protected $publisherConfirms;
    protected $timeout;

    protected $connections = array();
    protected $messageProviders = array();
    protected $messagePublishers = array();
    protected $queues = array();
    protected $exchanges = array();
    protected $amqpConnections = array();

    /**
     * __construct.
     *
     * @param LoggerInterface $logger
     * @param bool $publisherConfirms
     * @param float $timeout
     */
    public function __construct(LoggerInterface $logger = null, bool $publisherConfirms = false, float $timeout = 0.0)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->publisherConfirms = $publisherConfirms;
        $this->timeout = $timeout;
    }

    /**
     * {@inheritDoc}
     */
    public function addConnection($name, array $connection)
    {
        if (!empty($connection['url'])) {
            $params = $this->parseUrl($connection['url']);
            $connection = array_merge($connection, $params);
        }

        $this->connections[$name] = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageProvider($name, $connection)
    {
        if (!isset($this->messageProviders[$connection][$name])) {
            if (!isset($this->messageProviders[$connection])) {
                $this->messageProviders[$connection] = array();
            }

            $queue = $this->getQueue($name, $connection);

            $this->messageProviders[$connection][$name] = new PeclPackageMessageProvider($queue);
        }

        return $this->messageProviders[$connection][$name];
    }

    /**
     * {@inheritDoc}
     */
    public function getMessagePublisher($name, $connection)
    {
        if (!isset($this->messagePublishers[$connection][$name])) {
            if (!isset($this->messagePublishers[$connection])) {
                $this->messagePublishers[$connection] = array();
            }

            $exchange = $this->getExchange($name, $connection);

            $this->messagePublishers[$connection][$name] = new PeclPackageMessagePublisher($exchange, AMQP_NOPARAM, $this->logger, $this->publisherConfirms, $this->timeout);
        }

        return $this->messagePublishers[$connection][$name];
    }

    /**
     * getQueue.
     *
     * @param string $name
     * @param string $connection
     *
     * @return \AMQPQueue
     */
    public function getQueue($name, $connection)
    {
        if (!isset($this->queues[$connection][$name])) {
            if (!isset($this->queues[$connection])) {
                $this->queues[$connection] = array();
            }

            $queue = new \AMQPQueue(
                $this->getChannel($connection)
            );
            $queue->setName($name);

            $this->queues[$connection][$name] = $queue;
        }

        return $this->queues[$connection][$name];
    }

    /**
     * getExchange.
     *
     * @param string $name
     * @param string $connection
     *
     * @return \AMQPExchange
     */
    public function getExchange($name, $connection)
    {
        if (!isset($this->exchanges[$connection][$name])) {
            if (!isset($this->exchanges[$connection])) {
                $this->exchanges[$connection] = array();
            }

            $exchange = new \AMQPExchange(
                $this->getChannel($connection)
            );
            $exchange->setName($name);

            $this->exchanges[$connection][$name] = $exchange;
        }

        return $this->exchanges[$connection][$name];
    }

    /**
     * getChannel.
     *
     * @param string $connection
     *
     * @throws \AMQPConnectionException
     *
     * @return \AMQPChannel
     */
    protected function getChannel($connection)
    {
        if (!isset($this->connections[$connection])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown connection "%s". Available: [%s]',
                $connection,
                implode(', ', array_keys($this->connections))
            ));
        }

        if (!isset($this->amqpConnections[$connection])) {
            $this->amqpConnections[$connection] = new \AMQPConnection($this->connections[$connection]);
            $this->amqpConnections[$connection]->connect();
        }

        return new \AMQPChannel($this->amqpConnections[$connection]);
    }
}
