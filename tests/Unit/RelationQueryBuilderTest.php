<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableComment;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableImage;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Tests for query builder method forwarding on relation builders.
 *
 * These tests verify that methods like first(), paginate(), pluck()
 * work correctly when called on relation builders via __call() forwarding.
 */
class RelationQueryBuilderTest extends TestCase
{
    private ImmutableUser $user;
    private ImmutablePost $post;
    private ImmutableCountry $country;

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

        // Create posts
        DB::table('posts')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'First Post', 'body' => 'Body 1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'title' => 'Second Post', 'body' => 'Body 2', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 1, 'title' => 'Third Post', 'body' => 'Body 3', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create comments
        DB::table('comments')->insert([
            ['id' => 1, 'post_id' => 1, 'user_id' => 1, 'body' => 'Comment 1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'post_id' => 1, 'user_id' => 1, 'body' => 'Comment 2', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'post_id' => 1, 'user_id' => 1, 'body' => 'Comment 3', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create tags
        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Testing', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create pivot entries
        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 3, 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create images (polymorphic)
        DB::table('images')->insert([
            ['id' => 1, 'imageable_type' => ImmutablePost::class, 'imageable_id' => 1, 'path' => '/img1.jpg', 'is_featured' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'imageable_type' => ImmutablePost::class, 'imageable_id' => 1, 'path' => '/img2.jpg', 'is_featured' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create country and suppliers for HasManyThrough
        DB::table('countries')->insert([
            'id' => 1,
            'name' => 'United States',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Supplier A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'country_id' => 1, 'name' => 'Supplier B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->where('id', 1)->update(['supplier_id' => 1]);

        DB::table('users')->insert([
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com', 'supplier_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'User 3', 'email' => 'user3@example.com', 'supplier_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = ImmutableUser::find(1);
        $this->post = ImmutablePost::find(1);
        $this->country = ImmutableCountry::find(1);
    }

    // =========================================================================
    // HasMany Query Builder Tests
    // =========================================================================

    public function test_has_many_first(): void
    {
        $post = $this->user->posts()->first();

        $this->assertInstanceOf(ImmutablePost::class, $post);
        $this->assertEquals('First Post', $post->title);
    }

    public function test_has_many_first_or_fail(): void
    {
        $post = $this->user->posts()->firstOrFail();

        $this->assertInstanceOf(ImmutablePost::class, $post);
    }

    public function test_has_many_first_or_fail_throws_when_not_found(): void
    {
        // Create a user with no posts
        DB::table('users')->insert([
            'id' => 99,
            'name' => 'No Posts User',
            'email' => 'noposts@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = ImmutableUser::find(99);

        $this->expectException(ModelNotFoundException::class);
        $user->posts()->firstOrFail();
    }

    public function test_has_many_pluck(): void
    {
        $titles = $this->user->posts()->pluck('title');

        $this->assertCount(3, $titles);
        $this->assertContains('First Post', $titles->all());
        $this->assertContains('Second Post', $titles->all());
    }

    public function test_has_many_pluck_with_key(): void
    {
        $titles = $this->user->posts()->pluck('title', 'id');

        $this->assertEquals('First Post', $titles[1]);
        $this->assertEquals('Second Post', $titles[2]);
    }

    public function test_has_many_paginate(): void
    {
        $paginator = $this->user->posts()->paginate(2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(3, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    public function test_has_many_simple_paginate(): void
    {
        $paginator = $this->user->posts()->simplePaginate(2);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
    }

    public function test_has_many_chunk(): void
    {
        $chunks = [];
        $this->user->posts()->chunk(2, function ($posts) use (&$chunks) {
            $chunks[] = $posts;
        });

        $this->assertCount(2, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(1, $chunks[1]);
    }

    // =========================================================================
    // BelongsToMany Query Builder Tests
    // =========================================================================

    public function test_belongs_to_many_first(): void
    {
        $tag = $this->post->tags()->first();

        $this->assertInstanceOf(ImmutableTag::class, $tag);
    }

    public function test_belongs_to_many_first_or_fail(): void
    {
        $tag = $this->post->tags()->firstOrFail();

        $this->assertInstanceOf(ImmutableTag::class, $tag);
    }

    public function test_belongs_to_many_pluck(): void
    {
        $names = $this->post->tags()->pluck('name');

        $this->assertCount(3, $names);
        $this->assertContains('PHP', $names->all());
        $this->assertContains('Laravel', $names->all());
    }

    public function test_belongs_to_many_paginate(): void
    {
        $paginator = $this->post->tags()->paginate(2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(3, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    public function test_belongs_to_many_simple_paginate(): void
    {
        $paginator = $this->post->tags()->simplePaginate(2);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
    }

    // =========================================================================
    // HasManyThrough Query Builder Tests
    // =========================================================================

    public function test_has_many_through_first(): void
    {
        $user = $this->country->users()->first();

        $this->assertInstanceOf(ImmutableUser::class, $user);
    }

    public function test_has_many_through_pluck(): void
    {
        // Use qualified column name to avoid ambiguity between users.name and suppliers.name
        $names = $this->country->users()->pluck('users.name');

        $this->assertCount(3, $names);
    }

    public function test_has_many_through_paginate(): void
    {
        $paginator = $this->country->users()->paginate(2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(3, $paginator->total());
    }

    // =========================================================================
    // MorphMany Query Builder Tests
    // =========================================================================

    public function test_morph_many_first(): void
    {
        $image = $this->post->images()->first();

        $this->assertInstanceOf(ImmutableImage::class, $image);
    }

    public function test_morph_many_pluck(): void
    {
        $paths = $this->post->images()->pluck('path');

        $this->assertCount(2, $paths);
        $this->assertContains('/img1.jpg', $paths->all());
    }

    public function test_morph_many_paginate(): void
    {
        $paginator = $this->post->images()->paginate(1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertEquals(2, $paginator->total());
    }

    // =========================================================================
    // MorphToMany Query Builder Tests
    // =========================================================================

    public function test_morph_to_many_first(): void
    {
        // Add taggables entries
        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tag_id' => 2, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tag = $this->post->morphTags()->first();

        $this->assertInstanceOf(ImmutableTag::class, $tag);
    }

    public function test_morph_to_many_pluck(): void
    {
        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['tag_id' => 2, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $names = $this->post->morphTags()->pluck('name');

        $this->assertCount(2, $names);
    }

    // =========================================================================
    // Additional Query Methods
    // =========================================================================

    public function test_relation_exists(): void
    {
        $this->assertTrue($this->user->posts()->exists());
    }

    public function test_relation_doesnt_exist(): void
    {
        DB::table('users')->insert([
            'id' => 100,
            'name' => 'Empty User',
            'email' => 'empty@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = ImmutableUser::find(100);

        $this->assertFalse($user->posts()->exists());
        $this->assertTrue($user->posts()->doesntExist());
    }

    public function test_relation_sum(): void
    {
        $sum = $this->post->comments()->sum('id');

        $this->assertEquals(6, $sum); // 1 + 2 + 3
    }

    public function test_relation_avg(): void
    {
        $avg = $this->post->comments()->avg('id');

        $this->assertEquals(2, $avg);
    }

    public function test_relation_min(): void
    {
        $min = $this->post->comments()->min('id');

        $this->assertEquals(1, $min);
    }

    public function test_relation_max(): void
    {
        $max = $this->post->comments()->max('id');

        $this->assertEquals(3, $max);
    }

    public function test_relation_where_chain(): void
    {
        $posts = $this->user->posts()->where('published', true)->get();

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(2, $posts);
    }

    public function test_relation_order_by(): void
    {
        $posts = $this->user->posts()->orderBy('title', 'desc')->get();

        $this->assertEquals('Third Post', $posts->first()->title);
    }

    public function test_relation_limit(): void
    {
        $posts = $this->user->posts()->limit(1)->get();

        $this->assertCount(1, $posts);
    }

    // =========================================================================
    // Relation Builder with() Method Tests
    // =========================================================================

    public function test_has_many_relation_builder_with_nested_eager_load(): void
    {
        // Test pattern: $user->posts()->with('comments')->get()
        $posts = $this->user->posts()->with('comments')->get();

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(3, $posts);

        // Verify nested relation was eager loaded
        $firstPost = $posts->first();
        $this->assertTrue($firstPost->relationLoaded('comments'));
    }

    public function test_morph_many_relation_builder_with_nested_eager_load(): void
    {
        // Test pattern: $post->images()->with('imageable')->get()
        // This is the pattern that triggered the IDE warning
        $images = $this->post->images()->with('imageable')->get();

        $this->assertInstanceOf(EloquentCollection::class, $images);
        $this->assertCount(2, $images);

        // Verify nested relation was eager loaded
        $firstImage = $images->first();
        $this->assertTrue($firstImage->relationLoaded('imageable'));
        $this->assertInstanceOf(ImmutablePost::class, $firstImage->imageable);
    }
}
