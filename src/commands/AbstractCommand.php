<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/23
 * Time: 上午10:26
 */

namespace lanzhi\redis\commands;


use Generator;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use lanzhi\coroutine\AbstractRoutineUnit;
use lanzhi\redis\Client;
use lanzhi\redis\exceptions\FailedAfterRetryManyTimes;
use lanzhi\redis\exceptions\ResponseErrorException;
use lanzhi\redis\exceptions\RetryFailedException;
use lanzhi\redis\Response;
use lanzhi\redis\ResponseInterface;
use lanzhi\redis\ResponseReadHandler;
use lanzhi\socket\ConnectionInterface;
use lanzhi\socket\exceptions\SocketException;

abstract class AbstractCommand extends AbstractRoutineUnit implements CommandInterface
{
    private $status = self::STATUS_INIT;
    /**
     * @var array 命令选项  如 set 命令中的 EX | PX | NX | XX 等
     */
    private $options;
    /**
     * @var array 命令参数
     */
    private $arguments;
    /**
     * @var Client
     */
    private $client;

    protected $mark = Client::MARK_SELECTED;

    private $maxRetryTimes;

    public function __construct(Client $client, int $maxRetryTimes)
    {
        $this->client        = $client;
        $this->maxRetryTimes = $maxRetryTimes;
        $this->init();

        parent::__construct();
    }

    public function init(){}

    abstract public function getCommandId(): string;

    public function setOptions(array $options): CommandInterface
    {
        $this->options = $options;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setArguments(array $arguments): CommandInterface
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    abstract function getDoc();

    public function prepare(): void
    {
        //do nothing here
    }

    /**
     * @return string
     */
    public function toFriendlyString(): string
    {
        if($this->status==self::STATUS_INIT){
            $this->prepare();
            $this->status = self::STATUS_PREPARED;
        }
        $arguments = $this->getArguments();
        $commandId = $this->getCommandId();
        return $commandId . implode(" ", $arguments);
    }

    /**
     * 一个命令对象只能执行一次
     * @return Generator
     */
    protected function generate(): Generator
    {
        if($this->status==self::STATUS_INIT){
            $this->prepare();
            $this->status = self::STATUS_PREPARED;
        }
        if($this->status==self::STATUS_EXECUTED){
            return $this->getReturn();
        }

        $remainTimes = $this->maxRetryTimes;
        $data = self::serialize($this->getCommandId(), $this->getArguments());

        request:
        $generator = $this->client->getConnection($this->mark);
        yield from $generator;
        $connection = $generator->getReturn();

        $handler = new ResponseReadHandler();
        try{
            $remainTimes--;
            yield from $connection->write($data, true);
            yield from $connection->read($handler);
        }catch (SocketException $exception){
            $connection->close();//此时连接已经无效，需要通过重试重新获取连接
            $this->client->backConnection($connection);
            if($remainTimes){
                goto request;
            }else{
                throw new RetryFailedException(
                    "fail again after retry {$this->maxRetryTimes} times;",
                    $exception->getCode(),
                    $exception
                );
            }
        }catch (\Throwable $exception){
            $this->client->backConnection($connection);
            throw $exception;
        }
        $response = $handler->getResponse();
        $this->backConnectionAfterResponse($connection, $response);

        $this->status = self::STATUS_EXECUTED;
        return $response;
    }

    /**
     * 当
     * @param ConnectionInterface $connection
     * @param ResponseInterface $response
     */
    protected function backConnectionAfterResponse(ConnectionInterface $connection, ResponseInterface $response)
    {
        $this->client->backConnection($connection);
    }

    public static function serialize(string $commandId, array $arguments): string
    {
        $requestParamNum = count($arguments) + 1;
        $string = "*{$requestParamNum}\r\n";

        array_unshift($arguments, $commandId);
        foreach ($arguments as $argument){
            $argument = self::escape($argument);
            $length  = strlen($argument);
            $string .= "\${$length}\r\n{$argument}\r\n";
        }

        return $string;
    }

    private static function escape($argument)
    {
        if(!is_string($argument)){
            return $argument;
        }

        //如果参数内包含 \r\n，则直接替换为 \n
        if(strpos($argument, "\r\n")){
            $argument = str_replace("\r\n", "\n", $argument);
        }

        return $argument;
    }

}
