<?php

namespace JamesAusten\RedisORM\UUID;

interface Factory
{
    /**
     * Creates a version-4 UUID and returns the string
     *
     * @return string
     */
    public function create();
}
