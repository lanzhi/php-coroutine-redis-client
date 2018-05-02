<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/5/2
 * Time: 下午5:12
 */

namespace lanzhi\redis\commands\strings;


use lanzhi\redis\commands\AbstractCommand;

class IncrCommand extends AbstractCommand
{
    public function getCommandId(): string
    {
        return 'INCRBY';
    }

    public function prepare(): void
    {
        $args = $this->getArguments();
        $args[] = 1;
        $this->setArguments($args);
    }

    public function getDoc()
    {
        return '参见 IncrByCommand ';
    }
}