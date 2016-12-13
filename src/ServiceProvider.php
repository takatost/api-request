<?php namespace Jhk\Document;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Class ServiceProvider
 * @author  JohnWang <takato@vip.qq.com>
 * @package Jhk\Document
 */
class ServiceProvider extends LaravelServiceProvider
{

    /**
     * 延时加载
     * @var bool
     */
    protected $defer = true;

    public function boot()
    {
        $source = realpath(__DIR__.'/config.php');
        $this->mergeConfigFrom($source, 'api_requests');
    }

}