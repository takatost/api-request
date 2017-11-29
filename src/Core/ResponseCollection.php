<?php
/**
 * Created by PhpStorm.
 * User: JohnWang <takato@vip.qq.com>
 * Date: 2017/8/2
 * Time: 10:44
 */

namespace Takatost\ApiRequests\Core;

use Illuminate\Support\Collection;

/**
 * Class ResponseCollection
 * @author  JohnWang <takato@vip.qq.com>
 * @package Jhk\ApiRequests\Core
 */
class ResponseCollection extends Collection
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