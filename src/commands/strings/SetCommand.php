<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 下午2:41
 */

namespace lanzhi\redis\commands\strings;


use lanzhi\redis\commands\AbstractCommand;

/**
 * Class SetCommand
 * @package lanzhi\redis\commands\strings
 *
 * 在没有使用 nx 或者 xx 选项时，set 命令总是执行成功，此时响应类型为状态响应类型
 * 当使用了 nx 或者 xx 选项时，响应类型为整数响应类型，1 表示设置成功，0 表示失败
 *
 * 接受选项 [ex | px] 和 [nx | xx]
 *
 */
class SetCommand extends AbstractCommand
{
    const OPTION_EX = 'EX';
    const OPTION_PX = 'PX';
    const OPTION_NX = 'NX';
    const OPTION_XX = 'XX';

    public function getCommandId(): string
    {
        return 'SET';
    }

    public function prepare(): void
    {
        $options = $this->getOptions();
        $exOrPx = self::OPTION_EX;
        $nxOrXx = null;
        foreach ($options as $option){
            switch ($option){
                case self::OPTION_EX:
                case self::OPTION_PX:
                    $exOrPx = $option;
                    break;
                case self::OPTION_NX:
                case self::OPTION_XX:
                    $nxOrXx = $option;
                    break;
                default:
                    //do nothing here
            }
        }

        $arguments = $this->getArguments();
        //此时设置了过期时间
        if(count($arguments)==3 && is_int($arguments[2])){
            array_splice($arguments, 2, 0, $exOrPx);
        }

        if($nxOrXx){
            array_push($arguments, $nxOrXx);
        }

        $this->setArguments($arguments);
    }

    public function getDoc()
    {
        return <<<TXT
SET key value [EX seconds] [PX milliseconds] [NX|XX]

将字符串值 value 关联到 key 。

如果 key 已经持有其他值， SET 就覆写旧值，无视类型。

对于某个原本带有生存时间（TTL）的键来说， 当 SET 命令成功在这个键上执行时， 这个键原有的 TTL 将被清除。

可选参数

从 Redis 2.6.12 版本开始， SET 命令的行为可以通过一系列参数来修改：

EX second ：设置键的过期时间为 second 秒。 SET key value EX second 效果等同于 SETEX key second value 。
PX millisecond ：设置键的过期时间为 millisecond 毫秒。 SET key value PX millisecond 效果等同于 PSETEX key millisecond value 。
NX ：只在键不存在时，才对键进行设置操作。 SET key value NX 效果等同于 SETNX key value 。
XX ：只在键已经存在时，才对键进行设置操作。
因为 SET 命令可以通过参数来实现和 SETNX 、 SETEX 和 PSETEX 三个命令的效果，所以将来的 Redis 版本可能会废弃并最终移除 SETNX 、 SETEX 和 PSETEX 这三个命令。
可用版本：
>= 1.0.0
时间复杂度：
O(1)
返回值：
在 Redis 2.6.12 版本以前， SET 命令总是返回 OK 。

从 Redis 2.6.12 版本开始， SET 在设置操作成功完成时，才返回 OK 。
如果设置了 NX 或者 XX ，但因为条件没达到而造成设置操作未执行，那么命令返回空批量回复（NULL Bulk Reply）。
# 对不存在的键进行设置

redis 127.0.0.1:6379> SET key "value"
OK

redis 127.0.0.1:6379> GET key
"value"


# 对已存在的键进行设置

redis 127.0.0.1:6379> SET key "new-value"
OK

redis 127.0.0.1:6379> GET key
"new-value"


# 使用 EX 选项

redis 127.0.0.1:6379> SET key-with-expire-time "hello" EX 10086
OK

redis 127.0.0.1:6379> GET key-with-expire-time
"hello"

redis 127.0.0.1:6379> TTL key-with-expire-time
(integer) 10069


# 使用 PX 选项

redis 127.0.0.1:6379> SET key-with-pexpire-time "moto" PX 123321
OK

redis 127.0.0.1:6379> GET key-with-pexpire-time
"moto"

redis 127.0.0.1:6379> PTTL key-with-pexpire-time
(integer) 111939


# 使用 NX 选项

redis 127.0.0.1:6379> SET not-exists-key "value" NX
OK      # 键不存在，设置成功

redis 127.0.0.1:6379> GET not-exists-key
"value"

redis 127.0.0.1:6379> SET not-exists-key "new-value" NX
(nil)   # 键已经存在，设置失败

redis 127.0.0.1:6379> GEt not-exists-key
"value" # 维持原值不变


# 使用 XX 选项

redis 127.0.0.1:6379> EXISTS exists-key
(integer) 0

redis 127.0.0.1:6379> SET exists-key "value" XX
(nil)   # 因为键不存在，设置失败

redis 127.0.0.1:6379> SET exists-key "value"
OK      # 先给键设置一个值

redis 127.0.0.1:6379> SET exists-key "new-value" XX
OK      # 设置新值成功

redis 127.0.0.1:6379> GET exists-key
"new-value"


# NX 或 XX 可以和 EX 或者 PX 组合使用

redis 127.0.0.1:6379> SET key-with-expire-and-NX "hello" EX 10086 NX
OK

redis 127.0.0.1:6379> GET key-with-expire-and-NX
"hello"

redis 127.0.0.1:6379> TTL key-with-expire-and-NX
(integer) 10063

redis 127.0.0.1:6379> SET key-with-pexpire-and-XX "old value"
OK

redis 127.0.0.1:6379> SET key-with-pexpire-and-XX "new value" PX 123321
OK

redis 127.0.0.1:6379> GET key-with-pexpire-and-XX
"new value"

redis 127.0.0.1:6379> PTTL key-with-pexpire-and-XX
(integer) 112999


# EX 和 PX 可以同时出现，但后面给出的选项会覆盖前面给出的选项

redis 127.0.0.1:6379> SET key "value" EX 1000 PX 5000000
OK

redis 127.0.0.1:6379> TTL key
(integer) 4993  # 这是 PX 参数设置的值

redis 127.0.0.1:6379> SET another-key "value" PX 5000000 EX 1000
OK

redis 127.0.0.1:6379> TTL another-key
(integer) 997   # 这是 EX 参数设置的值
TXT;

    }
}