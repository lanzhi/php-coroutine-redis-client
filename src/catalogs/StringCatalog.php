<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 上午11:05
 */

namespace lanzhi\redis\catalogs;


use lanzhi\coroutine\RoutineUnitInterface;
use lanzhi\redis\commands\strings\BitCountCommand;
use lanzhi\redis\commands\strings\GetBitCommand;
use lanzhi\redis\commands\strings\GetCommand;
use lanzhi\redis\commands\strings\IncrByCommand;
use lanzhi\redis\commands\strings\SetBitCommand;
use lanzhi\redis\commands\strings\SetCommand;

/**
 * Class StringCatalog
 * @package lanzhi\redis\commands
 *
 * @method RoutineUnitInterface set(string $key, $value, int $secondsOrMilliseconds=null)
 *     在没有使用 nx 或者 xx 选项时，set 命令总是执行成功，此时响应类型为状态响应类型
 *     当使用了 nx 或者 xx 选项时，响应类型为整数响应类型，1 表示设置成功，0 表示失败
 *
 * @method RoutineUnitInterface get(string $key)
 *     返回 key 所关联的字符串值，如果 key 不存在，返回-1，如果 key 存在，但不是字符串类型，则报错
 *
 * @method RoutineUnitInterface incrBy(string $key, int $increment)
 *
 * @method RoutineUnitInterface setBit(string $key, int $offset, int $zeroOrOne)
 * @method RoutineUnitInterface getBit(string $key, int $offset)
 * @method RoutineUnitInterface bitCount(string $key, int $start=null, int $end=null)
 */
class StringCatalog extends AbstractCatalog
{

    protected function getCommandMap(): array
    {
        return [
            'set'     => SetCommand::class,
            'get'     => GetCommand::class,
            'incrBy'  => IncrByCommand::class,
            'setBit'  => SetBitCommand::class,
            'getBit'  => GetBitCommand::class,
            'bitCount'=> BitCountCommand::class,
        ];
    }

    public function ex()
    {
        $this->appendOption(SetCommand::OPTION_EX);
        return $this;
    }

    public function px()
    {
        $this->appendOption(SetCommand::OPTION_PX);
        return $this;
    }

    public function nx()
    {
        $this->appendOption(SetCommand::OPTION_NX);
        return $this;
    }

    public function xx()
    {
        $this->appendOption(SetCommand::OPTION_XX);
        return $this;
    }

    protected function getDoc()
    {
        return <<<TXT
String（字符串）
APPEND
BITCOUNT
BITOP
BITFIELD
DECR
DECRBY
GET
GETBIT
GETRANGE
GETSET
INCR
INCRBY
INCRBYFLOAT
MGET
MSET
MSETNX
PSETEX
SET
SETBIT
SETEX
SETNX
SETRANGE
STRLEN
TXT;

    }
}