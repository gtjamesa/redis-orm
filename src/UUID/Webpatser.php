<?php

namespace JamesAusten\RedisORM\UUID;

use Webpatser\Uuid\Uuid;

class Webpatser implements Factory
{
    /**
     * Creates a version-4 UUID and returns the string
     *
     * @return string
     */
    public function create(): string
    {
        return Uuid::generate(4)->string;
    }
}
