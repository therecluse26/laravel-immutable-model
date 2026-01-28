<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentComment;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentTag;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentCountry;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentSupplier;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableComment;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableSupplier;

/**
 * Tests that ImmutableModel relationship behavior matches Eloquent exactly.
 */
class RelationshipParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRelationshipTestData();
    }

    protected function seedRelationshipTestData(): void
    {
        // Countries
        $this->app['db']->table('countries')->insert([
            ['id' => 1, 'name' => 'USA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Canada', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Suppliers
        $this->app['db']->table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Acme Corp', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'country_id' => 1, 'name' => 'Widget Inc', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Users
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => json_encode(['theme' => 'dark']),
                'email_verified_at' => '2024-01-01 12:00:00',
                'supplier_id' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'supplier_id' => 1,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'supplier_id' => null,
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ]);

        // Profiles
        $this->app['db']->table('profiles')->insert([
            ['id' => 1, 'user_id' => 1, 'bio' => 'Software developer', 'birthday' => '1990-05-15'],
        ]);

        // Posts
        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'First Post',
                'body' => 'Content',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'Second Post',
                'body' => 'More content',
                'published' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'category_id' => null,
                'title' => 'Bob Post',
                'body' => 'Bob content',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Comments
        $this->app['db']->table('comments')->insert([
            [
                'id' => 1,
                'post_id' => 1,
                'user_id' => 2,
                'body' => 'Great post!',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'post_id' => 1,
                'user_id' => 3,
                'body' => 'I agree!',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Tags
        $this->app['db']->table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Post-Tag pivot
        $this->app['db']->table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Images (for morph relations)
        $this->app['db']->table('images')->insert([
            [
                'id' => 1,
                'imageable_type' => EloquentPost::class,
                'imageable_id' => 1,
                'path' => '/images/post1.jpg',
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'imageable_type' => EloquentPost::class,
                'imageable_id' => 1,
                'path' => '/images/post1-2.jpg',
                'is_featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    // =========================================================================
    // BELONGS TO
    // =========================================================================

    public function test_belongs_to_lazy_load(): void
    {
        $eloquentPost = EloquentPost::find(1);
        $immutablePost = ImmutablePost::find(1);

        $this->assertModelParity(
            $eloquentPost->user,
            $immutablePost->user,
            'belongsTo lazy load differs'
        );
    }

    public function test_belongs_to_eager_load(): void
    {
        $eloquentPost = EloquentPost::with('user')->find(1);
        $immutablePost = ImmutablePost::with('user')->find(1);

        $this->assertModelParity(
            $eloquentPost->user,
            $immutablePost->user,
            'belongsTo eager load differs'
        );
    }

    public function test_belongs_to_returns_null_when_foreign_key_null(): void
    {
        // User 3 has no supplier (supplier_id = null)
        $eloquentUser = EloquentUser::find(3);
        $immutableUser = ImmutableUser::find(3);

        $this->assertNull($eloquentUser->supplier);
        $this->assertNull($immutableUser->supplier);
    }

    // =========================================================================
    // HAS ONE
    // =========================================================================

    public function test_has_one_lazy_load(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        $this->assertModelParity(
            $eloquentUser->profile,
            $immutableUser->profile,
            'hasOne lazy load differs'
        );
    }

    public function test_has_one_eager_load(): void
    {
        $eloquentUser = EloquentUser::with('profile')->find(1);
        $immutableUser = ImmutableUser::with('profile')->find(1);

        $this->assertModelParity(
            $eloquentUser->profile,
            $immutableUser->profile,
            'hasOne eager load differs'
        );
    }

    public function test_has_one_returns_null_when_not_found(): void
    {
        // User 2 has no profile
        $eloquentUser = EloquentUser::find(2);
        $immutableUser = ImmutableUser::find(2);

        $this->assertNull($eloquentUser->profile);
        $this->assertNull($immutableUser->profile);
    }

    // =========================================================================
    // HAS MANY
    // =========================================================================

    public function test_has_many_lazy_load(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        $this->assertCollectionParity(
            $eloquentUser->posts,
            $immutableUser->posts,
            'hasMany lazy load differs'
        );
    }

    public function test_has_many_eager_load(): void
    {
        $eloquentUser = EloquentUser::with('posts')->find(1);
        $immutableUser = ImmutableUser::with('posts')->find(1);

        $this->assertCollectionParity(
            $eloquentUser->posts,
            $immutableUser->posts,
            'hasMany eager load differs'
        );
    }

    public function test_has_many_returns_empty_collection(): void
    {
        // User 3 has no posts
        $eloquentUser = EloquentUser::find(3);
        $immutableUser = ImmutableUser::find(3);

        $this->assertTrue($eloquentUser->posts->isEmpty());
        $this->assertTrue($immutableUser->posts->isEmpty());
        $this->assertEquals(0, $eloquentUser->posts->count());
        $this->assertEquals(0, $immutableUser->posts->count());
    }

    public function test_has_many_with_count(): void
    {
        $eloquentUser = EloquentUser::withCount('posts')->find(1);
        $immutableUser = ImmutableUser::withCount('posts')->find(1);

        $this->assertEquals($eloquentUser->posts_count, $immutableUser->posts_count);
    }

    // =========================================================================
    // BELONGS TO MANY
    // =========================================================================

    public function test_belongs_to_many_lazy_load(): void
    {
        $eloquentPost = EloquentPost::find(1);
        $immutablePost = ImmutablePost::find(1);

        $eloquentTags = $eloquentPost->tags->sortBy('id')->values();
        $immutableTags = $immutablePost->tags->sortBy('id')->values();

        $this->assertEquals($eloquentTags->count(), $immutableTags->count());

        for ($i = 0; $i < $eloquentTags->count(); $i++) {
            $this->assertEquals(
                $eloquentTags[$i]->toArray(),
                $immutableTags[$i]->toArray(),
                "Tag at index {$i} differs"
            );
        }
    }

    public function test_belongs_to_many_pivot_data(): void
    {
        $eloquentPost = EloquentPost::find(1);
        $immutablePost = ImmutablePost::find(1);

        $eloquentTag = $eloquentPost->tags->first();
        $immutableTag = $immutablePost->tags->first();

        // Both should have pivot data
        $this->assertNotNull($eloquentTag->pivot);
        $this->assertNotNull($immutableTag->pivot);

        $this->assertEquals($eloquentTag->pivot->order, $immutableTag->pivot->order);
    }

    public function test_belongs_to_many_empty(): void
    {
        // Post 2 has no tags
        $eloquentPost = EloquentPost::find(2);
        $immutablePost = ImmutablePost::find(2);

        $this->assertTrue($eloquentPost->tags->isEmpty());
        $this->assertTrue($immutablePost->tags->isEmpty());
    }

    // =========================================================================
    // HAS MANY THROUGH
    // =========================================================================

    public function test_has_many_through_lazy_load(): void
    {
        $eloquentCountry = EloquentCountry::find(1);
        $immutableCountry = ImmutableCountry::find(1);

        $eloquentUsers = $eloquentCountry->users->sortBy('id')->values();
        $immutableUsers = $immutableCountry->users->sortBy('id')->values();

        $this->assertEquals($eloquentUsers->count(), $immutableUsers->count());
    }

    public function test_has_many_through_eager_load(): void
    {
        $eloquentCountry = EloquentCountry::with('users')->find(1);
        $immutableCountry = ImmutableCountry::with('users')->find(1);

        $this->assertEquals(
            $eloquentCountry->users->count(),
            $immutableCountry->users->count()
        );
    }

    public function test_has_many_through_empty(): void
    {
        // Country 2 has no suppliers, thus no users
        $eloquentCountry = EloquentCountry::find(2);
        $immutableCountry = ImmutableCountry::find(2);

        $this->assertTrue($eloquentCountry->users->isEmpty());
        $this->assertTrue($immutableCountry->users->isEmpty());
    }

    public function test_has_many_through_respects_soft_deletes_parity(): void
    {
        // Soft delete a supplier
        $this->app['db']->table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        $eloquentCountry = EloquentCountry::find(1);
        $immutableCountry = ImmutableCountry::find(1);

        // Both should exclude users from deleted supplier
        $this->assertEquals(
            $eloquentCountry->users->count(),
            $immutableCountry->users->count(),
            'User count with soft deleted supplier differs'
        );
    }

    public function test_has_many_through_with_trashed_parents_parity(): void
    {
        // Soft delete a supplier
        $this->app['db']->table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Create anonymous classes that use withTrashedParents
        $eloquentCountry = new class extends EloquentCountry {
            public function usersWithTrashed()
            {
                return $this->hasManyThrough(
                    \Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser::class,
                    EloquentSupplier::class,
                    'country_id',
                    'supplier_id',
                    'id',
                    'id'
                )->withTrashedParents();
            }
        };

        $immutableCountry = new class extends ImmutableCountry {
            public function usersWithTrashed()
            {
                return $this->hasManyThrough(
                    ImmutableUser::class,
                    ImmutableSupplier::class,
                    'country_id',
                    'supplier_id',
                    'id',
                    'id'
                )->withTrashedParents();
            }
        };

        $eloquentCountry = $eloquentCountry::find(1);
        $immutableCountry = $immutableCountry::find(1);

        // Both should include users from deleted supplier
        $this->assertEquals(
            $eloquentCountry->usersWithTrashed->count(),
            $immutableCountry->usersWithTrashed->count(),
            'User count with withTrashedParents differs'
        );
    }

    // =========================================================================
    // HAS ONE THROUGH
    // =========================================================================

    public function test_has_one_through_lazy_load(): void
    {
        $eloquentCountry = EloquentCountry::find(1);
        $immutableCountry = ImmutableCountry::find(1);

        // Both should return the first user
        $eloquentUser = $eloquentCountry->firstUser;
        $immutableUser = $immutableCountry->firstUser;

        $this->assertNotNull($eloquentUser);
        $this->assertNotNull($immutableUser);
        $this->assertEquals($eloquentUser->id, $immutableUser->id);
    }

    public function test_has_one_through_returns_null(): void
    {
        // Country 2 has no suppliers
        $eloquentCountry = EloquentCountry::find(2);
        $immutableCountry = ImmutableCountry::find(2);

        $this->assertNull($eloquentCountry->firstUser);
        $this->assertNull($immutableCountry->firstUser);
    }

    public function test_has_one_through_respects_soft_deletes_parity(): void
    {
        // Soft delete the first supplier
        $this->app['db']->table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        $eloquentCountry = EloquentCountry::find(1);
        $immutableCountry = ImmutableCountry::find(1);

        // Both should skip the deleted supplier and get user from non-deleted one
        // or return null if no non-deleted suppliers have users
        $eloquentUser = $eloquentCountry->firstUser;
        $immutableUser = $immutableCountry->firstUser;

        if ($eloquentUser === null) {
            $this->assertNull($immutableUser);
        } else {
            $this->assertEquals($eloquentUser->id, $immutableUser->id);
        }
    }

    public function test_has_one_through_with_trashed_parents_parity(): void
    {
        // Soft delete the first supplier
        $this->app['db']->table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Create anonymous classes that use withTrashedParents
        $eloquentCountry = new class extends EloquentCountry {
            public function firstUserWithTrashed()
            {
                return $this->hasOneThrough(
                    \Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser::class,
                    EloquentSupplier::class,
                    'country_id',
                    'supplier_id',
                    'id',
                    'id'
                )->withTrashedParents();
            }
        };

        $immutableCountry = new class extends ImmutableCountry {
            public function firstUserWithTrashed()
            {
                return $this->hasOneThrough(
                    ImmutableUser::class,
                    ImmutableSupplier::class,
                    'country_id',
                    'supplier_id',
                    'id',
                    'id'
                )->withTrashedParents();
            }
        };

        $eloquentCountry = $eloquentCountry::find(1);
        $immutableCountry = $immutableCountry::find(1);

        // Both should include user from deleted supplier
        $this->assertNotNull($eloquentCountry->firstUserWithTrashed);
        $this->assertNotNull($immutableCountry->firstUserWithTrashed);
        $this->assertEquals(
            $eloquentCountry->firstUserWithTrashed->id,
            $immutableCountry->firstUserWithTrashed->id
        );
    }

    // =========================================================================
    // EAGER LOADING WITH CONSTRAINTS
    // =========================================================================

    public function test_eager_load_with_constraint(): void
    {
        $eloquentUsers = EloquentUser::with(['posts' => fn($q) => $q->where('published', true)])
            ->orderBy('id')
            ->get();
        $immutableUsers = ImmutableUser::with(['posts' => fn($q) => $q->where('published', true)])
            ->orderBy('id')
            ->get();

        $this->assertEquals($eloquentUsers->count(), $immutableUsers->count());

        for ($i = 0; $i < $eloquentUsers->count(); $i++) {
            $this->assertEquals(
                $eloquentUsers[$i]->posts->count(),
                $immutableUsers[$i]->posts->count(),
                "User {$i} published post count differs"
            );
        }
    }

    public function test_nested_eager_loading(): void
    {
        $eloquentUsers = EloquentUser::with('posts.comments')->orderBy('id')->get();
        $immutableUsers = ImmutableUser::with('posts.comments')->orderBy('id')->get();

        $this->assertEquals($eloquentUsers->count(), $immutableUsers->count());

        // Check first user's first post's comments
        $eloquentComments = $eloquentUsers[0]->posts->first()?->comments ?? collect();
        $immutableComments = $immutableUsers[0]->posts->first()?->comments;

        $this->assertEquals($eloquentComments->count(), $immutableComments?->count() ?? 0);
    }

    public function test_multiple_eager_loads(): void
    {
        $eloquentUser = EloquentUser::with(['profile', 'posts', 'comments'])->find(1);
        $immutableUser = ImmutableUser::with(['profile', 'posts', 'comments'])->find(1);

        // Profile
        $this->assertModelParity($eloquentUser->profile, $immutableUser->profile);

        // Posts count
        $this->assertEquals($eloquentUser->posts->count(), $immutableUser->posts->count());

        // Comments count
        $this->assertEquals($eloquentUser->comments->count(), $immutableUser->comments->count());
    }

    // =========================================================================
    // RELATION METHOD RETURNS BUILDER
    // =========================================================================

    public function test_relation_method_allows_chaining(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        $eloquentPosts = $eloquentUser->posts()->where('published', true)->get();
        $immutablePosts = $immutableUser->posts()->where('published', true)->get();

        $this->assertEquals($eloquentPosts->count(), $immutablePosts->count());
    }

    public function test_relation_method_first(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        $eloquentPost = $eloquentUser->posts()->orderBy('id')->first();
        $immutablePost = $immutableUser->posts()->orderBy('id')->first();

        $this->assertModelParity($eloquentPost, $immutablePost);
    }

    public function test_relation_method_count(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        $this->assertEquals(
            $eloquentUser->posts()->count(),
            $immutableUser->posts()->count()
        );
    }

    // =========================================================================
    // RELATION LOADED STATE
    // =========================================================================

    public function test_relation_loaded_after_access(): void
    {
        $eloquentUser = EloquentUser::find(1);
        $immutableUser = ImmutableUser::find(1);

        // Before access
        $this->assertFalse($eloquentUser->relationLoaded('posts'));
        $this->assertFalse($immutableUser->relationLoaded('posts'));

        // Access relation
        $_ = $eloquentUser->posts;
        $_ = $immutableUser->posts;

        // After access
        $this->assertTrue($eloquentUser->relationLoaded('posts'));
        $this->assertTrue($immutableUser->relationLoaded('posts'));
    }

    public function test_relation_loaded_after_eager(): void
    {
        $eloquentUser = EloquentUser::with('posts')->find(1);
        $immutableUser = ImmutableUser::with('posts')->find(1);

        $this->assertTrue($eloquentUser->relationLoaded('posts'));
        $this->assertTrue($immutableUser->relationLoaded('posts'));
    }
}
