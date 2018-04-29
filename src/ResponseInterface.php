<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 下午3:50
 */

namespace lanzhi\redis;


interface ResponseInterface
{
    const TYPE_STATUS  = '+'; //状态响应（status reply）的第一个字节是 "+"  通常由那些不需要返回数据的命令返回
    const TYPE_ERROR   = '-'; //错误响应（error reply）的第一个字节是 "-"
    const TYPE_INTEGER = ':'; //整数响应（integer reply）的第一个字节是 ":"
    const TYPE_BULK    = '$'; //块响应（bulk reply）的第一个字节是 "$"
    const TYPE_MULTI   = '*'; //多条响应（multi bulk reply）的第一个字节是 "*"

    const TYPE_CHILD   = '?'; //表示多条响应中的子响应

    public function getType(): string;

    public function getError(): ?string;

    public function getPhrase(): ?string;

    public function getData();
}
