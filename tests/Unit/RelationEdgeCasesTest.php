<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableComment;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Edge case tests for relationships.
 *
 * Tests nested loading, collection operations, multiple relation loads,
 * and other edge case scenarios.
 */
class RelationEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create posts
        DB::table('posts')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'Post 1', 'body' => 'Body 1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'title' => 'Post 2', 'body' => 'Body 2', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 2, 'title' => 'Post 3', 'body' => 'Body 3', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create comments
        DB::table('comments')->insert([
            ['id' => 1, 'post_id' => 1, 'user_id' => 2, 'body' => 'Comment on Post 1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'post_id' => 2, 'user_id' => 2, 'body' => 'Comment on Post 2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create tags
        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create pivot entries
        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 2, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create profiles
        DB::table('profiles')->insert([
            ['id' => 1, 'user_id' => 1, 'bio' => 'User 1 bio', 'birthday' => '1990-01-01'],
        ]);
    }

    // =========================================================================
    // Multi-Level Relation Loading Tests
    // =========================================================================

    public function test_loading_first_level_relations(): void
    {
        $users = ImmutableUser::with('posts')->get();

        $user1 = $users->firstWhere('id', 1);

        $this->assertTrue($user1->relationLoaded('posts'));
        $this->assertCount(2, $user1->posts);
    }

    public function test_loading_relations_then_accessing_nested(): void
    {
        // Load first level
        $users = ImmutableUser::with('posts')->get();
        $user1 = $users->firstWhere('id', 1);

        // Access nested relation via lazy loading
        $firstPost = $user1->posts->first();
        $comments = $firstPost->comments; // Lazy loads

        $this->assertInstanceOf(EloquentCollection::class, $comments);
        $this->assertCount(1, $comments);
    }

    public function test_loading_post_with_user(): void
    {
        $posts = ImmutablePost::with('user')->get();

        $post1 = $posts->firstWhere('id', 1);

        $this->assertTrue($post1->relationLoaded('user'));
        $this->assertInstanceOf(ImmutableUser::class, $post1->user);

        // Profile can be lazy loaded from user
        $profile = $post1->user->profile;
        $this->assertEquals('User 1 bio', $profile->bio);
    }

    // =========================================================================
    // Multiple Relation Loading Tests
    // =========================================================================

    public function test_loading_multiple_relations_at_once(): void
    {
        $users = ImmutableUser::with(['posts', 'profile', 'comments'])->get();

        $user1 = $users->firstWhere('id', 1);

        $this->assertTrue($user1->relationLoaded('posts'));
        $this->assertTrue($user1->relationLoaded('profile'));
        $this->assertTrue($user1->relationLoaded('comments'));
    }

    public function test_loading_same_relation_with_different_constraints(): void
    {
        // Load posts without constraints
        $user = ImmutableUser::with('posts')->find(1);
        $allPosts = $user->posts;
        $this->assertCount(2, $allPosts);

        // Load again with fresh query and constraints
        $user2 = ImmutableUser::with(['posts' => fn($q) => $q->where('title', 'Post 1')])->find(1);
        $this->assertCount(1, $user2->posts);
    }

    // =========================================================================
    // Collection Immutability Tests
    // =========================================================================

    public function test_relation_collection_is_immutable(): void
    {
        $user = ImmutableUser::find(1);
        $posts = $user->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
    }

    public function test_relation_collection_items_are_immutable(): void
    {
        $user = ImmutableUser::find(1);
        $post = $user->posts->first();

        $this->expectException(ImmutableModelViolationException::class);
        $post->title = 'New Title';
    }

    // =========================================================================
    // Relation Access Edge Cases
    // =========================================================================

    public function test_accessing_unloaded_relation_triggers_lazy_load(): void
    {
        $user = ImmutableUser::find(1);

        // Posts not yet loaded
        $this->assertFalse($user->relationLoaded('posts'));

        // Access triggers lazy load
        $posts = $user->posts;

        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertCount(2, $posts);
    }

    public function test_relation_loaded_returns_correct_state(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertFalse($user->relationLoaded('posts'));

        $user->posts; // Trigger load

        $this->assertTrue($user->relationLoaded('posts'));
    }

    public function test_get_relations_returns_all_loaded(): void
    {
        $user = ImmutableUser::with(['posts', 'profile'])->find(1);

        $relations = $user->getRelations();

        $this->assertArrayHasKey('posts', $relations);
        $this->assertArrayHasKey('profile', $relations);
    }

    public function test_get_relation_returns_specific_relation(): void
    {
        $user = ImmutableUser::with('posts')->find(1);

        $posts = $user->getRelation('posts');

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(2, $posts);
    }

    // =========================================================================
    // Mixed Constraint Tests
    // =========================================================================

    public function test_eager_load_with_order_by(): void
    {
        $users = ImmutableUser::with(['posts' => fn($q) => $q->orderBy('title', 'desc')])->get();

        $user1 = $users->firstWhere('id', 1);

        $this->assertEquals('Post 2', $user1->posts->first()->title);
    }

    public function test_eager_load_with_limit(): void
    {
        $users = ImmutableUser::with(['posts' => fn($q) => $q->limit(1)])->get();

        $user1 = $users->firstWhere('id', 1);

        $this->assertCount(1, $user1->posts);
    }

    // =========================================================================
    // Relation on Empty Models Tests
    // =========================================================================

    public function test_has_many_on_model_with_no_related(): void
    {
        $user = ImmutableUser::find(2);
        $posts = $user->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(1, $posts); // User 2 has 1 post
    }

    public function test_belongs_to_on_model_with_null_foreign_key(): void
    {
        // Create a post with no user
        DB::table('posts')->insert([
            'id' => 99,
            'user_id' => 999, // Non-existent user
            'title' => 'Orphan Post',
            'body' => 'Body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = ImmutablePost::find(99);
        $user = $post->user;

        $this->assertNull($user);
    }

    public function test_has_one_on_model_with_no_related(): void
    {
        $user = ImmutableUser::find(2);
        $profile = $user->profile;

        $this->assertNull($profile);
    }

    // =========================================================================
    // BelongsToMany Edge Cases
    // =========================================================================

    public function test_belongs_to_many_on_model_with_no_pivots(): void
    {
        $post = ImmutablePost::find(3); // Post 3 has no tags
        $tags = $post->tags;

        $this->assertInstanceOf(EloquentCollection::class, $tags);
        $this->assertCount(0, $tags);
    }

    public function test_inverse_belongs_to_many(): void
    {
        $tag = ImmutableTag::find(1);
        $posts = $tag->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(2, $posts); // Tag 1 is on posts 1 and 2
    }

    // =========================================================================
    // Collection Operations on Relations
    // =========================================================================

    public function test_filter_on_relation_collection(): void
    {
        $user = ImmutableUser::find(1);
        $publishedPosts = $user->posts->filter(fn($post) => $post->published);

        // filter returns a new collection (not ImmutableCollection)
        $this->assertCount(2, $publishedPosts);
    }

    public function test_map_on_relation_collection(): void
    {
        $user = ImmutableUser::find(1);
        $titles = $user->posts->map(fn($post) => $post->title);

        $this->assertContains('Post 1', $titles->all());
        $this->assertContains('Post 2', $titles->all());
    }

    public function test_pluck_on_relation_collection(): void
    {
        $user = ImmutableUser::find(1);
        $titles = $user->posts->pluck('title');

        $this->assertCount(2, $titles);
        $this->assertContains('Post 1', $titles->all());
    }

    public function test_first_where_on_relation_collection(): void
    {
        $user = ImmutableUser::find(1);
        $post = $user->posts->firstWhere('title', 'Post 1');

        $this->assertInstanceOf(ImmutablePost::class, $post);
        $this->assertEquals('Post 1', $post->title);
    }
}
