<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;

/**
 * Mechanical Parity Audit Tests
 *
 * These tests systematically verify that ImmutableModel behavior matches
 * Eloquent exactly for all read operations, including edge cases.
 *
 * Based on MECHANICAL_PARITY_AUDIT.md Phase 1.1: Attribute Access
 */
class MechanicalParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // 1.1 getAttribute() EDGE CASES
    // =========================================================================

    /**
     * Test getAttribute with empty string key.
     * Eloquent returns null for falsy keys.
     */
    public function test_get_attribute_with_empty_string_key(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertNull($eloquent->getAttribute(''));
        $this->assertNull($immutable->getAttribute(''));
    }

    /**
     * Test getAttribute for existing attribute.
     */
    public function test_get_attribute_existing_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals(
            $eloquent->getAttribute('name'),
            $immutable->getAttribute('name')
        );
    }

    /**
     * Test getAttribute for missing (non-existent) attribute.
     */
    public function test_get_attribute_missing_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Both should return null for non-existent attributes
        $this->assertNull($eloquent->getAttribute('nonexistent_attribute'));
        $this->assertNull($immutable->getAttribute('nonexistent_attribute'));
    }

    /**
     * Test getAttribute for cast attribute.
     */
    public function test_get_attribute_cast_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // 'settings' is cast to 'array'
        $this->assertEquals(
            $eloquent->getAttribute('settings'),
            $immutable->getAttribute('settings')
        );
        $this->assertIsArray($eloquent->getAttribute('settings'));
        $this->assertIsArray($immutable->getAttribute('settings'));
    }

    /**
     * Test getAttribute for accessor attribute.
     */
    public function test_get_attribute_accessor_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // 'display_name' is defined via getDisplayNameAttribute accessor
        $this->assertEquals(
            $eloquent->getAttribute('display_name'),
            $immutable->getAttribute('display_name')
        );
        $this->assertEquals('ALICE', $eloquent->getAttribute('display_name'));
        $this->assertEquals('ALICE', $immutable->getAttribute('display_name'));
    }

    /**
     * Test getAttribute for null cast value.
     */
    public function test_get_attribute_null_cast_value(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        // User 2 has null settings
        $this->assertNull($eloquent->getAttribute('settings'));
        $this->assertNull($immutable->getAttribute('settings'));
    }

    /**
     * Test getAttribute for relation attribute (not loaded).
     * This tests lazy loading behavior.
     */
    public function test_get_attribute_relation_not_loaded(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // 'posts' is a relation - should lazy load
        $eloquentPosts = $eloquent->getAttribute('posts');
        $immutablePosts = $immutable->getAttribute('posts');

        $this->assertEquals($eloquentPosts->count(), $immutablePosts->count());
        $this->assertEquals(2, $eloquentPosts->count()); // Alice has 2 posts
        $this->assertEquals(2, $immutablePosts->count());
    }

    /**
     * Test getAttribute for relation attribute (already loaded).
     */
    public function test_get_attribute_relation_loaded(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        // Relation is already loaded via with()
        $eloquentPosts = $eloquent->getAttribute('posts');
        $immutablePosts = $immutable->getAttribute('posts');

        $this->assertEquals($eloquentPosts->count(), $immutablePosts->count());
    }

    /**
     * Test getAttribute for datetime cast attribute.
     */
    public function test_get_attribute_datetime_cast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentDate = $eloquent->getAttribute('created_at');
        $immutableDate = $immutable->getAttribute('created_at');

        // Both should be Carbon instances
        $this->assertInstanceOf(\Carbon\Carbon::class, $eloquentDate);
        $this->assertInstanceOf(\Carbon\Carbon::class, $immutableDate);

        // Timestamps should match
        $this->assertEquals(
            $eloquentDate->timestamp,
            $immutableDate->timestamp
        );
    }

    // =========================================================================
    // 1.1 __get() EDGE CASES
    // =========================================================================

    /**
     * Test magic __get for existing attribute.
     */
    public function test_magic_get_existing_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->name, $immutable->name);
        $this->assertEquals('Alice', $eloquent->name);
    }

    /**
     * Test magic __get for accessor.
     */
    public function test_magic_get_accessor(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->display_name, $immutable->display_name);
    }

    /**
     * Test magic __get for missing attribute returns null.
     */
    public function test_magic_get_missing_attribute_returns_null(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertNull($eloquent->nonexistent_field);
        $this->assertNull($immutable->nonexistent_field);
    }

    // =========================================================================
    // 1.1 getRawAttribute() / getAttributes()
    // =========================================================================

    /**
     * Test getRawAttribute returns uncast value.
     */
    public function test_get_raw_attribute_returns_uncast_value(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Raw settings should be JSON string, not array
        $eloquentRaw = $eloquent->getAttributes()['settings'];
        $immutableRaw = $immutable->getRawAttribute('settings');

        $this->assertIsString($eloquentRaw);
        $this->assertIsString($immutableRaw);
        $this->assertEquals($eloquentRaw, $immutableRaw);
    }

    /**
     * Test getAttributes returns all raw attributes.
     */
    public function test_get_attributes_returns_all_raw(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentAttrs = $eloquent->getAttributes();
        $immutableAttrs = $immutable->getAttributes();

        // Same keys
        ksort($eloquentAttrs);
        ksort($immutableAttrs);
        $this->assertEquals(array_keys($eloquentAttrs), array_keys($immutableAttrs));

        // Same raw values
        $this->assertEquals($eloquentAttrs['name'], $immutableAttrs['name']);
        $this->assertEquals($eloquentAttrs['settings'], $immutableAttrs['settings']);
    }

    // =========================================================================
    // 1.1 getOriginal() / getRawOriginal()
    // =========================================================================

    /**
     * Test getOriginal returns values.
     * Note: For ImmutableModel, original equals current (no dirty tracking).
     */
    public function test_get_original_returns_values(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // getOriginal() with key should return cast value
        $this->assertEquals(
            $eloquent->getOriginal('name'),
            $immutable->getOriginal('name')
        );
    }

    /**
     * Test getOriginal with null key returns all.
     */
    public function test_get_original_with_null_key_returns_all(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentOriginal = $eloquent->getOriginal();
        $immutableOriginal = $immutable->getOriginal();

        // Both should have the same keys
        $this->assertEquals(
            array_keys($eloquentOriginal),
            array_keys($immutableOriginal)
        );
    }

    /**
     * Test getRawOriginal returns uncast values.
     */
    public function test_get_raw_original_returns_uncast(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentRaw = $eloquent->getRawOriginal('settings');
        $immutableRaw = $immutable->getRawOriginal('settings');

        $this->assertIsString($eloquentRaw);
        $this->assertIsString($immutableRaw);
        $this->assertEquals($eloquentRaw, $immutableRaw);
    }

    // =========================================================================
    // 1.1 __isset() EDGE CASES
    // =========================================================================

    /**
     * Test __isset for existing attribute.
     */
    public function test_isset_existing_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertTrue(isset($eloquent->name));
        $this->assertTrue(isset($immutable->name));
    }

    /**
     * Test __isset for null attribute value.
     */
    public function test_isset_null_attribute(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        // settings is null for user 2
        // isset() returns false for null values
        $this->assertFalse(isset($eloquent->settings));
        $this->assertFalse(isset($immutable->settings));
    }

    /**
     * Test __isset for missing attribute.
     */
    public function test_isset_missing_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertFalse(isset($eloquent->nonexistent_field));
        $this->assertFalse(isset($immutable->nonexistent_field));
    }

    /**
     * Test __isset for accessor attribute.
     */
    public function test_isset_accessor_attribute(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertTrue(isset($eloquent->display_name));
        $this->assertTrue(isset($immutable->display_name));
    }

    /**
     * Test __isset for loaded relation.
     */
    public function test_isset_loaded_relation(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $this->assertTrue(isset($eloquent->posts));
        $this->assertTrue(isset($immutable->posts));
    }

    // =========================================================================
    // ACCESSOR INVOCATION
    // =========================================================================

    /**
     * Test accessor receives raw attribute value.
     */
    public function test_accessor_receives_raw_value(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // display_name accessor uses $this->name internally
        // Verify both return same result
        $this->assertEquals($eloquent->display_name, $immutable->display_name);
    }

    /**
     * Test accessor for different users returns different values.
     */
    public function test_accessor_different_values(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        $this->assertEquals($eloquent->display_name, $immutable->display_name);
        $this->assertEquals('BOB', $immutable->display_name);
    }

    // =========================================================================
    // 1.2 CASTING INTEGRATION
    // =========================================================================

    /**
     * Test hasCast for attribute with cast.
     */
    public function test_has_cast_with_cast(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertTrue($eloquent->hasCast('settings'));
        $this->assertTrue($immutable->hasCast('settings'));
    }

    /**
     * Test hasCast for attribute without cast.
     */
    public function test_has_cast_without_cast(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertFalse($eloquent->hasCast('name'));
        $this->assertFalse($immutable->hasCast('name'));
    }

    /**
     * Test hasCast with types parameter.
     */
    public function test_has_cast_with_types(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        // settings is cast as 'array'
        $this->assertTrue($eloquent->hasCast('settings', 'array'));
        $this->assertTrue($immutable->hasCast('settings', 'array'));

        $this->assertFalse($eloquent->hasCast('settings', 'string'));
        $this->assertFalse($immutable->hasCast('settings', 'string'));
    }

    /**
     * Test getCasts returns all casts.
     *
     * Note: Eloquent adds 'id' to casts when $incrementing=true.
     * ImmutableModel doesn't have incrementing since it's read-only,
     * so we compare the explicitly defined casts only.
     */
    public function test_get_casts_returns_all(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $eloquentCasts = $eloquent->getCasts();
        $immutableCasts = $immutable->getCasts();

        // Remove 'id' from Eloquent casts (added by incrementing logic)
        unset($eloquentCasts['id']);

        // Should have same cast definitions (minus auto-id)
        ksort($eloquentCasts);
        ksort($immutableCasts);

        $this->assertEquals(array_keys($eloquentCasts), array_keys($immutableCasts));
    }

    /**
     * Test getDates returns timestamp columns.
     *
     * Eloquent's getDates() returns timestamp columns (created_at, updated_at)
     * when timestamps are enabled, NOT all datetime-cast attributes.
     */
    public function test_get_dates_returns_timestamp_columns(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $eloquentDates = $eloquent->getDates();
        $immutableDates = $immutable->getDates();

        // Both should return timestamp columns
        sort($eloquentDates);
        sort($immutableDates);

        $this->assertEquals($eloquentDates, $immutableDates);
        $this->assertContains('created_at', $immutableDates);
        $this->assertContains('updated_at', $immutableDates);
    }

    // =========================================================================
    // 1.3 KEYS & TABLES
    // =========================================================================

    /**
     * Test getTable returns table name.
     */
    public function test_get_table(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getTable(), $immutable->getTable());
        $this->assertEquals('users', $immutable->getTable());
    }

    /**
     * Test getKeyName returns primary key name.
     */
    public function test_get_key_name(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getKeyName(), $immutable->getKeyName());
        $this->assertEquals('id', $immutable->getKeyName());
    }

    /**
     * Test getKey returns primary key value.
     */
    public function test_get_key(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent->getKey(), $immutable->getKey());
        $this->assertEquals(1, $immutable->getKey());
    }

    /**
     * Test getKeyType returns key type.
     */
    public function test_get_key_type(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals($eloquent->getKeyType(), $immutable->getKeyType());
        $this->assertEquals('int', $immutable->getKeyType());
    }

    /**
     * Test getQualifiedKeyName returns table.key format.
     */
    public function test_get_qualified_key_name(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals(
            $eloquent->getQualifiedKeyName(),
            $immutable->getQualifiedKeyName()
        );
        $this->assertEquals('users.id', $immutable->getQualifiedKeyName());
    }

    /**
     * Test getForeignKey returns foreign key name.
     *
     * ImmutableModel intentionally strips the "Immutable" prefix so that
     * ImmutableUser produces 'user_id' (same as a hypothetical User model),
     * while EloquentUser produces 'eloquent_user_id' based on its class name.
     * This is by design so ImmutableModel works with existing database schemas.
     */
    public function test_get_foreign_key(): void
    {
        $immutable = new ImmutableUser();

        // ImmutableUser should produce 'user_id' (Immutable prefix stripped)
        $this->assertEquals('user_id', $immutable->getForeignKey());
    }

    /**
     * Test getForeignKey algorithm produces expected snake_case format.
     */
    public function test_get_foreign_key_format(): void
    {
        $eloquent = new EloquentUser();

        // Eloquent's algorithm: snake_case(className) + '_id'
        // EloquentUser → eloquent_user_id
        $this->assertEquals('eloquent_user_id', $eloquent->getForeignKey());
    }

    /**
     * Test qualifyColumn adds table prefix.
     */
    public function test_qualify_column(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals(
            $eloquent->qualifyColumn('name'),
            $immutable->qualifyColumn('name')
        );
        $this->assertEquals('users.name', $immutable->qualifyColumn('name'));
    }

    /**
     * Test qualifyColumn doesn't double-qualify.
     */
    public function test_qualify_column_already_qualified(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertEquals(
            $eloquent->qualifyColumn('users.name'),
            $immutable->qualifyColumn('users.name')
        );
        $this->assertEquals('users.name', $immutable->qualifyColumn('users.name'));
    }

    // =========================================================================
    // 1.6 RELATION ACCESS
    // =========================================================================

    /**
     * Test relationLoaded returns false for unloaded relation.
     */
    public function test_relation_loaded_false(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertFalse($eloquent->relationLoaded('posts'));
        $this->assertFalse($immutable->relationLoaded('posts'));
    }

    /**
     * Test relationLoaded returns true for loaded relation.
     */
    public function test_relation_loaded_true(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $this->assertTrue($eloquent->relationLoaded('posts'));
        $this->assertTrue($immutable->relationLoaded('posts'));
    }

    /**
     * Test getRelation returns loaded relation.
     */
    public function test_get_relation_loaded(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $this->assertNotNull($eloquent->getRelation('posts'));
        $this->assertNotNull($immutable->getRelation('posts'));
    }

    /**
     * Test getRelation throws for unloaded relation (matches Eloquent).
     */
    public function test_get_relation_unloaded_throws(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Both should throw when accessing unloaded relation
        $eloquentException = null;
        try {
            $eloquent->getRelation('comments');
        } catch (\Throwable $e) {
            $eloquentException = $e;
        }

        $immutableException = null;
        try {
            $immutable->getRelation('comments');
        } catch (\Throwable $e) {
            $immutableException = $e;
        }

        $this->assertNotNull($eloquentException, 'Eloquent should throw for unloaded relation');
        $this->assertNotNull($immutableException, 'ImmutableModel should throw for unloaded relation');
    }

    /**
     * Test getRelations returns all loaded relations.
     */
    public function test_get_relations(): void
    {
        $eloquent = EloquentUser::with(['posts', 'profile'])->find(1);
        $immutable = ImmutableUser::with(['posts', 'profile'])->find(1);

        $eloquentRelations = $eloquent->getRelations();
        $immutableRelations = $immutable->getRelations();

        $this->assertEquals(
            array_keys($eloquentRelations),
            array_keys($immutableRelations)
        );
    }

    // =========================================================================
    // 1.7 SCOPES
    // =========================================================================

    /**
     * Test hasNamedScope detects existing scope.
     */
    public function test_has_named_scope_exists(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertTrue($eloquent->hasNamedScope('verified'));
        $this->assertTrue($immutable->hasNamedScope('verified'));
    }

    /**
     * Test hasNamedScope returns false for missing scope.
     */
    public function test_has_named_scope_missing(): void
    {
        $eloquent = new EloquentUser();
        $immutable = new ImmutableUser();

        $this->assertFalse($eloquent->hasNamedScope('nonexistent'));
        $this->assertFalse($immutable->hasNamedScope('nonexistent'));
    }

    // =========================================================================
    // 1.9 ArrayAccess
    // =========================================================================

    /**
     * Test ArrayAccess offsetExists.
     */
    public function test_array_access_offset_exists(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertTrue(isset($eloquent['name']));
        $this->assertTrue(isset($immutable['name']));

        $this->assertFalse(isset($eloquent['nonexistent']));
        $this->assertFalse(isset($immutable['nonexistent']));
    }

    /**
     * Test ArrayAccess offsetGet.
     */
    public function test_array_access_offset_get(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent['name'], $immutable['name']);
        $this->assertEquals('Alice', $immutable['name']);
    }

    /**
     * Test ArrayAccess offsetGet with accessor.
     */
    public function test_array_access_offset_get_accessor(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertEquals($eloquent['display_name'], $immutable['display_name']);
        $this->assertEquals('ALICE', $immutable['display_name']);
    }

    // =========================================================================
    // 1.4 CONNECTION METHODS
    // =========================================================================

    /**
     * Test getConnectionName returns connection name.
     */
    public function test_get_connection_name(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Both should return the same connection name (null = default)
        $this->assertEquals(
            $eloquent->getConnectionName(),
            $immutable->getConnectionName()
        );
    }

    /**
     * Test getConnection returns connection instance.
     */
    public function test_get_connection(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Both should return a Connection instance
        $eloquentConn = $eloquent->getConnection();
        $immutableConn = $immutable->getConnection();

        $this->assertInstanceOf(\Illuminate\Database\Connection::class, $eloquentConn);
        $this->assertInstanceOf(\Illuminate\Database\Connection::class, $immutableConn);

        // Should be the same connection
        $this->assertEquals($eloquentConn->getName(), $immutableConn->getName());
    }

    // =========================================================================
    // 1.5 SERIALIZATION
    // =========================================================================

    /**
     * Test toArray returns array representation.
     */
    public function test_to_array(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        ksort($eloquentArray);
        ksort($immutableArray);

        $this->assertEquals($eloquentArray, $immutableArray);
    }

    /**
     * Test toArray includes accessor/appended attributes.
     */
    public function test_to_array_includes_appends(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // display_name is in $appends
        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        $this->assertArrayHasKey('display_name', $eloquentArray);
        $this->assertArrayHasKey('display_name', $immutableArray);
        $this->assertEquals($eloquentArray['display_name'], $immutableArray['display_name']);
    }

    /**
     * Test toArray includes loaded relations.
     */
    public function test_to_array_with_relations(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        $this->assertArrayHasKey('posts', $eloquentArray);
        $this->assertArrayHasKey('posts', $immutableArray);
        $this->assertCount(count($eloquentArray['posts']), $immutableArray['posts']);
    }

    /**
     * Test toArray includes cast attributes.
     */
    public function test_to_array_cast_attributes(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        // settings is cast to array
        $this->assertEquals($eloquentArray['settings'], $immutableArray['settings']);
        $this->assertIsArray($immutableArray['settings']);
    }

    /**
     * Test toJson returns JSON string.
     */
    public function test_to_json(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentJson = $eloquent->toJson();
        $immutableJson = $immutable->toJson();

        // Decode and compare (handles key ordering differences)
        $this->assertEquals(
            json_decode($eloquentJson, true),
            json_decode($immutableJson, true)
        );
    }

    /**
     * Test jsonSerialize returns array for json_encode.
     */
    public function test_json_serialize(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $eloquentSerialized = $eloquent->jsonSerialize();
        $immutableSerialized = $immutable->jsonSerialize();

        $this->assertEquals($eloquentSerialized, $immutableSerialized);
    }

    /**
     * Test attributesToArray returns attributes only (no relations).
     */
    public function test_attributes_to_array(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        // attributesToArray should not include relations
        $eloquentAttrs = $eloquent->attributesToArray();
        $immutableAttrs = $immutable->attributesToArray();

        $this->assertArrayNotHasKey('posts', $eloquentAttrs);
        $this->assertArrayNotHasKey('posts', $immutableAttrs);

        // But should include attributes and appends
        $this->assertArrayHasKey('name', $immutableAttrs);
        $this->assertArrayHasKey('display_name', $immutableAttrs);
    }

    /**
     * Test relationsToArray returns relations only.
     */
    public function test_relations_to_array(): void
    {
        $eloquent = EloquentUser::with('posts')->find(1);
        $immutable = ImmutableUser::with('posts')->find(1);

        $eloquentRelations = $eloquent->relationsToArray();
        $immutableRelations = $immutable->relationsToArray();

        $this->assertArrayHasKey('posts', $eloquentRelations);
        $this->assertArrayHasKey('posts', $immutableRelations);

        // Should not include regular attributes
        $this->assertArrayNotHasKey('name', $eloquentRelations);
        $this->assertArrayNotHasKey('name', $immutableRelations);
    }

    // =========================================================================
    // 1.7-1.9 SCOPES, STATIC QUERY, MAGIC METHODS
    // =========================================================================

    /**
     * Test local scope via static call.
     */
    public function test_scope_via_static_call(): void
    {
        // verified scope should filter by email_verified_at not null
        $eloquent = EloquentUser::verified()->orderBy('id')->get();
        $immutable = ImmutableUser::verified()->orderBy('id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(2, $immutable->count()); // Users 1 and 3 have verified email
    }

    /**
     * Test scope with parameter.
     */
    public function test_scope_with_parameter(): void
    {
        $eloquent = EloquentUser::nameLike('A%')->get();
        $immutable = ImmutableUser::nameLike('A%')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(1, $immutable->count()); // Only Alice
    }

    /**
     * Test static all() method.
     */
    public function test_static_all(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    /**
     * Test static where() method.
     */
    public function test_static_where(): void
    {
        $eloquent = EloquentUser::where('name', 'Alice')->first();
        $immutable = ImmutableUser::where('name', 'Alice')->first();

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    /**
     * Test __set for in-memory mutation.
     */
    public function test_magic_set_in_memory(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Set new attribute in memory
        $eloquent->custom_field = 'test';
        $immutable->custom_field = 'test';

        $this->assertEquals($eloquent->custom_field, $immutable->custom_field);
        $this->assertEquals('test', $immutable->custom_field);
    }

    /**
     * Test __unset removes attribute.
     */
    public function test_magic_unset(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        // Verify attribute exists
        $this->assertTrue(isset($eloquent->name));
        $this->assertTrue(isset($immutable->name));

        // Unset the attribute
        unset($eloquent->name);
        unset($immutable->name);

        // Both should no longer have the attribute
        $this->assertFalse(isset($eloquent->name));
        $this->assertFalse(isset($immutable->name));
    }
}
