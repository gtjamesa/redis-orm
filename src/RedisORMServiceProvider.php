<?php

namespace JamesAusten\RedisORM;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use JamesAusten\RedisORM\UUID\Factory as UuidFactory;
use JamesAusten\RedisORM\UUID\Webpatser;

class RedisORMServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(UuidFactory::class, function ($app) {
            return new Webpatser();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function provides()
    {
        return [UuidFactory::class];
    }
}
