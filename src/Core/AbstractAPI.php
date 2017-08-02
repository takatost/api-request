<?php namespace Jhk\ApiRequests\Core;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Jhk\ApiRequests\Exceptions\ApiClosedException;
use Jhk\ApiRequests\Exceptions\AuthErrorException;
use Jhk\ApiRequests\Exceptions\HttpException;
use Jhk\ApiRequests\Exceptions\ParameterIllegalException;
use Jhk\ApiRequests\Exceptions\RequestErrorException;
use Jhk\ApiRequests\Exceptions\ResourceNotFoundException;
use Psr\Http\Message\RequestInterface;
use Jhk\KongAuth\Client;
use Jhk\KongAuth\Token;

/**
 * Class AbstractAPI.
 */
abstract class AbstractAPI
{
    /**
     * Http instance.
     *
     */
    protected $http;

    /**
     * @var string
     */
    protected $defaultEntity = null;

    const GET   = 'get';
    const POST  = 'post';
    const PATCH = 'patch';
    const PUT   = 'put';
    const DELETE = 'delete';
    const JSON  = 'json';

    /**
     * @return string
     * @author         JohnWang <takato@vip.qq.com>
     */
    abstract public function getApiPrefix();

    /**
     * @return string
     * @author         JohnWang <takato@vip.qq.com>
     */
    public abstract function getApiGatewayPrefix();

    /**
     * @return array
     * @author         JohnWang <takato@vip.qq.com>
     */
    public function getApiGatewayConfig()
    {
        return [
            'app_id'     => config('api_requests.app_id'),
            'app_secret' => config('api_requests.app_secret')
        ];
    }

    /**
     * Return the http instance.
     *
     */
    public function getHttp()
    {
        if (is_null($this->http)) {
            $this->http = new Http();
        }

        if (count($this->http->getMiddlewares()) === 0) {
            $this->registerHttpMiddlewares();
            $this->registerCustomMiddlewares();
        }

        return $this->http;
    }

    /**
     * Set the http instance.
     *
     * @param \Jhk\ApiRequests\Core\Http $http
     * @return $this
     */
    public function setHttp(Http $http)
    {
        $this->http = $http;

        return $this;
    }

    /**
     * Parse JSON from response and check error.
     *
     * @param string $method
     * @param array  $args
     *
     * @return \Illuminate\Support\Collection
     */
    public function parseJSON($method, array $args)
    {
        $headers = [];
        if(isset($args[1])){
            $input = (array)$args[1];
            if(array_key_exists('headers',$input)){
                $headers = (array)$input['headers'];
            }
        }
        Http::setDefaultOptions([
            'headers'=>$headers
        ]);

        $http = $this->getHttp();

        if(!starts_with($args[0],'http')){
            $args[0] = $this->getApiPrefix() . $args[0];
        }

        try {
            $response = call_user_func_array([$http, $method], $args);
            $headers = $response->getHeaders();
            $contents = $http->parseJSON($response);
        } catch (TransferException $e) {
            $responseString = $e->getResponse()->getBody()->getContents();
            $response = json_decode($responseString);
            if($e instanceof ClientException && $response && isset($response->error) && $response->error === 'invalid_token'){
                //此种情况是 Kong Token 过期，则尝试重新获取
                $apiGatewayConfig = $this->getApiGatewayConfig();
                $client = new Client($apiGatewayConfig['app_id'], $apiGatewayConfig['app_secret']);
                $token = new Token($client);
                $token->refreshToken($this->getApiGatewayPrefix());
                return $this->parseJSON($method,$args);
            }
            $returnCode = isset($response->result_code) ? $response->result_code : 0;
            $returnMessage = isset($response->message) ? $response->message : $responseString;
            switch ($returnCode){
                case 200000:
                    throw new RequestErrorException($returnMessage,$returnCode);
                case 200001:
                    throw new ApiClosedException($returnMessage,$returnCode);
                case 200002:
                    throw new ResourceNotFoundException($returnMessage,$returnCode);
                case 200003:
                    throw new ParameterIllegalException($returnMessage,$returnCode);
                case 200004:
                    throw new AuthErrorException($returnMessage,$returnCode);
                default:
                    throw new HttpException(isset($response->message) ? $response->message : $responseString, isset($response->result_code) ? $response->result_code : 0, $response);
            }

        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode());
        }

        $contents === false || $contents = $this->checkAndThrow($contents);
        $contents = new ResponseCollection($contents);

        $headerList = [];
        foreach ($headers as $key => $header) {
            $headerList[$key] = array_first($header);
        }

        $contents->setHeaders($headerList);

        return $contents;
    }

    /**
     * Register Guzzle middlewares.
     */
    protected function registerHttpMiddlewares()
    {
        // signature API GATEWAY
        if (env('ENABLE_API_GATEWAY_AUTH', true) == true) {
            $this->http->addMiddleware($this->apiGatewaySignatureMiddleware());
        }
    }

    /**
     * Register Custom Moddlewares
     */
    protected function registerCustomMiddlewares()
    {
        $middlewares = config('api_requests.middlewares');

        if (!$middlewares || !is_array($middlewares)) {
            return;
        }

        foreach ($middlewares as $middleware) {
            $this->http->addMiddleware($middleware::register(Container::getInstance()));
        }

    }

    /**
     * Attache Signature to request query.
     *
     * @return \Closure
     */
    protected function apiGatewaySignatureMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $uri = $request->getUri();
                $apiGatewayConfig = $this->getApiGatewayConfig();
                $client = new Client($apiGatewayConfig['app_id'], $apiGatewayConfig['app_secret']);
                $token = new Token($client);
                $params = [
                    'access_token' => $token->getToken($this->getApiGatewayPrefix())
                ];

                // 排序
                ksort($params, SORT_STRING);

                // 生成校验签名
                foreach ($params as $key => $value) {
                    $uri = Uri::withQueryValue($uri, $key, $value);
                }

                $request = $request->withUri($uri);

                return $handler($request, $options);
            };
        };
    }

    /**
     * Check the array data errors, and Throw exception when the contents contains error.
     *
     * @param array $contents
     * @throws \Jhk\Document\Exceptions\HttpException
     */
    protected function checkAndThrow(array $contents)
    {
        return $contents['response'];
    }

    protected function parseResponse($response, $defaultEntity = null)
    {
        $defaultEntity = $defaultEntity ? : $this->defaultEntity;
        if (!$defaultEntity) {
            return $response;
        }

        if ($response->has('meta')) {
            $list = $response->get('data');

            foreach ($list as $key => $item) {
                $list[$key] = new $defaultEntity($item);
            }

            $result = new ResponseLengthAwarePaginator(
                $list,
                $response->get('meta')['pagination']['total'],
                $response->get('meta')['pagination']['per_page'],
                $response->get('meta')['pagination']['current_page']
            );

            $result->setHeaders($response->getHeaders());
        } else if ($response->has('data') && $response->count() === 1) {
            $list = $response->get('data');

            foreach ($list as $key => $item) {
                $list[$key] = new $defaultEntity($item);
            }

            $result = new ResponseCollection($list);

            $result->setHeaders($response->getHeaders());
        } else {
            $result = new $defaultEntity($response->toArray());

            $result->setHeaders($response->getHeaders());
        }

        return $result;
    }
}