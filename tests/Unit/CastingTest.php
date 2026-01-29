<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Tests\Models\ImmutableProfile;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\TestCastableModel;
use Brighten\ImmutableModel\Tests\TestCase;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;

class CastingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        TestCastableModel::setConnectionResolver($this->app['db']);
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'settings' => json_encode(['theme' => 'dark', 'notifications' => true]),
            'email_verified_at' => '2024-01-15 10:30:00',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $this->app['db']->table('profiles')->insert([
            'id' => 1,
            'user_id' => 1,
            'bio' => 'Test bio',
            'birthday' => '1990-06-15',
        ]);
    }

    // =========================================================================
    // SCALAR CASTS
    // =========================================================================

    public function test_cast_to_int(): void
    {
        TestCastableModel::$testCasts = ['value' => 'int'];
        $model = TestCastableModel::fromRow(['value' => '42']);

        $this->assertSame(42, $model->value);
    }

    public function test_cast_to_float(): void
    {
        TestCastableModel::$testCasts = ['value' => 'float'];
        $model = TestCastableModel::fromRow(['value' => '3.14']);

        $this->assertSame(3.14, $model->value);
    }

    public function test_cast_to_bool(): void
    {
        TestCastableModel::$testCasts = ['value' => 'bool'];

        $trueModel = TestCastableModel::fromRow(['value' => '1']);
        $this->assertTrue($trueModel->value);

        $falseModel = TestCastableModel::fromRow(['value' => '0']);
        $this->assertFalse($falseModel->value);
    }

    public function test_cast_to_string(): void
    {
        TestCastableModel::$testCasts = ['value' => 'string'];
        $model = TestCastableModel::fromRow(['value' => 42]);

        $this->assertSame('42', $model->value);
    }

    // =========================================================================
    // ARRAY/JSON CASTS
    // =========================================================================

    public function test_cast_to_array(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertIsArray($user->settings);
        $this->assertEquals('dark', $user->settings['theme']);
        $this->assertTrue($user->settings['notifications']);
    }

    public function test_cast_to_json(): void
    {
        TestCastableModel::$testCasts = ['value' => 'json'];
        $model = TestCastableModel::fromRow(['value' => '{"key": "value"}']);

        $this->assertIsArray($model->value);
        $this->assertEquals('value', $model->value['key']);
    }

    public function test_cast_to_collection(): void
    {
        TestCastableModel::$testCasts = ['value' => 'collection'];
        $model = TestCastableModel::fromRow(['value' => '[1, 2, 3]']);

        $this->assertInstanceOf(Collection::class, $model->value);
        $this->assertEquals([1, 2, 3], $model->value->all());
    }

    // =========================================================================
    // INVALID JSON HANDLING
    // =========================================================================

    public function test_cast_to_array_throws_on_invalid_json(): void
    {
        TestCastableModel::$testCasts = ['value' => 'array'];
        $model = TestCastableModel::fromRow(['value' => 'not valid json']);

        $this->expectException(\JsonException::class);
        $model->value;
    }

    public function test_cast_to_json_throws_on_invalid_json(): void
    {
        TestCastableModel::$testCasts = ['value' => 'json'];
        $model = TestCastableModel::fromRow(['value' => '{invalid}']);

        $this->expectException(\JsonException::class);
        $model->value;
    }

    public function test_cast_to_object_throws_on_invalid_json(): void
    {
        TestCastableModel::$testCasts = ['value' => 'object'];
        $model = TestCastableModel::fromRow(['value' => 'comma,separated,string']);

        $this->expectException(\JsonException::class);
        $model->value;
    }

    public function test_cast_to_collection_throws_on_invalid_json(): void
    {
        TestCastableModel::$testCasts = ['value' => 'collection'];
        $model = TestCastableModel::fromRow(['value' => '1,2,3']);

        $this->expectException(\JsonException::class);
        $model->value;
    }

    // =========================================================================
    // INVALID JSON - DATABASE INTEGRATION
    // =========================================================================

    public function test_database_query_with_invalid_json_in_array_cast_column_throws(): void
    {
        // Simulate the real-world bug: database contains comma-separated string
        // in a column that's cast as 'array' (JSON)
        $this->app['db']->table('users')->insert([
            'id' => 999,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'settings' => '1,3,5',  // Comma-separated, NOT valid JSON
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Query the database - this is the real-world path
        $user = ImmutableUser::find(999);

        // Accessing the JSON-cast field should throw JsonException
        $this->expectException(\JsonException::class);
        $user->settings;
    }

    // =========================================================================
    // DATE/TIME CASTS
    // =========================================================================

    public function test_cast_to_datetime(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
        $this->assertEquals('2024-01-15 10:30:00', $user->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function test_cast_to_date(): void
    {
        $profile = ImmutableProfile::find(1);

        $this->assertInstanceOf(Carbon::class, $profile->birthday);
        $this->assertEquals('1990-06-15', $profile->birthday->format('Y-m-d'));
        // Date cast sets time to start of day
        $this->assertEquals('00:00:00', $profile->birthday->format('H:i:s'));
    }

    public function test_cast_to_immutable_datetime(): void
    {
        TestCastableModel::$testCasts = ['value' => 'immutable_datetime'];
        $model = TestCastableModel::fromRow(['value' => '2024-01-15 10:30:00']);

        $this->assertInstanceOf(CarbonImmutable::class, $model->value);
    }

    public function test_cast_to_timestamp(): void
    {
        TestCastableModel::$testCasts = ['value' => 'timestamp'];
        $model = TestCastableModel::fromRow(['value' => '2024-01-15 10:30:00']);

        $this->assertIsInt($model->value);
        $this->assertEquals(Carbon::parse('2024-01-15 10:30:00')->getTimestamp(), $model->value);
    }

    // =========================================================================
    // OBJECT CAST
    // =========================================================================

    public function test_cast_to_object(): void
    {
        TestCastableModel::$testCasts = ['value' => 'object'];
        $model = TestCastableModel::fromRow(['value' => '{"name": "John", "age": 30}']);

        $this->assertInstanceOf(\stdClass::class, $model->value);
        $this->assertEquals('John', $model->value->name);
        $this->assertEquals(30, $model->value->age);
    }

    public function test_cast_to_object_with_nested_data(): void
    {
        TestCastableModel::$testCasts = ['value' => 'object'];
        $model = TestCastableModel::fromRow(['value' => '{"user": {"name": "John", "email": "john@example.com"}}']);

        $this->assertInstanceOf(\stdClass::class, $model->value);
        $this->assertInstanceOf(\stdClass::class, $model->value->user);
        $this->assertEquals('John', $model->value->user->name);
    }

    public function test_cast_to_object_from_array(): void
    {
        TestCastableModel::$testCasts = ['value' => 'object'];
        // When the value is already an array (e.g., from a JSON column that was pre-decoded)
        $model = TestCastableModel::fromRow(['value' => ['name' => 'John', 'age' => 30]]);

        $this->assertInstanceOf(\stdClass::class, $model->value);
        $this->assertEquals('John', $model->value->name);
    }

    // =========================================================================
    // IMMUTABLE DATE CAST
    // =========================================================================

    public function test_cast_to_immutable_date(): void
    {
        TestCastableModel::$testCasts = ['value' => 'immutable_date'];
        $model = TestCastableModel::fromRow(['value' => '2024-06-15']);

        $this->assertInstanceOf(CarbonImmutable::class, $model->value);
        $this->assertEquals('2024-06-15', $model->value->format('Y-m-d'));
        // Date cast sets time to start of day
        $this->assertEquals('00:00:00', $model->value->format('H:i:s'));
    }

    public function test_cast_to_immutable_date_from_datetime(): void
    {
        TestCastableModel::$testCasts = ['value' => 'immutable_date'];
        $model = TestCastableModel::fromRow(['value' => '2024-06-15 14:30:00']);

        $this->assertInstanceOf(CarbonImmutable::class, $model->value);
        $this->assertEquals('2024-06-15', $model->value->format('Y-m-d'));
        // Time should be stripped
        $this->assertEquals('00:00:00', $model->value->format('H:i:s'));
    }

    // =========================================================================
    // DECIMAL CAST
    // =========================================================================

    public function test_cast_to_decimal(): void
    {
        TestCastableModel::$testCasts = ['value' => 'decimal:2'];
        $model = TestCastableModel::fromRow(['value' => '123.456']);

        $this->assertEquals('123.46', $model->value);
    }

    public function test_cast_to_decimal_with_different_precision(): void
    {
        TestCastableModel::$testCasts = ['value' => 'decimal:4'];
        $model = TestCastableModel::fromRow(['value' => '123.456789']);

        $this->assertEquals('123.4568', $model->value);
    }

    // =========================================================================
    // CUSTOM CASTER
    // =========================================================================

    public function test_custom_caster(): void
    {
        // Using the TestUppercaseCaster class defined below
        TestCastableModel::$testCasts = ['value' => TestUppercaseCaster::class];
        $model = TestCastableModel::fromRow(['value' => 'hello']);

        $this->assertEquals('HELLO', $model->value);
    }

    public function test_invalid_custom_caster_throws(): void
    {
        TestCastableModel::$testCasts = ['value' => 'NonExistentCasterClass'];
        $model = TestCastableModel::fromRow(['value' => 'test']);

        $this->expectException(ImmutableModelConfigurationException::class);
        $this->expectExceptionMessage('Cast class [NonExistentCasterClass] does not exist');

        $model->value;
    }

    // =========================================================================
    // LARAVEL 11 METHOD-BASED CASTS
    // =========================================================================

    public function test_method_based_casts_are_applied(): void
    {
        // MethodCastModel uses the casts() method instead of $casts property
        $model = \Brighten\ImmutableModel\Tests\Models\MethodCastModel::fromRow([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'settings' => json_encode(['key' => 'value']),
            'email_verified_at' => '2024-01-15 10:30:00',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        // settings should be cast to array
        $this->assertIsArray($model->settings);
        $this->assertEquals(['key' => 'value'], $model->settings);

        // email_verified_at should be cast to Carbon
        $this->assertInstanceOf(Carbon::class, $model->email_verified_at);
        $this->assertEquals('2024-01-15', $model->email_verified_at->format('Y-m-d'));
    }

    public function test_method_based_casts_work_with_database_query(): void
    {
        // This is the CRITICAL test - ensures the full query path works with method-based casts
        // This matches the user's scenario: querying a database with method-based casts
        \Brighten\ImmutableModel\Tests\Models\MethodCastModel::setConnectionResolver($this->app['db']);

        $model = \Brighten\ImmutableModel\Tests\Models\MethodCastModel::find(1);

        // Verify the model was found and attributes are NOT null
        $this->assertNotNull($model, 'Model should be found');
        $this->assertNotNull($model->id, 'ID should not be null');
        $this->assertNotNull($model->name, 'Name should not be null');
        $this->assertNotNull($model->email, 'Email should not be null');

        // Verify actual values
        $this->assertEquals(1, $model->id);
        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@example.com', $model->email);

        // Verify method-based casts are applied
        $this->assertIsArray($model->settings);
        $this->assertEquals(['theme' => 'dark', 'notifications' => true], $model->settings);
        $this->assertInstanceOf(Carbon::class, $model->email_verified_at);
    }

    public function test_method_based_casts_work_with_get_all(): void
    {
        // Test that getting multiple records works with method-based casts
        \Brighten\ImmutableModel\Tests\Models\MethodCastModel::setConnectionResolver($this->app['db']);

        $models = \Brighten\ImmutableModel\Tests\Models\MethodCastModel::all();

        $this->assertCount(1, $models);
        $model = $models->first();

        // Verify attributes are NOT null
        $this->assertNotNull($model->id);
        $this->assertNotNull($model->name);
        $this->assertEquals('John', $model->name);
    }

    public function test_method_based_casts_merged_with_property_casts(): void
    {
        // Get the casts from MethodCastModel
        $model = new \Brighten\ImmutableModel\Tests\Models\MethodCastModel();
        $casts = $model->getCasts();

        // Should have the casts defined in the casts() method
        $this->assertArrayHasKey('settings', $casts);
        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertEquals('array', $casts['settings']);
        $this->assertEquals('datetime', $casts['email_verified_at']);
    }

    // =========================================================================
    // NULL HANDLING
    // =========================================================================

    public function test_null_values_are_preserved(): void
    {
        TestCastableModel::$testCasts = ['value' => 'array'];
        $model = TestCastableModel::fromRow(['value' => null]);

        $this->assertNull($model->value);
    }
}

/**
 * Test caster for custom caster tests.
 */
class TestUppercaseCaster implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): string
    {
        return strtoupper($value);
    }

    public function set($model, string $key, mixed $value, array $attributes): mixed
    {
        throw new \RuntimeException('set() should not be called');
    }
}
