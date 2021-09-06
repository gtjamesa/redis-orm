<?php

namespace JamesAusten\RedisORM\Tests\UUID;

use JamesAusten\RedisORM\Tests\TestCase;
use JamesAusten\RedisORM\UUID\Factory;
use JamesAusten\RedisORM\UUID\Webpatser;

class FactoryTest extends TestCase
{
    /** @test */
    public function should_create_uuid()
    {
        $uuidFactory = new Webpatser();
        $uuid = $uuidFactory->create();

        $this->assertTrue(is_string($uuid));
        $this->assertSame(36, strlen($uuid));
    }

    /** @test */
    public function should_create_uuid_from_singleton()
    {
        app()->bind(Factory::class, Webpatser::class);

        $uuidFactory = app(Factory::class);
        $uuid = $uuidFactory->create();

        $this->assertTrue(is_string($uuid));
        $this->assertSame(36, strlen($uuid));
    }
}
