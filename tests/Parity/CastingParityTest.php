<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentProfile;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableProfile;
use Carbon\Carbon;

/**
 * Tests that ImmutableModel casting matches Eloquent casting behavior exactly.
 */
class CastingParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCastingTestData();
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
}
