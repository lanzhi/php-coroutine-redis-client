<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/23
 * Time: 上午11:45
 */

namespace lanzhi\redis;


use lanzhi\socket\ReadHandlerInterface;

/**
 * Class ResponseReadHandler
 * @package lanzhi\redis
 *
 * 整数响应的命令：
 * SETNX 、 DEL 、 EXISTS 、 INCR 、 INCRBY 、 DECR 、 DECRBY 、 DBSIZE 、 LASTSAVE 、 RENAMENX 、 MOVE 、 LLEN 、 SADD 、 SREM 、 SISMEMBER 、 SCARD
 *
 */
class ResponseReadHandler implements ReadHandlerInterface
{
    const PROCESS_HEADER = 'header';
    const PROCESS_LINE   = 'line';

    const DELIMITER      = "\r\n";
    
    private $process = self::PROCESS_HEADER;

    /**
     * @var
     */
    private $responseType;//响应类型
    private $responseError;//错误响应时有值，为 error code，多条响应时为数组，其它情况下为 null
    private $responsePhrase;//状态响应和错误响应时有值，多条响应时为数组，其它情况下为 null
    private $responseData;//状态响应时为 true，错误响应时为 false
                          //整数响应时为整数，块响应时为标量，多条响应时为数组
    private $bulksTotal;//当响应类型为多条响应时有值，表示本次响应有多少条
    private $remainBulks;//剩余处理的响应条数

    /**
     * 响应流处理函数
     * @param string $buffer
     * @param int $size
     * @param bool $isEnd
     * @param bool $shouldClose
     */
    public function deal(string &$buffer, int &$size, bool &$isEnd = false, bool &$shouldClose = false): void
    {
        if($this->process===self::PROCESS_HEADER){
            $this->responseType = $buffer[0];
        }

        $isEnd = $this->dealOneBulk($this->responseType, $buffer, $this->responseError, $this->responsePhrase, $this->responseData);
    }

    /**
     * @return Response | MultiResponse
     */
    public function getResponse()
    {
        if($this->responseType==Response::TYPE_MULTI){
            return new MultiResponse($this->responseType, $this->responseError, $this->responsePhrase, $this->responseData);
        }else{
            return new Response($this->responseType, $this->responseError, $this->responsePhrase, $this->responseData);
        }
    }

    /**
     * 处理一条响应结果
     * @param string $type
     * @param string $bulk
     * @param $responseError
     * @param $responsePhrase
     * @param $responseData
     * @return bool
     * @throws \Exception
     */
    private function dealOneBulk(string $type, string &$bulk, &$responseError, &$responsePhrase, &$responseData)
    {
        switch ($type){
            case Response::TYPE_STATUS:
                $isEnd = $this->parseWhenStatus($bulk, $responsePhrase, $responseData);
                break;
            case Response::TYPE_ERROR:
                $isEnd = $this->parseWhenError($bulk, $responseError, $responsePhrase, $responseData);
                break;
            case Response::TYPE_INTEGER:
                $isEnd = $this->parseWhenInteger($bulk, $responseData);
                break;
            case Response::TYPE_BULK:
                $isEnd = $this->parseWhenBulk($bulk, $responseData);
                break;
            case Response::TYPE_MULTI:
                $isEnd = $this->parseWhenMulti($bulk, $responseError, $responsePhrase, $responseData);
                break;
            default:
                throw new \Exception("parse reply string fail; unknown reply type:{$type}; bulk:{$bulk}");
        }

        return $isEnd;
    }

    /**
     * 处理状态响应类型数据
     * 如：+OK\r\n
     * @param string $buffer
     * @param string $responsePhrase
     * @param bool $responseData
     * @return bool 是否完成处理当前响应
     */
    private function parseWhenStatus(string &$buffer, string &$responsePhrase=null, bool &$responseData=null)
    {
        $pos = strpos($buffer, self::DELIMITER);
        if($pos===false){
            return false;
        }

        $payload = substr($buffer, 1, $pos-1);
        $responsePhrase = $payload;
        $responseData   = null;

        $buffer = substr($buffer, $pos+2);
        return true;
    }

    /**
     * 处理错误响应类型数据
     * 如：-ERR unknown command 'foobar'\r\n
     * @param string $buffer
     * @param string $responseError
     * @param string $responsePhrase
     * @param bool $responseData
     * @return bool 是否完成处理当前响应
     */
    private function parseWhenError(string &$buffer, string &$responseError=null, string &$responsePhrase=null, bool &$responseData=null)
    {
        $pos = strpos($buffer, self::DELIMITER);
        if($pos===false){
            return false;
        }

        $payload = substr($buffer, 1, $pos-1);
        list($responseError, $responsePhrase) = explode(" ", $payload, 2);
        $responseData = false;

        $buffer = substr($buffer, $pos+2);
        return true;
    }

    /**
     * 处理整型响应类型数据
     * 如：:1000\r\n
     * 如：:0\r\n
     * @param string $buffer
     * @param int $responseData
     * @return bool 是否完成处理当前响应
     */
    private function parseWhenInteger(string &$buffer, int &$responseData=null)
    {
        $pos = strpos($buffer, self::DELIMITER);
        if($pos===false){
            return false;
        }

        $payload = substr($buffer, 1, $pos-1);
        $responseData = (int)$payload;

        $buffer = substr($buffer, $pos+2);
        return true;
    }

    /**
     * 处理块响应类型数据
     * 如：$6\r\nfoobar\r\n
     * 如：$-1\r\n     被请求的值不存在
     * @param string $buffer
     * @param $responseData
     * @return bool 是否完成处理当前响应
     */
    private function parseWhenBulk(string &$buffer, &$responseData=null)
    {
        $end    = strpos($buffer, self::DELIMITER);
        $length = strlen($buffer);

        $payload = substr($buffer, 1, $end-1);
        if($payload==-1){
            $responseData = null;//此时表示请求的值不存在

            $buffer = substr($buffer, $end+2);
            return true;
        }elseif($length<$end+4+$payload){//$length<($end+2+$payload+2) 当前尚未读取完响应数据
            return false;
        }else{
            $responseData = substr($buffer, $end+2, $payload);

            $buffer = substr($buffer, $end+4+$payload);
            return true;
        }
    }

    /**
     * 不支持多条响应中嵌套多条响应
     * 如：*5\r\n:1\r\n:2\r\n:3\r\n:4\r\n$6\r\nfoobar\r\n
     * 如：*0\r\n     空白的，此时响应数据应该为空数组
     * 如：*-1\r\n    无内容，此时响应数据应该为 null 对象
     * @param string $buffer
     * @param array $responseError
     * @param array $responsePhrase
     * @param array $responseData
     * @return bool
     */
    private function parseWhenMulti(string &$buffer, &$responseError=[], &$responsePhrase=[], &$responseData=[])
    {
        if($this->process==self::PROCESS_HEADER){
            $pos = strpos($buffer, "\r\n");

            $payload = substr($buffer, 1, $pos-1);
            $this->bulksTotal  = (int)$payload;
            $this->remainBulks = $this->bulksTotal;
            $buffer = substr($buffer, $pos+2);

            $this->process = self::PROCESS_LINE;
        }

        if($this->bulksTotal===0){
            return true;
        }
        while(true){
            if($this->remainBulks<=0){
                return true;
            }
            if($buffer==''){
                return false;
            }

            $response = [
                'error'  => null,
                'phrase' => null,
                'data'   => null,
            ];

            //完整处理一个响应则继续处理下一个响应，否则终止函数，返回false，等待下一次读取字节流
            if($this->dealOneBulk($buffer[0], $buffer, $response['error'], $response['phrase'], $response['data'])){
                $responseError  = is_array($responseError)  ? $responseError  : [];
                $responsePhrase = is_array($responsePhrase) ? $responsePhrase : [];
                $responseData   = is_array($responseData)   ? $responseData   : [];
                $responseError[]  = $response['error'];
                $responsePhrase[] = $response['phrase'];
                $responseData[]   = $response['data'];

                $this->remainBulks--;
            }else{
                return false;
            }
        }
    }

}
