<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;

/**
 * Tests that ImmutableModel serialization matches Eloquent exactly.
 */
class SerializationParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // TO ARRAY
    // =========================================================================

    public function test_to_array(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_to_array_keys(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            array_keys($eloquent->toArray()),
            array_keys($immutable->toArray())
        );
    }

    public function test_to_array_with_relation(): void
    {
        $eloquent = EloquentUser::with('profile')->find(1);
        $immutable = ImmutableUser::with('profile')->find(1);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_to_array_with_has_many_relation(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_to_array_with_nested_relations(): void
    {
        $eloquent = EloquentUser::with('posts.comments')->find(1);
        $immutable = ImmutableUser::with('posts.comments')->find(1);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    // =========================================================================
    // TO JSON
    // =========================================================================

    public function test_to_json(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            json_decode($eloquent->toJson(), true),
            json_decode($immutable->toJson(), true)
        );
    }

    public function test_to_json_with_relations(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $this->assertEquals(
            json_decode($eloquent->toJson(), true),
            json_decode($immutable->toJson(), true)
        );
    }

    // =========================================================================
    // JSON SERIALIZE
    // =========================================================================

    public function test_json_encode(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            json_decode(json_encode($eloquent), true),
            json_decode(json_encode($immutable), true)
        );
    }

    public function test_json_serialize(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            $eloquent->jsonSerialize(),
            $immutable->jsonSerialize()
        );
    }

    // =========================================================================
    // APPENDS
    // =========================================================================

    public function test_appends_in_array(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        // display_name is in appends for ImmutableUser
        $this->assertArrayHasKey('display_name', $eloquentArray);
        $this->assertArrayHasKey('display_name', $immutableArray);

        $this->assertEquals($eloquentArray['display_name'], $immutableArray['display_name']);
    }

    public function test_accessor_value_in_serialization(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // display_name accessor returns uppercase name
        $this->assertEquals('ALICE', $eloquent->toArray()['display_name']);
        $this->assertEquals('ALICE', $immutable->toArray()['display_name']);
    }

    // =========================================================================
    // TYPE PRESERVATION
    // =========================================================================

    public function test_integer_preserved(): void
    {
        $eloquent = EloquentUser::find(1)->toArray();
        $immutable = ImmutableUser::find(1)->toArray();

        $this->assertIsInt($eloquent['id']);
        $this->assertIsInt($immutable['id']);
    }

    public function test_boolean_preserved(): void
    {
        $eloquent = EloquentPost::find(1)->toArray();
        $immutable = ImmutablePost::find(1)->toArray();

        $this->assertIsBool($eloquent['published']);
        $this->assertIsBool($immutable['published']);
    }

    public function test_null_preserved(): void
    {
        $eloquent = EloquentUser::find(2)->toArray();
        $immutable = ImmutableUser::find(2)->toArray();

        $this->assertNull($eloquent['settings']);
        $this->assertNull($immutable['settings']);
    }

    public function test_array_preserved(): void
    {
        $eloquent = EloquentUser::find(1)->toArray();
        $immutable = ImmutableUser::find(1)->toArray();

        $this->assertIsArray($eloquent['settings']);
        $this->assertIsArray($immutable['settings']);
    }

    // =========================================================================
    // DATETIME SERIALIZATION
    // =========================================================================

    public function test_datetime_format(): void
    {
        $eloquent = EloquentUser::find(1)->toArray();
        $immutable = ImmutableUser::find(1)->toArray();

        // Both should serialize datetime the same way
        $this->assertEquals($eloquent['created_at'], $immutable['created_at']);
        $this->assertEquals($eloquent['updated_at'], $immutable['updated_at']);
    }

    // =========================================================================
    // COLLECTION SERIALIZATION
    // =========================================================================

    public function test_collection_to_array(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->toArray();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->toArray();

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_collection_to_json(): void
    {
        $eloquent = json_decode(EloquentUser::orderBy('id')->get()->toJson(), true);
        $immutable = json_decode(ImmutableUser::query()->orderBy('id')->get()->toJson(), true);

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_empty_collection_serialization(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->get()->toArray();
        $immutable = ImmutableUser::where('id', 9999)->get()->toArray();

        $this->assertEquals([], $eloquent);
        $this->assertEquals([], $immutable);
    }

    // =========================================================================
    // RELATION SERIALIZATION
    // =========================================================================

    public function test_null_relation_serialization(): void
    {
        // User 2 has no profile
        $eloquent = EloquentUser::with('profile')->find(2)->toArray();
        $immutable = ImmutableUser::with('profile')->find(2)->toArray();

        $this->assertArrayHasKey('profile', $eloquent);
        $this->assertArrayHasKey('profile', $immutable);

        $this->assertNull($eloquent['profile']);
        $this->assertNull($immutable['profile']);
    }

    public function test_empty_has_many_serialization(): void
    {
        // User 3 has no posts
        $eloquent = EloquentUser::with('posts')->find(3)->toArray();
        $immutable = ImmutableUser::with('posts')->find(3)->toArray();

        $this->assertArrayHasKey('posts', $eloquent);
        $this->assertArrayHasKey('posts', $immutable);

        $this->assertEquals([], $eloquent['posts']);
        $this->assertEquals([], $immutable['posts']);
    }
}
