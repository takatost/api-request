<?php namespace Takatost\ApiRequests\Core;
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
    public function implodeInputQuery(&$input, array $searchEqualParams = [], array $searchLikeParams = [])
    {
        $requestParams = $input;

        $equalMap = [];
        $likeMap  = [];
        foreach ($requestParams as $requestParam => $value) {
            if (in_array($requestParam, $searchEqualParams)) {
                $equalMap[$requestParam] = $value;
            } elseif (in_array($requestParam, $searchLikeParams)) {
                $likeMap[$requestParam] = $value;
            }
        }

        //开始拼接
        $searchString       = '';
        $searchFieldsString = '';
        $mergeArray         = array_merge($equalMap, $likeMap);
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
            $searchString       = trim($searchString, ';');
            $searchFieldsString = trim($searchFieldsString, ';');
        }

        if ($searchString) {
            $input = array_merge($input, [
                'search' => $searchString,
                //            'searchFields' => $searchFieldsString
            ]);
        }
        return $input;
    }

    /**
     * 解析数组形式的查询参数
     * 例如:
     * $input = ["name" => '名字',['title', 'like', '标题'],['start_time', '>', '2017-09-12'],'ep_key'=>'test','page'=>1];
     * $searchAllowParams = ['name', 'title', 'start_time'];
     * 则结果为:['ep_key'=>'test','page'=>1,'search'=>'name:名字;title:标题;start_time:2017-09-12','searchFields'=>'name:=;title:like;start_time:>']
     * @param $input
     * @param $searchAllowParams
     * @return mixed
     * @author wanghaiming@vchangyi.com
     */
    public function parseQueryInput(&$input, $searchAllowParams)
    {
        $requestParams      = $input;
        $searchString       = '';
        $searchFieldsString = '';
        foreach ($requestParams as $key => $param) {
            //如果是数组形式的条件表达式
            if (is_numeric($key) && in_array($param[0], $searchAllowParams, true)) {
                $searchString .= $param[0] . ':' . urlencode($param[2]) . ';';
                $searchFieldsString .= $param[0] . ':' . $param[1] . ';';
                unset($input[$key]);
            } elseif (in_array($key, $searchAllowParams, true)) {
                $searchString .= $key . ':' . urlencode($param) . ';';
                $searchFieldsString .= $key . ':=;';
                unset($input[$key]);
            }
        }
        $searchString          = trim($searchString, ';');
        $searchFieldsString    = trim($searchFieldsString, ';');
        $input['search']       = $searchString;
        $input['searchFields'] = $searchFieldsString;
        return $input;
    }
}