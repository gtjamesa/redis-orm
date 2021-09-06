<?php

namespace JamesAusten\RedisORM\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use JamesAusten\RedisORM\Model;

class ModelTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Redis::del('test');
        Redis::del('fake:test');
        Redis::del('test:fake');
    }

    /** @test */
    public function should_create_model()
    {
        $model = new FakeModel([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertInstanceOf(Model::class, $model);
    }

    /** @test */
    public function should_generate_key()
    {
        $model = new FakeModelWithoutNamespace([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertSame('fake', $model->getRedisKey());
    }

    /** @test */
    public function should_generate_key_with_namespace()
    {
        $model = new FakeModel([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertSame('test:fake', $model->getRedisKey());
    }

    /** @test */
    public function should_access_data()
    {
        $model = new FakeModel([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertSame('attr1', $model->key1);
        $this->assertSame('attr2', $model->key2);
    }

//    /** @test */
//    public function should_throw_exception_if_attr_not_found()
//    {
//        $this->expectException(\Exception::class);
//
//        $model = new FakeModel([
//            'key1' => 'attr1',
//            'key2' => 'attr2',
//        ]);
//
//        $this->assertSame('attr1', $model->keynotfound);
//    }

    /** @test */
    public function should_return_null_if_attr_not_found()
    {
        $model = new FakeModel([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        /** @phpstan-ignore-next-line */
        $this->assertNull($model->keynotfound);
    }

    /** @test */
    public function should_format_keys_with_uuid()
    {
        $model = new FakeModelSaved([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertSame('test:fake.a97a8987-83b5-4104-aa4a-1ddc1f61cbfa', $model->getRedisKey());
    }

    /** @test */
    public function should_save_model()
    {
        $model = new FakeModel([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $model->save();

        $rawObject = Redis::hgetall($model->getRedisKey());

        $this->assertSame(1, Redis::exists($model->getRedisKey()));
        $this->assertSame(36, strlen($model->getObjectIdentifier()));

        $this->assertSame('attr1', $rawObject['key1']);
        $this->assertSame('attr1', $model->key1);

        $this->assertSame('attr2', $rawObject['key2']);
        $this->assertSame('attr2', $model->key2);
    }

    /** @test */
    public function should_create_model_from_static_method()
    {
        $model = FakeModel::create([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $rawObject = Redis::hgetall($model->getRedisKey());

        $this->assertSame(1, Redis::exists($model->getRedisKey()));
        $this->assertSame(36, strlen($model->getObjectIdentifier()));

        $this->assertSame('attr1', $rawObject['key1']);
        $this->assertSame('attr1', $model->key1);

        $this->assertSame('attr2', $rawObject['key2']);
        $this->assertSame('attr2', $model->key2);
    }

    /** @test */
    public function should_load_model_from_identifier()
    {
        // Create a model in Redis so we have a valid identifier
        $firstModel = FakeModel::create([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $foundModel = FakeModel::find($firstModel->getObjectIdentifier());

        $this->assertNotNull($foundModel);

        $this->assertSame($firstModel->getObjectIdentifier(), $foundModel->getObjectIdentifier());

        $this->assertSame($firstModel->key1, $foundModel->key1);
        $this->assertSame($firstModel->key1, $foundModel->key1);

        $this->assertSame($firstModel->key2, $foundModel->key2);
        $this->assertSame($firstModel->key2, $foundModel->key2);
    }

    /** @test */
    public function should_expire_model_automatically()
    {
        // Create a model in Redis so we have a valid identifier
        $model = FakeModelExpires::create([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $this->assertTrue($model->willExpire());
        $this->assertSame(30, $model->getExpiryTtl());
        $this->assertSame(30, $model->expiresIn());

        sleep(2);

        $this->assertSame(28, $model->expiresIn());
    }

    /** @test */
    public function should_update_attributes_and_persist()
    {
        $model = FakeModel::create([
            'key1' => 'attr1',
            'key2' => 'attr2',
        ]);

        $initialKey = $model->getRedisKey();

        $model->key1 = 'updated1';
        $model->key2 = 'updated2';
        $model->save();

        $rawObject = Redis::hgetall($model->getRedisKey());

        // Ensure the key is not being mutated during the save
        $this->assertSame($initialKey, $model->getRedisKey());

        $this->assertSame(1, Redis::exists($model->getRedisKey()));
        $this->assertSame(36, strlen($model->getObjectIdentifier()));

        $this->assertSame('updated1', $rawObject['key1']);
        $this->assertSame('updated1', $model->key1);

        $this->assertSame('updated2', $rawObject['key2']);
        $this->assertSame('updated2', $model->key2);
    }

    /** @test */
    public function should_cast_attributes()
    {
        $model = FakeModelCasts::create([
            'key1' => 1,
            'key2' => 'attr2',
            'key3' => 1561437696,
            'key4' => 0,
        ]);

        $this->assertTrue(is_bool($model->key1));
        $this->assertTrue($model->key1);

        $this->assertInstanceOf(Carbon::class, $model->key3);
        $this->assertSame('2019-06-25 05:41:36', $model->key3->format('Y-m-d H:i:s'));

        $this->assertTrue(is_bool($model->key4));
        $this->assertFalse($model->key4);
    }

    /** @test */
    public function should_create_model_with_static_key()
    {
        $model = FakeModelStaticKey::create([
            'key1' => 1,
            'key2' => 'attr2',
        ], 155);

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('attr2', $model->key2);
    }

    /** @test */
    public function should_load_model_with_static_key()
    {
        Redis::del('test:fake.155');

        // Create a model in Redis so we have a valid identifier
        $firstModel = FakeModelStaticKey::create([
            'key1' => '1',
            'key2' => 'attr2',
        ], 155);

        $foundModel = FakeModelStaticKey::find($firstModel->getObjectIdentifier());

        $this->assertNotNull($foundModel);

        $this->assertSame((int)$firstModel->getObjectIdentifier(), (int)$foundModel->getObjectIdentifier());

        $this->assertSame($firstModel->key1, $foundModel->key1);
        $this->assertSame($firstModel->key1, $foundModel->key1);

        $this->assertSame($firstModel->key2, $foundModel->key2);
        $this->assertSame($firstModel->key2, $foundModel->key2);
    }

    /** @test */
    public function should_incrby()
    {
        $model = FakeModelCasts::create([
            'key5' => 5,
            'key2' => 'attr2',
        ]);

        $this->assertSame(5, $model->key5);

        $model->incrBy('key5', 1);

        $this->assertSame(6, $model->key5);
    }

    /** @test */
    public function should_decrby()
    {
        $model = FakeModelCasts::create([
            'key5' => 5,
            'key2' => 'attr2',
        ]);

        $this->assertSame(5, $model->key5);

        $model->decrBy('key5', 3);

        $this->assertSame(2, $model->key5);
    }
}

/**
 * @property string $key1
 * @property string $key2
 */
class FakeModel extends Model
{
    protected $namespace = 'test';

    protected $key = 'fake';
}

/**
 * @property string $key1
 * @property string $key2
 */
class FakeModelWithoutNamespace extends Model
{
    protected $namespace;

    protected $key = 'fake';
}

/**
 * @property string $key1
 * @property string $key2
 */
class FakeModelSaved extends Model
{
    protected $namespace = 'test';

    protected $key = 'fake';

    protected $saved = true;

    protected $objectIdentifier = 'a97a8987-83b5-4104-aa4a-1ddc1f61cbfa';
}

/**
 * @property string $key1
 * @property string $key2
 */
class FakeModelExpires extends Model
{
    protected $namespace = 'test';

    protected $key = 'fake';

    protected $expiryTtl = 30;
}

/**
 * @property bool   $key1
 * @property string $key2
 * @property Carbon $key3
 * @property bool   $key4
 * @property int    $key5
 */
class FakeModelCasts extends Model
{
    protected $namespace = 'test';

    protected $key = 'fake';

    protected $expiryTtl = 30;

    protected $casts = [
        'key1' => 'bool',
        'key3' => 'datetime',
        'key4' => 'bool',
        'key5' => 'int',
    ];
}

/**
 * @property string $key1
 * @property string $key2
 */
class FakeModelStaticKey extends Model
{
    protected $namespace = 'test';

    protected $key = 'fake';

    protected $expiryTtl = 30;

    protected $hasStaticKey = true;
}
