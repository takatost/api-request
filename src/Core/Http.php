<?php namespace Takatost\ApiRequests\Core;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Stream;
use Takatost\ApiRequests\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Http.
 */
class Http
{
    /**
     * Http client.
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * The middlewares.
     *
     * @var array
     */
    protected $middlewares = [];

    /**
     * Guzzle client default settings.
     *
     * @var array
     */
    protected static $defaults = [];

    /**
     * Set guzzle default settings.
     *
     * @param array $defaults
     */
    public static function setDefaultOptions($defaults = [])
    {
        self::$defaults = $defaults;
    }

    /**
     * Return current guzzle default settings.
     *
     * @return array
     */
    public static function getDefaultOptions()
    {
        return self::$defaults;
    }

    /**
     * GET request.
     *
     * @param string $url
     * @param array  $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function get($url, array $options = [])
    {
        return $this->request($url, 'GET', ['query' => $options]);
    }

    /**
     * POST request.
     *
     * @param string       $url
     * @param array|string $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function post($url, $options = [])
    {
        $key = is_array($options) ? 'form_params' : 'body';

        return $this->request($url, 'POST', [$key => $options]);
    }

    /**
     * PATCH request.
     *
     * @param string       $url
     * @param array|string $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function patch($url, $options = [])
    {
        $key = is_array($options) ? 'form_params' : 'body';

        return $this->request($url, 'PATCH', [$key => $options]);
    }

    /**
     * PUT request.
     *
     * @param string       $url
     * @param array|string $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function put($url, $options = [])
    {
        $key = is_array($options) ? 'form_params' : 'body';

        return $this->request($url, 'PUT', [$key => $options]);
    }

    public function delete($url, $options = [])
    {
        $key = is_array($options) ? 'form_params' : 'body';

        return $this->request($url, 'DELETE', [$key => $options]);
    }

    /**
     * JSON request.
     *
     * @param string       $url
     * @param string|array $options
     * @param int          $encodeOption
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function json($url, $options = [], $encodeOption = JSON_UNESCAPED_UNICODE)
    {
        is_array($options) && $options = json_encode($options, $encodeOption);

        return $this->request($url, 'POST', ['body' => $options, 'headers' => ['content-type' => 'application/json']]);
    }

    /**
     * Upload file.
     *
     * @param string $url
     * @param array  $files
     * @param array  $form
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function upload($url, array $files = [], array $form = [], array $queries = [])
    {
        $multipart = [];

        foreach ($files as $name => $path) {
            $multipart[] = [
                'name' => $name,
                'contents' => fopen($path, 'r'),
            ];
        }

        foreach ($form as $name => $contents) {
            $multipart[] = compact('name', 'contents');
        }

        return $this->request($url, 'POST', ['query' => $queries, 'multipart' => $multipart]);
    }

    /**
     * Set GuzzleHttp\Client.
     *
     * @param \GuzzleHttp\Client $client
     *
     * @return Http
     */
    public function setClient(HttpClient $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Return GuzzleHttp\Client instance.
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        if (!($this->client instanceof HttpClient)) {
            $this->client = new HttpClient();
        }

        return $this->client;
    }

    /**
     * Add a middleware.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function addMiddleware(callable $middleware)
    {
        array_push($this->middlewares, $middleware);

        return $this;
    }

    /**
     * Return all middlewares.
     *
     * @return array
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    /**
     * Make a request.
     *
     * @param string $url
     * @param string $method
     * @param array  $options
     *
     * @return ResponseInterface
     *
     * @throws HttpException
     */
    public function request($url, $method = 'GET', $options = [])
    {
        $method = strtoupper($method);

        $options = array_merge(self::$defaults, $options);

        $options['handler'] = $this->getHandler();

        return $this->getClient()->request($method, $url, $options);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface|string $body
     *
     * @return mixed
     *
     * @throws \EasyWeChat\Core\Exceptions\HttpException
     */
    public function parseJSON($body)
    {
        if ($body instanceof ResponseInterface) {
            $body = $body->getBody();
        }

        if (empty($body) || ($body instanceof Stream && !$body->getContents())) {
            return false;
        }

        $contents = json_decode($body, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new HttpException('Failed to parse JSON: '.json_last_error_msg());
        }

        return $contents;
    }

    /**
     * Build a handler.
     *
     * @return HandlerStack
     */
    protected function getHandler()
    {
        $stack = HandlerStack::create();

        foreach ($this->middlewares as $middleware) {
            $stack->push($middleware);
        }

        return $stack;
    }
}

