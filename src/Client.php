<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/20
 * Time: 上午11:38
 */

namespace lanzhi\redis;

use lanzhi\redis\catalogs\ConnectionCatalog;
use lanzhi\redis\catalogs\KeyCatalog;
use lanzhi\redis\catalogs\StringCatalog;
use lanzhi\redis\exceptions\AuthFailedException;
use lanzhi\socket\ConnectionInterface;
use lanzhi\socket\Connector;


/**
 * Class Client
 * @package lanzhi\redis
 *
 */
class Client
{
    const MARK_INIT       = null;
    const MARK_AUTHORIZED = 'authorized';
    const MARK_SELECTED   = 'selected';

    private const MAX_RETRY_TIMES = 3;

    /**
     * @var Connector
     */
    private $connector;

    /**
     * @var ConnectionInterface[]
     */
    private $authorizedConnections = [];
    /**
     * @var ConnectionInterface[]
     */
    private $selectedConnections = [];
    /**
     * @var ConnectionInterface[]
     */
    private $busyConnections = [];


    private $host;
    private $port;
    private $select;
    private $password;

    private $maxRetryTimes;
    public function __construct($host, $port, array $config=[])
    {
        $this->host          = $host;
        $this->port          = $port;
        $this->select        = $config['select'] ?? 0;
        $this->password      = $config['password'] ?? null;
        $this->maxRetryTimes = $config['maxRetryTimes'] ?? self::MAX_RETRY_TIMES;

        $this->connector= Connector::getInstance();
    }

    public function key(): KeyCatalog
    {
        return new KeyCatalog($this, $this->maxRetryTimes);
    }

    public function string(): StringCatalog
    {
        return new StringCatalog($this, $this->maxRetryTimes);
    }

    protected function connection(): ConnectionCatalog
    {
        return new ConnectionCatalog($this, $this->maxRetryTimes);
    }

    /**
     * @internal
     * @param ConnectionInterface $connection
     */
    public function backConnection(ConnectionInterface $connection)
    {
        $mark = $connection->getMark();
        switch ($mark){
            case self::MARK_AUTHORIZED:
                $this->authorizedConnections[] = $connection;
                break;
            case self::MARK_SELECTED:
                $this->selectedConnections[] = $connection;
                break;
            default:
                $connection->close();
                $this->connector->back($connection);
                return;
        }

        foreach ($this->busyConnections as $index=>$item){
            if($item===$connection){
                unset($this->busyConnections[$index]);
            }
        }
    }

    /**
     * @param string $mark
     * @return \Generator
     * @throws AuthFailedException
     * @throws \Exception
     */
    public function getConnection(string $mark=self::MARK_INIT): \Generator
    {
        again:
        switch ($mark){
            case self::MARK_INIT:
                $connection = $this->connector->get('tcp', $this->host, $this->port);
                $this->busyConnections[] = $connection;
                return $connection;
                break;
            case self::MARK_AUTHORIZED:
                if($this->authorizedConnections){
                    $connection = array_shift($this->authorizedConnections);
                    $this->busyConnections[] = $connection;
                    return $connection;
                }
                if(!$this->password){
                    $connection = $this->connector->get('tcp', $this->host, $this->port);
                    $connection->setMark(self::MARK_AUTHORIZED);
                    $this->busyConnections[] = $connection;
                    return $connection;
                }

                //认证过之后，连接会通过 backConnection 添加到 authorizedConnections 列表内
                $command = $this->connection()->auth($this->password);
                yield from $command();
                $response = $command->getReturn();
                if($response->getType()==Response::TYPE_ERROR){
                    $message = sprintf(
                        "authorize failed; password:%s; error:%s[%s]",
                        $this->password,
                        $response->getPhrase(),
                        $response->getError()
                    );
                    throw new AuthFailedException($message);
                }
                goto again;

                break;
            case self::MARK_SELECTED:
                if($this->selectedConnections){
                    $connection = array_shift($this->selectedConnections);
                    $this->busyConnections[] = $connection;
                    return $connection;
                }
                if(!$this->select){
                    $connection  = $this->connector->get('tcp', $this->host, $this->port);
                    $connection->setMark(self::MARK_SELECTED);
                    $this->busyConnections[] = $connection;
                    return $connection;
                }

                //选择数据库，选择过之后，连接会通过 backConnection 添加到 idleConnections 列表内
                $command = $this->connection()->select($this->select);
                yield from $command();
                $response = $command->getReturn();
                if($response->getType()==Response::TYPE_ERROR){
                    $message = sprintf(
                        "select db failed; index:%s; error:%s[%s]",
                        $this->select,
                        $response->getPhrase(),
                        $response->getError()
                    );
                    throw new AuthFailedException($message);
                }
                goto again;

                break;
            default:
                throw new \Exception("unsupported mark:{$mark}");
        }
    }

    //归还持有的全部连接
    public function __destruct()
    {
        foreach ($this->authorizedConnections as $connection){
            $connection->close();
            $this->connector->back($connection);
        }

        foreach ($this->selectedConnections as $connection){
            $connection->close();
            $this->connector->back($connection);
        }
    }


}