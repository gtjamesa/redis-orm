<?php

namespace JamesAusten\RedisORM;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use JamesAusten\RedisORM\Helpers\Model\GetAttributes;
use JamesAusten\RedisORM\Redis\TTL;

abstract class Model
{
    use GetAttributes;

    /**
     * The namespace used by the model
     *
     * @var string|null
     */
    protected $namespace;

    /**
     * The identifier used by the model.
     *
     * This is appended to the namespace
     *
     * @var string
     */
    protected $key;

    /**
     * Indicates the time in seconds that the model should expire
     *
     * @var int
     */
    protected $expiryTtl = 0;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Indicates whether the model uses a static key (always supplied) or dynamic (uuid)
     *
     * @var bool
     */
    protected $hasStaticKey = false;

    private $attributes = [];

    protected $objectIdentifier;

    protected $saved = false;

    /**
     * Construct a Redis Model
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Create a Model instance and persist into Redis
     *
     * @param array      $attributes
     *
     * @param null $key
     *
     * @return static
     */
    public static function create(array $attributes, $key = null)
    {
        if ($key) {
            $attributes = array_merge($attributes, [
                'modelObjectIdentifier' => $key,
            ]);
        }

        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Find a Model instance persisted into Redis, by its `objectIdentifier`
     *
     * @param string $objectIdentifier
     *
     * @return static|null
     */
    public static function find(string $objectIdentifier)
    {
        // Create a new object instance to access the object definitions
        $object = new static();

        // Format the key
        $redisKey = self::formatKey($object->getNamespace(), $object->getKey(), true, $objectIdentifier);

        // Return null if the object doesn't exist
        if (Redis::exists($redisKey) === 0) {
            return null;
        }

        $attributes = Redis::hgetall($redisKey);

        return new static(array_merge([
            'modelObjectIdentifier' => $objectIdentifier,
        ], $attributes));
    }

    /**
     * Find a Model instance persisted into Redis, by its `objectIdentifier`. If no model is found in Redis,
     * then it will be created using the `attributes` provided.
     *
     * @param string $objectIdentifier
     * @param array  $attributes
     *
     * @return static|null
     */
    public static function findOrCreate(string $objectIdentifier, array $attributes)
    {
        $model = static::find($objectIdentifier);

        if ($model !== null) {
            return $model;
        }

        return static::create($attributes, $objectIdentifier);
    }

    /**
     * Fill the model with an array of attributes
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function fill(array $attributes)
    {
        if (array_key_exists('modelObjectIdentifier', $attributes)) {
            $this->objectIdentifier = $attributes['modelObjectIdentifier'];
            $this->saved = true;
        }

        $this->attributes = Arr::except($attributes, ['modelObjectIdentifier']);

        return $this;
    }

    /**
     * Save the model
     */
    public function save()
    {
        // Store the object identifier
        if (!$this->objectIdentifier) {
            $this->objectIdentifier = $this->generateUuid();
        }

        // Mark the object as saved
        $this->saved = true;

        // Store the Redis hash
        Redis::hmset($this->getRedisKey(), $this->attributes);

        if ($this->shouldSetExpires()) {
            $this->setExpires($this->expiryTtl);
        }
    }

    /**
     * Determine whether we should set EXPIRY on the key
     *
     * @return bool
     */
    private function shouldSetExpires()
    {
        $expiresIn = ($this->willExpire()) ? $this->expiresIn() : 0;

        if ($this->willExpire() && ($expiresIn === TTL::NON_EXISTENT || $expiresIn === TTL::NO_EXPIRY)) {
            return true;
        }

        return false;
    }

    /**
     * Set expiry on the key
     *
     * @param int $ttl
     */
    public function setExpires($ttl)
    {
        Redis::expire($this->getRedisKey(), $ttl);
    }

    /**
     * Increment a number
     *
     * @param string $key
     * @param int|float $increment
     *
     * @return mixed
     */
    public function incrBy(string $key, $increment)
    {
        // Run HINCRBY in Redis
        $newValue = Redis::hIncrBy($this->getRedisKey(), $key, $increment);

        // Update the attribute value
        $this->attributes[$key] = $newValue;

        return $this->getAttribute($key);
    }

    /**
     * Increment a number
     *
     * @param string    $key
     * @param int|float $increment
     *
     * @return mixed
     */
    public function decrBy(string $key, $increment)
    {
        return $this->incrBy($key, abs($increment) * -1);
    }

    /**
     * Get the key for the model
     *
     * @return string
     */
    public function getRedisKey()
    {
        return static::formatKey($this->namespace, $this->key, $this->saved, $this->objectIdentifier);
    }

    /**
     * Format a key to include the `objectIdentifier` (UUID)
     *
     * @param string $namespace
     * @param string $key
     * @param bool   $saved
     * @param string $objectIdentifier
     *
     * @return string
     */
    protected static function formatKey($namespace, $key, $saved, $objectIdentifier)
    {
        if ($saved && $objectIdentifier) {
            $key = $key . '.' . $objectIdentifier;
        }

        if ($namespace) {
            return $namespace . ':' . $key;
        }

        return $key;
    }

    /**
     * Generate a UUID using the bound UUIDFactory
     *
     * @return string
     */
    protected function generateUuid()
    {
        return (string)Str::uuid();
    }

    /**
     * @return string|null
     */
    public function getObjectIdentifier()
    {
        return $this->objectIdentifier;
    }

    /**
     * @return string|null
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Indicates whether the model is configured to expire automatically
     *
     * @return bool
     */
    public function willExpire(): bool
    {
        return $this->expiryTtl > 0;
    }

    /**
     * Get the instance max expiry TTL
     *
     * @return int
     */
    public function getExpiryTtl(): int
    {
        return $this->expiryTtl;
    }

    /**
     * Get the remaining seconds until key expiry
     *
     * @return int
     */
    public function expiresIn(): int
    {
        return Redis::ttl($this->getRedisKey());
    }

    /**
     * Get an attribute from the model
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Set an attribute on the model
     *
     * @param string           $key
     * @param string|int|float $value
     */
    public function setAttribute(string $key, $value): void
    {
        if (array_key_exists($key, $this->attributes)) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function __set(string $name, $value)
    {
        $this->setAttribute($name, $value);
    }
}
