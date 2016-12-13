<?php namespace Jhk\ApiRequests\Core;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Collection;
use Jhk\ApiRequests\Exceptions\HttpException;
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
    protected $apiPrefix;

    /**
     * @var array
     */
    protected $apiGatewayConfig = [
        'app_id'           => '',
        'app_secret'       => '',
        'oauth_url_prefix' => ''
    ];

    const GET   = 'get';
    const POST  = 'post';
    const PATCH = 'patch';
    const PUT   = 'put';
    const JSON  = 'json';

    /**
     * @param string $prefix
     * @return $this
     * @author         JohnWang <takato@vip.qq.com>
     */
    public function setApiPrefix($prefix)
    {
        $this->apiPrefix = $prefix;

        return $this;
    }

    /**
     * @return string
     * @author         JohnWang <takato@vip.qq.com>
     */
    public function getApiPrefix()
    {
        return $this->apiPrefix;
    }

    /**
     * @param $config
     * @return $this
     * @author         JohnWang <takato@vip.qq.com>
     */
    public function setApiGatewayConfig($config)
    {
        $this->apiGatewayConfig = $config;

        return $this;
    }

    /**
     * @return array
     * @author         JohnWang <takato@vip.qq.com>
     */
    public function getApiGatewayConfig()
    {
        return $this->apiGatewayConfig;
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
        $http = $this->getHttp();

        $args[0] = $this->apiPrefix . $args[0];

        try {
            $contents = $http->parseJSON(call_user_func_array([$http, $method], $args));
        } catch (BadResponseException $e) {
            $responseString = $e->getResponse()->getBody()->getContents();
            $response = json_decode($responseString);
            throw new HttpException(isset($response->message) ? $response->message : $responseString, isset($response->result_code) ? $response->result_code : 0);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode());
        }

        $contents = $this->checkAndThrow($contents);

        return new Collection($contents);
    }

    /**
     * Register Guzzle middlewares.
     */
    protected function registerHttpMiddlewares()
    {
        // signature API GATEWAY
        if (env('ENABLE_API_GATEWAY_AUTH', false) == true) {
            $this->http->addMiddleware($this->apiGatewaySignatureMiddleware());
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
                $client = new Client($this->apiGatewayConfig['app_id'], $this->apiGatewayConfig['app_secret']);
                $token = new Token($client);
                $params = [
                    'access_token' => $token->getToken($this->apiGatewayConfig['oauth_url_prefix'])
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
}
