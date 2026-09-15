<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\exception;

class HttpException extends \RuntimeException
{
    private $statusCode;
    private $headers;

    public function __construct($statusCode, $message = null, $previous = null, array $headers = [], $code = 0)
    {
        if ($previous !== null && !$previous instanceof \Exception) {
            throw new \InvalidArgumentException('参数$previous必须是Exception实例');//[修改]兼容类型约束
        }
        
        $this->statusCode = $statusCode;
        $this->headers    = $headers;

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
