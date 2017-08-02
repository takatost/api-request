<?php
/**
 * Created by PhpStorm.
 * User: JohnWang <takato@vip.qq.com>
 * Date: 2017/8/2
 * Time: 10:41
 */

namespace Jhk\ApiRequests\Core;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class ResponseLengthAwarePaginator
 * @author  JohnWang <takato@vip.qq.com>
 * @package Jhk\ApiRequests\Core
 */
class ResponseLengthAwarePaginator extends LengthAwarePaginator
{
    protected $headers = [];

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }
}