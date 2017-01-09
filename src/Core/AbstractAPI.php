<?php namespace Jhk\ApiRequests\Core;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function getApiGatewayPrefix()
    {
        return config('micro_service.prefix');
    }

    /**
     * @return array
     * @author         JohnWang <takato@vip.qq.com>
     */
    public abstract function getApiGatewayConfig();

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

        $args[0] = $this->getApiPrefix() . $args[0];

        try {
            $contents = $http->parseJSON(call_user_func_array([$http, $method], $args));
        } catch (BadResponseException $e) {
            $responseString = $e->getResponse()->getBody()->getContents();
            $response = json_decode($responseString);
            $returnCode = isset($response->result_code) ? $response->result_code : 0;
            $returnMessage = isset($response->message) ? $response->message : $responseString;
            switch ($returnCode){
                case 200000:
                    throw new RequestErrorException($returnMessage,$returnCode);
                    break;
                case 200001:
                    throw new ApiClosedException($returnMessage,$returnCode);
                    break;
                case 200002:
                    throw new ResourceNotFoundException($returnMessage,$returnCode);
                    break;
                case 200003:
                    throw new ParameterIllegalException($returnMessage,$returnCode);
                    break;
                case 200004:
                    throw new AuthErrorException($returnMessage,$returnCode);
            }
            throw new HttpException(isset($response->message) ? $response->message : $responseString, isset($response->result_code) ? $response->result_code : 0);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode());
        }

        $contents === false || $contents = $this->checkAndThrow($contents);

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

    /**
     * @param Collection $response
     * @param $defaultEntity
     * @return Collection|LengthAwarePaginator|Entity
     * @author         JohnWang <takato@vip.qq.com>
     */
    protected function parseResponse($response, $defaultEntity = null)
    {
        $defaultEntity = $this->defaultEntity ?: $defaultEntity;
        if (!$defaultEntity) {
            return $response;
        }

        if ($response->has('meta')) {
            $list = $response->get('data');

            foreach ($list as $key => $item) {
                $list[$key] = new $defaultEntity($item);
            }

            return new LengthAwarePaginator(
                $list,
                $response->get('meta')['pagination']['total'],
                $response->get('meta')['pagination']['per_page'],
                $response->get('meta')['pagination']['current_page']
            );
        } else if ($response->count() === 1 && $response->has('data')) {
            $list = $response->get('data');

            foreach ($list as $key => $item) {
                $list[$key] = new $defaultEntity($item);
            }

            return new Collection($list);
        } else {
            return new $defaultEntity($response->toArray());
        }
    }
}