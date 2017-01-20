<?php namespace Jhk\ApiRequests\Core;
/**
 * Created by PhpStorm.
 * DateTime: 2017/1/20 16:48
 * Author: Zhengqian.zhu <zhuzhengqian@vchangyi.com>
 */
trait L5RepositoryAdvanceQueryTrait
{
    /**
     *
     * @param $input
     * @param array $searchEqualParams
     * @param array $searchLikeParams
     * @return array
     * @author: Zhengqian.zhu <zhuzhengqian@vchangyi.com>
     */
    public function implodeInputQuery($input, array $searchEqualParams = [], array $searchLikeParams = [])
    {
        $requestParams = \Request::all();
        $equalMap = [];
        $likeMap = [];
        foreach ($requestParams as $requestParam => $value) {
            if (in_array($requestParam, $searchEqualParams)) {
                $equalMap[$requestParam] = $value;
            } elseif (in_array($requestParam, $searchLikeParams)) {
                $likeMap[$requestParam] = $value;
            }
        }

        //开始拼接
        $searchString = '';
        $searchFieldsString = '';
        $mergeArray = array_merge($equalMap, $likeMap);
        if ($mergeArray) {
            foreach ($mergeArray as $k => $v) {
                $searchString .= ';' . $k . ':' . $v;
                if (in_array($k, $searchEqualParams)) {
                    $searchFieldsString .= ';' . $k . ':=';
                } elseif (in_array($k, $searchLikeParams)) {
                    $searchFieldsString .= ';' . $k . ':like';
                }

                //剔除已经在高级查询里面的key
                if (array_key_exists($k, $input)) {
                    unset($input[$k]);
                }
            }
            $searchString = trim($searchString, ';');
            $searchFieldsString = trim($searchFieldsString, ';');
        }

        if ($searchString) {
            $input = array_merge($input, [
                'search' => $searchString,
            ]);
        }

        if ($searchFieldsString) {
            $input = array_merge($input, [
                'searchFields' => $searchFieldsString
            ]);
        }

        return $input;
    }
}