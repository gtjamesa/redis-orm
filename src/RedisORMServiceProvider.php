<?php

namespace JamesAusten\RedisORM;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class RedisORMServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function provides()
    {
        return [];
    }
}
