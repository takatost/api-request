<?php namespace Takatost\ApiRequests\Exceptions;
/**
 * Class HttpException.
 */
class HttpException extends \Exception
{
    protected $response;

    /**
     * PaymentPayingException constructor.
     * @param string $message
     * @param int    $code
     * @param        $response
     */
    public function __construct($message = "", $code = 0, $response = null)
    {
        parent::__construct($message, $code);

        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}