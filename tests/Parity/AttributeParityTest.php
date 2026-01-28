<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;

/**
 * Tests that ImmutableModel attribute access matches Eloquent exactly.
 */
class AttributeParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // PROPERTY ACCESS
    // =========================================================================

    public function test_property_access(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->name, $immutable->name);
        $this->assertEquals($eloquent->email, $immutable->email);
        $this->assertEquals($eloquent->id, $immutable->id);
    }

    public function test_property_access_cast_value(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->settings, $immutable->settings);
    }

    // =========================================================================
    // ARRAY ACCESS
    // =========================================================================

    public function test_array_access_get(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent['name'], $immutable['name']);
        $this->assertEquals($eloquent['email'], $immutable['email']);
        $this->assertEquals($eloquent['id'], $immutable['id']);
    }

    public function test_array_access_isset(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(isset($eloquent['name']), isset($immutable['name']));
        $this->assertEquals(isset($eloquent['nonexistent']), isset($immutable['nonexistent']));
    }

    // =========================================================================
    // GET ATTRIBUTE METHOD
    // =========================================================================

    public function test_get_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            $eloquent->getAttribute('name'),
            $immutable->getAttribute('name')
        );
    }

    public function test_get_attribute_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            $eloquent->getAttribute('settings'),
            $immutable->getAttribute('settings')
        );
    }

    public function test_get_attribute_null(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        $this->assertNull($eloquent->getAttribute('settings'));
        $this->assertNull($immutable->getAttribute('settings'));
    }

    // =========================================================================
    // GET ATTRIBUTES (RAW)
    // =========================================================================

    public function test_get_attributes(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentAttrs = $eloquent->getAttributes();
        $immutableAttrs = $immutable->getAttributes();

        // Sort keys for comparison
        ksort($eloquentAttrs);
        ksort($immutableAttrs);

        $this->assertEquals(array_keys($eloquentAttrs), array_keys($immutableAttrs));
    }

    public function test_get_attributes_raw_values(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Raw settings should be JSON string
        $eloquentRaw = $eloquent->getAttributes()['settings'];
        $immutableRaw = $immutable->getAttributes()['settings'];

        $this->assertIsString($eloquentRaw);
        $this->assertIsString($immutableRaw);
        $this->assertEquals($eloquentRaw, $immutableRaw);
    }

    // =========================================================================
    // ISSET
    // =========================================================================

    public function test_isset_existing(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertTrue(isset($eloquent->name));
        $this->assertTrue(isset($immutable->name));
    }

    public function test_isset_missing(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertFalse(isset($eloquent->nonexistent_attribute));
        $this->assertFalse(isset($immutable->nonexistent_attribute));
    }

    public function test_isset_null_value(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        // settings is null for user 2
        // isset returns false for null values
        $this->assertFalse(isset($eloquent->settings));
        $this->assertFalse(isset($immutable->settings));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function test_accessor(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // display_name accessor
        $this->assertEquals($eloquent->display_name, $immutable->display_name);
        $this->assertEquals('ALICE', $eloquent->display_name);
        $this->assertEquals('ALICE', $immutable->display_name);
    }

    public function test_accessor_different_values(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        $this->assertEquals($eloquent->display_name, $immutable->display_name);
        $this->assertEquals('BOB', $eloquent->display_name);
        $this->assertEquals('BOB', $immutable->display_name);
    }

    // =========================================================================
    // MODEL METADATA
    // =========================================================================

    public function test_get_key(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->getKey(), $immutable->getKey());
        $this->assertEquals(1, $eloquent->getKey());
        $this->assertEquals(1, $immutable->getKey());
    }

    public function test_get_key_name(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getKeyName(), $immutable->getKeyName());
        $this->assertEquals('id', $eloquent->getKeyName());
        $this->assertEquals('id', $immutable->getKeyName());
    }

    public function test_get_key_type(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getKeyType(), $immutable->getKeyType());
        $this->assertEquals('int', $eloquent->getKeyType());
        $this->assertEquals('int', $immutable->getKeyType());
    }

    public function test_get_table(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getTable(), $immutable->getTable());
        $this->assertEquals('users', $eloquent->getTable());
        $this->assertEquals('users', $immutable->getTable());
    }

    // =========================================================================
    // MISSING ATTRIBUTES
    // =========================================================================

    public function test_missing_attribute_returns_null(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertNull($eloquent->nonexistent);
        $this->assertNull($immutable->nonexistent);
    }

    public function test_missing_attribute_via_get_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertNull($eloquent->getAttribute('nonexistent'));
        $this->assertNull($immutable->getAttribute('nonexistent'));
    }

    // =========================================================================
    // SELECT SPECIFIC COLUMNS
    // =========================================================================

    public function test_partial_select_available_columns(): void
    {
        $eloquent = EloquentUser::select(['id', 'name'])->first();
        $immutable = ImmutableUser::query()->select(['id', 'name'])->first();

        $this->assertEquals($eloquent->id, $immutable->id);
        $this->assertEquals($eloquent->name, $immutable->name);
    }

    public function test_partial_select_missing_columns(): void
    {
        $eloquent = EloquentUser::select(['id'])->first();
        $immutable = ImmutableUser::query()->select(['id'])->first();

        // Accessing non-selected column should return null
        $this->assertNull($eloquent->name);
        $this->assertNull($immutable->name);
    }
}
