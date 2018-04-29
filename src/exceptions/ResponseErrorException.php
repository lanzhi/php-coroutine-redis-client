<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/25
 * Time: 下午4:05
 */

namespace lanzhi\redis\exceptions;


use lanzhi\redis\ResponseInterface;

class ResponseErrorException extends RedisException
{
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
        $message = "{$response->getError()}:{$response->getPhrase()}";
        parent::__construct($message, 0, null);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}