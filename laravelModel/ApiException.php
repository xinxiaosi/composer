<?php

namespace laravelModel;

use App\Models\Base\ResponseModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiException extends Exception
{
    const DEFAULT_ERROR_CODE = 500;

    const DEFAULT_SUCCESS_CODE = 1;

    const DEFAULT_ERROR_MESSAGE = '请求失败';

    const ERROR_NOT_FOUND = [404, '请求的页面不存在'];

    const ERROR_AUTHORIZATION = [403, '认证失败！'];

    const AUTHORIZATION_EXPIRED = [403, 'Token已过期'];

    const ERROR_DATA_NOT_EXISTS = [1404, '数据不存在'];

    const AUTH_RULES_NOT_EXISTS = [1403, '暂无此权限！'];

    public ResponseModel $responseModel;

    /**
     * ApiException constructor.
     * @param array $error
     * @param array $params 参数
     */
    public function __construct($error, $params = [])
    {
        $this->responseModel = new ResponseModel();
        $code = $error[0] ?? self::DEFAULT_ERROR_CODE;
        $msg = $error[1] ?? self::DEFAULT_ERROR_MESSAGE;
        $msg = $this->handleMsg($msg, $params);
        parent::__construct($msg, intval($code));
    }

    /**
     * 解析msg
     * @param string $msg --- 错误信息:{message}
     * @param array $params --- {"message":"您访问的页面不存在"}
     * @return string
     */
    private function handleMsg(string $msg, array $params): string
    {
        foreach ($params as $key => $item) {
            $msg = str_replace('{' . $key . '}', $item, $msg);
        }
        return $msg;
    }

    /**
     * 异常 报告
     * Report the exception.
     *
     * @return void
     */
    public function report()
    {
        //echo 'ApiException_report';
    }

    /**
     *
     * 异常 处理
     * Render the exception into an HTTP response.
     *
     * @param Request
     * @return Response
     */
    public function render(): Response
    {
        $response = new Response();

        $response->exception = $this;
        return $response;


    }

}
