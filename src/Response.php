<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 下午3:32
 */

namespace lanzhi\redis;


class Response implements ResponseInterface
{
    private $type;//响应类型
    private $error;//错误响应时有值，为 error code，多条响应时为数组，其它情况下为 null
    private $phrase;//状态响应和错误响应时有值，多条响应时为数组，其它情况下为 null
    private $data;//状态响应时为 true，错误响应时为 false
                  //整数响应时为整数，块响应时为标量，多条响应时为 Response 数组

    public function __construct(string $type, string $error=null, string $phrase=null, $data=null)
    {
        $this->type   = $type;
        $this->error  = $error;
        $this->phrase = $phrase;
        $this->data   = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getPhrase(): ?string
    {
        return $this->phrase;
    }

    /**
     * @return null|int|string
     */
    public function getData()
    {
        return $this->data;
    }
}