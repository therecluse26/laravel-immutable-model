<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Relations\ImmutablePivot;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Tests for pivot constraints on BelongsToMany and MorphToMany relations.
 *
 * Tests that pivot data can be accessed, filtered, and ordered correctly.
 */
class PivotConstraintsTest extends TestCase
{
    private ImmutablePost $post;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a post
        DB::table('posts')->insert([
            'id' => 1,
            'user_id' => 1,
            'title' => 'Test Post',
            'body' => 'Test body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create tags
        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Testing', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create pivot entries with different order values
        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 3, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create taggables for MorphToMany
        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tag_id' => 2, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->post = ImmutablePost::find(1);
    }

    // =========================================================================
    // Pivot Data Access Tests
    // =========================================================================

    public function test_pivot_data_is_accessible_on_loaded_relation(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstTag->pivot);
        $this->assertNotNull($firstTag->pivot->order);
    }

    public function test_pivot_data_has_foreign_keys(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertEquals(1, $firstTag->pivot->post_id);
        $this->assertNotNull($firstTag->pivot->tag_id);
    }

    public function test_pivot_data_has_timestamps(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertNotNull($firstTag->pivot->created_at);
        $this->assertNotNull($firstTag->pivot->updated_at);
    }

    public function test_pivot_custom_column_is_accessible(): void
    {
        $tags = $this->post->tags;

        // Find the tag with order = 1 (Laravel)
        $laravelTag = $tags->firstWhere('name', 'Laravel');

        $this->assertEquals(1, $laravelTag->pivot->order);
    }

    public function test_all_pivot_columns_are_present(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();
        $attributes = $firstTag->pivot->getAttributes();

        $this->assertArrayHasKey('post_id', $attributes);
        $this->assertArrayHasKey('tag_id', $attributes);
        $this->assertArrayHasKey('order', $attributes);
        $this->assertArrayHasKey('created_at', $attributes);
        $this->assertArrayHasKey('updated_at', $attributes);
    }

    // =========================================================================
    // Pivot Access on Eager Loading
    // =========================================================================

    public function test_pivot_data_available_on_eager_loaded_relation(): void
    {
        $posts = ImmutablePost::with('tags')->get();
        $post = $posts->first();
        $tag = $post->tags->first();

        $this->assertInstanceOf(ImmutablePivot::class, $tag->pivot);
        $this->assertNotNull($tag->pivot->order);
    }

    public function test_pivot_data_with_constraints_on_eager_load(): void
    {
        $posts = ImmutablePost::with(['tags' => function ($query) {
            $query->where('name', 'PHP');
        }])->get();

        $post = $posts->first();
        $this->assertCount(1, $post->tags);
        $this->assertInstanceOf(ImmutablePivot::class, $post->tags->first()->pivot);
    }

    // =========================================================================
    // Pivot on Inverse Relation
    // =========================================================================

    public function test_pivot_data_on_inverse_belongs_to_many(): void
    {
        $tag = ImmutableTag::find(1);
        $posts = $tag->posts;

        $this->assertCount(1, $posts);
        $firstPost = $posts->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstPost->pivot);
        $this->assertEquals(1, $firstPost->pivot->post_id);
        $this->assertEquals(1, $firstPost->pivot->tag_id);
    }

    // =========================================================================
    // MorphToMany Pivot Tests
    // =========================================================================

    public function test_morph_to_many_pivot_data_accessible(): void
    {
        $tags = $this->post->morphTags;

        $this->assertCount(2, $tags);
        $firstTag = $tags->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstTag->pivot);
    }

    public function test_morph_to_many_pivot_has_morph_type(): void
    {
        $tags = $this->post->morphTags;
        $firstTag = $tags->first();

        $this->assertEquals(ImmutablePost::class, $firstTag->pivot->taggable_type);
        $this->assertEquals(1, $firstTag->pivot->taggable_id);
    }

    public function test_morphed_by_many_pivot_data_accessible(): void
    {
        $tag = ImmutableTag::find(1);
        $posts = $tag->taggablePosts;

        $this->assertCount(1, $posts);
        $firstPost = $posts->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstPost->pivot);
        $this->assertEquals(ImmutablePost::class, $firstPost->pivot->taggable_type);
    }

    // =========================================================================
    // Pivot Table Name Tests
    // =========================================================================

    public function test_pivot_has_correct_table_name(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertEquals('post_tag', $firstTag->pivot->getTable());
    }

    public function test_morph_pivot_has_correct_table_name(): void
    {
        $tags = $this->post->morphTags;
        $firstTag = $tags->first();

        $this->assertEquals('taggables', $firstTag->pivot->getTable());
    }

    // =========================================================================
    // Pivot ArrayAccess Tests
    // =========================================================================

    public function test_pivot_array_access_read(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertEquals(1, $firstTag->pivot['post_id']);
        $this->assertNotNull($firstTag->pivot['order']);
    }

    public function test_pivot_isset_works(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertTrue(isset($firstTag->pivot->order));
        $this->assertFalse(isset($firstTag->pivot->nonexistent));
    }

    public function test_pivot_to_array(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $array = $firstTag->pivot->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('post_id', $array);
        $this->assertArrayHasKey('tag_id', $array);
        $this->assertArrayHasKey('order', $array);
    }

    public function test_pivot_json_serialize(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $json = json_encode($firstTag->pivot);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('post_id', $decoded);
    }
}
