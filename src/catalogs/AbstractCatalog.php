<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 下午2:21
 */

namespace lanzhi\redis\catalogs;


use lanzhi\coroutine\RoutineUnitInterface;
use lanzhi\redis\Client;
use lanzhi\redis\commands\CommandInterface;

abstract class AbstractCatalog
{
    private $client;
    private $maxRetryTimes;

    private $options = [];

    public function __construct(Client $client, int $maxRetryTimes)
    {
        $this->client        = $client;
        $this->maxRetryTimes = $maxRetryTimes;
    }

    public function __call($command, $arguments): RoutineUnitInterface
    {
        $map = $this->getCommandMap();
        if(!isset($map[$command])){
            throw new \Exception("unsupported; command:{$command}");
        }

        $commandClass = $map[$command];

        /**
         * @var CommandInterface|RoutineUnitInterface $command
         */
        $command = new $commandClass($this->client, $this->maxRetryTimes);
        $command->setOptions($this->options)->setArguments($arguments);

        return $command;
    }

    /**
     * @param string $option 追加命令选项
     */
    protected function appendOption($option)
    {
        $this->options[] = $option;
    }

    protected function getOptions()
    {
        return $this->options;
    }

    abstract protected function getCommandMap(): array;
    abstract protected function getDoc();
}