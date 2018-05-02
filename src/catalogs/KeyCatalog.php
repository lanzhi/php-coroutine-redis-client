<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 上午11:05
 */

namespace lanzhi\redis\catalogs;

use lanzhi\redis\commands\keys\DelCommand;
use lanzhi\redis\commands\keys\ExistsCommand;
use lanzhi\redis\commands\keys\ExpireCommand;
use lanzhi\redis\commands\keys\TTlCommand;
use lanzhi\coroutine\RoutineUnitInterface;

/**
 * Class Key
 * @package lanzhi\redis\commands
 *
 * @method RoutineUnitInterface del(string $key1, string ...$keyN)  返回被删除 key 的数量，如果 key 不存在，则返回 0
 * @method RoutineUnitInterface exists(string $key)  存在返回 1，不存在返回 0
 * @method RoutineUnitInterface expire(string $key, int $seconds)  设置成功则返回 1， 否则返回 0，当 key 不存在或者不能设置生存时间时返回 0
 * @method RoutineUnitInterface ttl(string $key)  当 key 不存在时返回 -2，当 key 存在但为持久类型时返回 -1，其它情况返回 key 的剩余生存时间，单位为秒
 */
class KeyCatalog extends AbstractCatalog
{
    protected function getCommandMap(): array
    {
        return [
            'del'    => DelCommand::class,
            'exists' => ExistsCommand::class,
            'expire' => ExpireCommand::class,
            'ttl'    => TTlCommand::class,
        ];
    }

    protected function getDoc()
    {
        return <<<TXT
DEL
DUMP
EXISTS
EXPIRE
EXPIREAT
KEYS
MIGRATE
MOVE
OBJECT
PERSIST
PEXPIRE
PEXPIREAT
PTTL
RANDOMKEY
RENAME
RENAMENX
RESTORE
SORT
TTL
TYPE
SCANs
TXT;
    }
}
