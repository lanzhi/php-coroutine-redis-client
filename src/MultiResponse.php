<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/24
 * Time: 下午3:48
 */

namespace lanzhi\redis;


class MultiResponse implements ResponseInterface
{
    private $type;//响应类型
    private $error;//错误响应时有值，为 error code，多条响应时为数组，其它情况下为 null
    private $phrase;//状态响应和错误响应时有值，多条响应时为数组，其它情况下为 null
    private $data;//状态响应时为 true，错误响应时为 false
    //整数响应时为整数，块响应时为标量，多条响应时为 Response 数组


    public function __construct($type, array $errors, array $phrases, array $dataList)
    {
        $this->type = $type;

        $list = [];
        foreach ($errors as $index=>$error){
            $list[] = new Response(self::TYPE_CHILD, $error, $phrases[$index], $dataList[$index]);
        }

        $this->error  = null;
        $this->phrase = null;
        $this->data   = $list;
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
     * @return Response[]
     */
    public function getData()
    {
        return $this->data;
    }
}