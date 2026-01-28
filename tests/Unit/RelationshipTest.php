<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableComment;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableProfile;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;

class RelationshipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Users
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        // Profiles (hasOne)
        $this->app['db']->table('profiles')->insert([
            'id' => 1,
            'user_id' => 1,
            'bio' => 'Alice bio',
            'birthday' => '1990-01-01',
        ]);

        // Posts (hasMany)
        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'title' => 'First Post',
                'body' => 'First post body',
                'published' => true,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'title' => 'Second Post',
                'body' => 'Second post body',
                'published' => false,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'title' => 'Bob Post',
                'body' => 'Bob post body',
                'published' => true,
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ]);

        // Comments (belongsTo)
        $this->app['db']->table('comments')->insert([
            [
                'id' => 1,
                'post_id' => 1,
                'user_id' => 2,
                'body' => 'Great post!',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'post_id' => 1,
                'user_id' => 1,
                'body' => 'Thanks!',
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
        ]);
    }

    // =========================================================================
    // BELONGS TO
    // =========================================================================

    public function test_belongs_to_lazy_load(): void
    {
        $comment = ImmutableComment::find(1);

        $user = $comment->user;

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('Bob', $user->name);
    }

    public function test_belongs_to_eager_load(): void
    {
        $comment = ImmutableComment::with('user')->find(1);

        $this->assertTrue($comment->relationLoaded('user'));
        $this->assertInstanceOf(ImmutableUser::class, $comment->user);
        $this->assertEquals('Bob', $comment->user->name);
    }

    public function test_belongs_to_returns_null_when_foreign_key_is_null(): void
    {
        // Insert a comment without a user
        $this->app['db']->table('comments')->insert([
            'id' => 3,
            'post_id' => 1,
            'user_id' => 999, // Non-existent user
            'body' => 'Orphan comment',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $comment = ImmutableComment::find(3);
        $this->assertNull($comment->user);
    }

    // =========================================================================
    // HAS ONE
    // =========================================================================

    public function test_has_one_lazy_load(): void
    {
        $user = ImmutableUser::find(1);

        $profile = $user->profile;

        $this->assertInstanceOf(ImmutableProfile::class, $profile);
        $this->assertEquals('Alice bio', $profile->bio);
    }

    public function test_has_one_eager_load(): void
    {
        $user = ImmutableUser::with('profile')->find(1);

        $this->assertTrue($user->relationLoaded('profile'));
        $this->assertInstanceOf(ImmutableProfile::class, $user->profile);
    }

    public function test_has_one_returns_null_when_not_found(): void
    {
        $user = ImmutableUser::find(2); // Bob has no profile

        $this->assertNull($user->profile);
    }

    // =========================================================================
    // HAS MANY
    // =========================================================================

    public function test_has_many_lazy_load(): void
    {
        $user = ImmutableUser::find(1);

        $posts = $user->posts;

        $this->assertInstanceOf(ImmutableCollection::class, $posts);
        $this->assertCount(2, $posts);
    }

    public function test_has_many_eager_load(): void
    {
        $user = ImmutableUser::with('posts')->find(1);

        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertInstanceOf(ImmutableCollection::class, $user->posts);
        $this->assertCount(2, $user->posts);
    }

    public function test_has_many_returns_empty_collection_when_none(): void
    {
        // Insert a user with no posts
        $this->app['db']->table('users')->insert([
            'id' => 3,
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'settings' => null,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $user = ImmutableUser::find(3);
        $posts = $user->posts;

        $this->assertInstanceOf(ImmutableCollection::class, $posts);
        $this->assertTrue($posts->isEmpty());
    }

    // =========================================================================
    // EAGER LOADING ON COLLECTIONS
    // =========================================================================

    public function test_eager_load_on_collection(): void
    {
        $users = ImmutableUser::with('posts')->get();

        foreach ($users as $user) {
            $this->assertTrue($user->relationLoaded('posts'));
        }
    }

    public function test_eager_load_with_constraints(): void
    {
        $users = ImmutableUser::with(['posts' => fn($q) => $q->where('published', true)])->get();

        $alice = $users->first();
        $this->assertCount(1, $alice->posts); // Only published posts
    }

    public function test_eager_load_multiple_relations(): void
    {
        $users = ImmutableUser::with(['posts', 'profile'])->get();

        foreach ($users as $user) {
            $this->assertTrue($user->relationLoaded('posts'));
            $this->assertTrue($user->relationLoaded('profile'));
        }
    }

    // =========================================================================
    // RELATION METHOD RETURNS QUERY BUILDER
    // =========================================================================

    public function test_relation_method_returns_builder(): void
    {
        $user = ImmutableUser::find(1);

        // Calling posts() returns a relation that can be chained
        $publishedPosts = $user->posts()->where('published', true)->get();

        $this->assertCount(1, $publishedPosts);
    }

    // =========================================================================
    // RELATION IMMUTABILITY
    // =========================================================================

    public function test_related_immutable_models_are_immutable(): void
    {
        $post = ImmutablePost::with('user')->find(1);

        $this->expectException(\Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException::class);

        $post->user->name = 'Changed';
    }

    public function test_relation_loaded_returns_correct_state(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertFalse($user->relationLoaded('posts'));

        // Access the relation to load it
        $user->posts;

        $this->assertTrue($user->relationLoaded('posts'));
    }

    // =========================================================================
    // GET RELATIONS
    // =========================================================================

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

        $this->assertInstanceOf(ImmutableCollection::class, $posts);
    }
}
