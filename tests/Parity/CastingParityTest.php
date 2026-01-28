<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentProfile;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentTestCastableModel;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableProfile;
use Brighten\ImmutableModel\Tests\Models\TestCastableModel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Tests that ImmutableModel casting matches Eloquent casting behavior exactly.
 */
class CastingParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCastingTestData();

        // Set up test castable models
        TestCastableModel::setConnectionResolver($this->app['db']);
        EloquentTestCastableModel::setConnectionResolver($this->app['db']);
    }

    protected function seedCastingTestData(): void
    {
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => json_encode(['theme' => 'dark', 'notifications' => true, 'count' => 42]),
                'email_verified_at' => '2024-06-15 10:30:00',
                'supplier_id' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'supplier_id' => null,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
        ]);

        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'Test Post',
                'body' => 'Content here',
                'published' => 1,
                'created_at' => '2024-01-01 12:00:00',
                'updated_at' => '2024-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'Unpublished',
                'body' => 'Draft',
                'published' => 0,
                'created_at' => '2024-01-02 12:00:00',
                'updated_at' => '2024-01-02 12:00:00',
            ],
        ]);

        $this->app['db']->table('profiles')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'bio' => 'Developer',
                'birthday' => '1990-05-15',
            ],
        ]);
    }

    // =========================================================================
    // DATETIME CASTING
    // =========================================================================

    public function test_datetime_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertInstanceOf(Carbon::class, $eloquent->email_verified_at);
        $this->assertInstanceOf(Carbon::class, $immutable->email_verified_at);

        $this->assertEquals(
            $eloquent->email_verified_at->toDateTimeString(),
            $immutable->email_verified_at->toDateTimeString()
        );
    }

    public function test_datetime_cast_null(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        $this->assertNull($eloquent->email_verified_at);
        $this->assertNull($immutable->email_verified_at);
    }

    public function test_created_at_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertInstanceOf(Carbon::class, $eloquent->created_at);
        $this->assertInstanceOf(Carbon::class, $immutable->created_at);

        $this->assertEquals(
            $eloquent->created_at->toDateTimeString(),
            $immutable->created_at->toDateTimeString()
        );
    }

    public function test_updated_at_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertInstanceOf(Carbon::class, $eloquent->updated_at);
        $this->assertInstanceOf(Carbon::class, $immutable->updated_at);

        $this->assertEquals(
            $eloquent->updated_at->toDateTimeString(),
            $immutable->updated_at->toDateTimeString()
        );
    }

    // =========================================================================
    // DATE CASTING
    // =========================================================================

    public function test_date_cast(): void
    {
        $eloquent = EloquentProfile::find(1);
        $immutable = ImmutableProfile::find(1);

        $this->assertInstanceOf(Carbon::class, $eloquent->birthday);
        $this->assertInstanceOf(Carbon::class, $immutable->birthday);

        $this->assertEquals(
            $eloquent->birthday->toDateString(),
            $immutable->birthday->toDateString()
        );

        // Date should be at midnight
        $this->assertEquals('00:00:00', $eloquent->birthday->toTimeString());
        $this->assertEquals('00:00:00', $immutable->birthday->toTimeString());
    }

    // =========================================================================
    // ARRAY CASTING
    // =========================================================================

    public function test_array_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertIsArray($eloquent->settings);
        $this->assertIsArray($immutable->settings);

        $this->assertEquals($eloquent->settings, $immutable->settings);
        $this->assertEquals('dark', $eloquent->settings['theme']);
        $this->assertEquals('dark', $immutable->settings['theme']);
    }

    public function test_array_cast_null(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        $this->assertNull($eloquent->settings);
        $this->assertNull($immutable->settings);
    }

    public function test_array_cast_nested_values(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Test nested boolean
        $this->assertTrue($eloquent->settings['notifications']);
        $this->assertTrue($immutable->settings['notifications']);

        // Test nested number
        $this->assertEquals(42, $eloquent->settings['count']);
        $this->assertEquals(42, $immutable->settings['count']);
    }

    // =========================================================================
    // BOOLEAN CASTING
    // =========================================================================

    public function test_bool_cast_true(): void
    {
        $eloquent = EloquentPost::find(1);
        $immutable = ImmutablePost::find(1);

        $this->assertIsBool($eloquent->published);
        $this->assertIsBool($immutable->published);

        $this->assertTrue($eloquent->published);
        $this->assertTrue($immutable->published);
    }

    public function test_bool_cast_false(): void
    {
        $eloquent = EloquentPost::find(2);
        $immutable = ImmutablePost::find(2);

        $this->assertIsBool($eloquent->published);
        $this->assertIsBool($immutable->published);

        $this->assertFalse($eloquent->published);
        $this->assertFalse($immutable->published);
    }

    // =========================================================================
    // INTEGER CASTING
    // =========================================================================

    public function test_int_cast(): void
    {
        $eloquent = EloquentPost::find(1);
        $immutable = ImmutablePost::find(1);

        $this->assertIsInt($eloquent->user_id);
        $this->assertIsInt($immutable->user_id);

        $this->assertEquals($eloquent->user_id, $immutable->user_id);
    }

    public function test_int_cast_null(): void
    {
        $eloquent = EloquentPost::find(1);
        $immutable = ImmutablePost::find(1);

        // category_id is null
        $this->assertNull($eloquent->category_id);
        $this->assertNull($immutable->category_id);
    }

    // =========================================================================
    // RAW ATTRIBUTES
    // =========================================================================

    public function test_get_attributes_returns_raw(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentAttrs = $eloquent->getAttributes();
        $immutableAttrs = $immutable->getAttributes();

        // Raw settings should be JSON string, not array
        $this->assertIsString($eloquentAttrs['settings']);
        $this->assertIsString($immutableAttrs['settings']);

        $this->assertEquals($eloquentAttrs['settings'], $immutableAttrs['settings']);
    }

    // =========================================================================
    // TYPE CONSISTENCY
    // =========================================================================

    public function test_cast_types_match(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Check that all cast types match
        $this->assertSame(gettype($eloquent->settings), gettype($immutable->settings));
        $this->assertSame(gettype($eloquent->email_verified_at), gettype($immutable->email_verified_at));
        $this->assertSame(gettype($eloquent->created_at), gettype($immutable->created_at));
    }

    public function test_uncast_attribute_types(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // name and email are not cast, should be strings
        $this->assertIsString($eloquent->name);
        $this->assertIsString($immutable->name);

        $this->assertEquals($eloquent->name, $immutable->name);
    }

    // =========================================================================
    // OBJECT CASTING PARITY
    // =========================================================================

    public function test_object_cast_parity(): void
    {
        // Create test table for castable models
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '{"name": "John", "age": 30}',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'object'];
        TestCastableModel::$testCasts = ['value' => 'object'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        // Both should be stdClass
        $this->assertInstanceOf(\stdClass::class, $eloquent->value);
        $this->assertInstanceOf(\stdClass::class, $immutable->value);

        // Values should match
        $this->assertEquals($eloquent->value->name, $immutable->value->name);
        $this->assertEquals($eloquent->value->age, $immutable->value->age);
    }

    public function test_object_cast_nested_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '{"user": {"name": "John", "settings": {"theme": "dark"}}}',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'object'];
        TestCastableModel::$testCasts = ['value' => 'object'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        // Nested objects should match
        $this->assertEquals($eloquent->value->user->name, $immutable->value->user->name);
        $this->assertEquals($eloquent->value->user->settings->theme, $immutable->value->user->settings->theme);
    }

    public function test_object_cast_null_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => null,
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'object'];
        TestCastableModel::$testCasts = ['value' => 'object'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        $this->assertNull($eloquent->value);
        $this->assertNull($immutable->value);
    }

    // =========================================================================
    // IMMUTABLE DATE CASTING PARITY
    // =========================================================================

    public function test_immutable_date_cast_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '2024-06-15',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'immutable_date'];
        TestCastableModel::$testCasts = ['value' => 'immutable_date'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        // Both should be CarbonImmutable
        $this->assertInstanceOf(CarbonImmutable::class, $eloquent->value);
        $this->assertInstanceOf(CarbonImmutable::class, $immutable->value);

        // Values should match
        $this->assertEquals(
            $eloquent->value->toDateString(),
            $immutable->value->toDateString()
        );

        // Time should be at midnight
        $this->assertEquals('00:00:00', $eloquent->value->toTimeString());
        $this->assertEquals('00:00:00', $immutable->value->toTimeString());
    }

    public function test_immutable_datetime_cast_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '2024-06-15 14:30:00',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'immutable_datetime'];
        TestCastableModel::$testCasts = ['value' => 'immutable_datetime'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        // Both should be CarbonImmutable
        $this->assertInstanceOf(CarbonImmutable::class, $eloquent->value);
        $this->assertInstanceOf(CarbonImmutable::class, $immutable->value);

        // Values should match exactly
        $this->assertEquals(
            $eloquent->value->toDateTimeString(),
            $immutable->value->toDateTimeString()
        );
    }

    public function test_immutable_date_null_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => null,
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'immutable_date'];
        TestCastableModel::$testCasts = ['value' => 'immutable_date'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        $this->assertNull($eloquent->value);
        $this->assertNull($immutable->value);
    }

    // =========================================================================
    // DECIMAL CASTING PARITY
    // =========================================================================

    public function test_decimal_cast_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '123.456789',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'decimal:2'];
        TestCastableModel::$testCasts = ['value' => 'decimal:2'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        $this->assertEquals($eloquent->value, $immutable->value);
        $this->assertEquals('123.46', $immutable->value);
    }

    public function test_decimal_cast_high_precision_parity(): void
    {
        $this->createTestTable();

        $this->app['db']->table('test')->insert([
            'id' => 1,
            'value' => '99.123456789',
        ]);

        EloquentTestCastableModel::$testCasts = ['value' => 'decimal:6'];
        TestCastableModel::$testCasts = ['value' => 'decimal:6'];

        $eloquent = EloquentTestCastableModel::find(1);
        $immutable = TestCastableModel::find(1);

        $this->assertEquals($eloquent->value, $immutable->value);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create the test table for TestCastableModel.
     */
    protected function createTestTable(): void
    {
        $this->app['db']->getSchemaBuilder()->dropIfExists('test');
        $this->app['db']->getSchemaBuilder()->create('test', function ($table) {
            $table->increments('id');
            $table->text('value')->nullable();
        });
    }
}
