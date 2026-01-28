<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\Mutable\Category;
use Brighten\ImmutableModel\Tests\Models\Mutable\PostMeta;
use Brighten\ImmutableModel\Tests\Models\Mutable\UserSettings;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Collection;

/**
 * Tests for relationships between ImmutableModels and mutable Eloquent models.
 *
 * Covers two scenarios:
 * 1. Forward: ImmutableModel has relationships TO mutable Eloquent models
 * 2. Inverse: Mutable Eloquent models have relationships TO ImmutableModels
 *
 * Both directions work seamlessly using standard Laravel relationship syntax.
 */
class MutableRelationshipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Categories (mutable)
        $this->app['db']->table('categories')->insert([
            [
                'id' => 1,
                'name' => 'Technology',
                'slug' => 'technology',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

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

        // Posts (with category_id)
        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'category_id' => 1,
                'title' => 'First Post',
                'body' => 'First post body',
                'published' => true,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'category_id' => 1,
                'title' => 'Second Post',
                'body' => 'Second post body',
                'published' => false,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'category_id' => 2,
                'title' => 'Bob Post',
                'body' => 'Bob post body',
                'published' => true,
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
            [
                'id' => 4,
                'user_id' => 1,
                'category_id' => null, // Post without category
                'title' => 'Uncategorized Post',
                'body' => 'No category',
                'published' => true,
                'created_at' => '2024-01-04 00:00:00',
                'updated_at' => '2024-01-04 00:00:00',
            ],
        ]);

        // Post meta (mutable hasMany target)
        $this->app['db']->table('post_meta')->insert([
            ['id' => 1, 'post_id' => 1, 'key' => 'featured', 'value' => 'true'],
            ['id' => 2, 'post_id' => 1, 'key' => 'views', 'value' => '1000'],
            ['id' => 3, 'post_id' => 2, 'key' => 'featured', 'value' => 'false'],
        ]);
        // Note: Post 3 and 4 have no meta for empty collection testing

        // User settings (mutable hasOne target)
        $this->app['db']->table('user_settings')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'theme' => 'dark',
                'notifications_enabled' => true,
            ],
        ]);
        // Note: User 2 (Bob) intentionally has no settings for null testing
    }

    // =========================================================================
    // FORWARD DIRECTION: ImmutableModel -> Mutable BelongsTo
    // =========================================================================

    public function test_belongs_to_mutable_lazy_load(): void
    {
        $post = ImmutablePost::find(1);

        $category = $post->category;

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Technology', $category->name);
    }

    public function test_belongs_to_mutable_eager_load(): void
    {
        $post = ImmutablePost::with('category')->find(1);

        $this->assertTrue($post->relationLoaded('category'));
        $this->assertInstanceOf(Category::class, $post->category);
        $this->assertEquals('Technology', $post->category->name);
    }

    public function test_belongs_to_mutable_returns_null_when_not_found(): void
    {
        $post = ImmutablePost::find(4); // Uncategorized post

        $this->assertNull($post->category);
    }

    public function test_belongs_to_mutable_returns_eloquent_model_instance(): void
    {
        $post = ImmutablePost::find(1);
        $category = $post->category;

        // Verify it's a standard Eloquent model, not an ImmutableModel
        $this->assertInstanceOf(Category::class, $category);
        $this->assertNotInstanceOf(ImmutablePost::class, $category);
    }

    // =========================================================================
    // FORWARD DIRECTION: ImmutableModel -> Mutable HasOne
    // =========================================================================

    public function test_has_one_mutable_lazy_load(): void
    {
        $user = ImmutableUser::find(1);

        $settings = $user->mutableSettings;

        $this->assertInstanceOf(UserSettings::class, $settings);
        $this->assertEquals('dark', $settings->theme);
    }

    public function test_has_one_mutable_eager_load(): void
    {
        $user = ImmutableUser::with('mutableSettings')->find(1);

        $this->assertTrue($user->relationLoaded('mutableSettings'));
        $this->assertInstanceOf(UserSettings::class, $user->mutableSettings);
        $this->assertEquals('dark', $user->mutableSettings->theme);
    }

    public function test_has_one_mutable_returns_null_when_not_found(): void
    {
        $user = ImmutableUser::find(2); // Bob has no settings

        $this->assertNull($user->mutableSettings);
    }

    public function test_has_one_mutable_returns_eloquent_model_instance(): void
    {
        $user = ImmutableUser::find(1);
        $settings = $user->mutableSettings;

        $this->assertInstanceOf(UserSettings::class, $settings);
    }

    // =========================================================================
    // FORWARD DIRECTION: ImmutableModel -> Mutable HasMany
    // =========================================================================

    public function test_has_many_mutable_lazy_load(): void
    {
        $post = ImmutablePost::find(1);

        $meta = $post->meta;

        $this->assertCount(2, $meta);
        $this->assertInstanceOf(PostMeta::class, $meta->first());
    }

    public function test_has_many_mutable_eager_load(): void
    {
        $post = ImmutablePost::with('meta')->find(1);

        $this->assertTrue($post->relationLoaded('meta'));
        $this->assertCount(2, $post->meta);
    }

    public function test_has_many_mutable_returns_empty_collection_when_none(): void
    {
        $post = ImmutablePost::find(3); // Bob's post has no meta

        $meta = $post->meta;

        $this->assertCount(0, $meta);
        $this->assertTrue($meta->isEmpty());
    }

    public function test_has_many_mutable_returns_laravel_collection_not_immutable(): void
    {
        $post = ImmutablePost::find(1);
        $meta = $post->meta;

        // Critical: mutable relations return Laravel Collection, NOT ImmutableCollection
        $this->assertInstanceOf(Collection::class, $meta);
        $this->assertNotInstanceOf(ImmutableCollection::class, $meta);
    }

    // =========================================================================
    // COLLECTION TYPE VERIFICATION
    // =========================================================================

    public function test_lazy_load_mutable_has_many_returns_laravel_collection(): void
    {
        $post = ImmutablePost::find(1);
        $meta = $post->meta;

        $this->assertInstanceOf(Collection::class, $meta);
        $this->assertNotInstanceOf(ImmutableCollection::class, $meta);
    }

    public function test_eager_load_mutable_has_many_returns_laravel_collection(): void
    {
        $post = ImmutablePost::with('meta')->find(1);

        $this->assertInstanceOf(Collection::class, $post->meta);
        $this->assertNotInstanceOf(ImmutableCollection::class, $post->meta);
    }

    public function test_empty_mutable_has_many_returns_laravel_collection(): void
    {
        $post = ImmutablePost::find(3); // No meta

        $this->assertInstanceOf(Collection::class, $post->meta);
        $this->assertNotInstanceOf(ImmutableCollection::class, $post->meta);
    }

    // =========================================================================
    // MIXED EAGER LOADING (Immutable and Mutable relations together)
    // =========================================================================

    public function test_mixed_eager_load_immutable_and_mutable_relations(): void
    {
        $user = ImmutableUser::with(['posts', 'mutableSettings'])->find(1);

        // posts should be ImmutableCollection of ImmutablePost
        $this->assertInstanceOf(ImmutableCollection::class, $user->posts);
        $this->assertInstanceOf(ImmutablePost::class, $user->posts->first());

        // mutableSettings should be mutable UserSettings
        $this->assertInstanceOf(UserSettings::class, $user->mutableSettings);
    }

    public function test_mixed_eager_load_on_collection_of_immutable_models(): void
    {
        $posts = ImmutablePost::with(['user', 'category', 'meta'])->get();

        foreach ($posts as $post) {
            // user is immutable
            $this->assertTrue($post->relationLoaded('user'));
            $this->assertInstanceOf(ImmutableUser::class, $post->user);

            // category is mutable (or null)
            $this->assertTrue($post->relationLoaded('category'));
            if ($post->category !== null) {
                $this->assertInstanceOf(Category::class, $post->category);
            }

            // meta is Laravel Collection of mutable models
            $this->assertTrue($post->relationLoaded('meta'));
            $this->assertInstanceOf(Collection::class, $post->meta);
        }
    }

    // =========================================================================
    // MUTABLE MODEL MUTABILITY (Forward direction)
    // Mutable models loaded from immutable parents should remain mutable
    // =========================================================================

    public function test_mutable_related_models_can_be_modified(): void
    {
        $post = ImmutablePost::with('category')->find(1);
        $category = $post->category;

        // This should NOT throw - mutable models remain mutable
        $category->name = 'New Name';
        $this->assertEquals('New Name', $category->name);
    }

    public function test_mutable_collection_items_can_be_modified(): void
    {
        $post = ImmutablePost::with('meta')->find(1);
        $meta = $post->meta;

        // Collection items are mutable
        $firstMeta = $meta->first();
        $firstMeta->value = 'updated';
        $this->assertEquals('updated', $firstMeta->value);
    }

    public function test_mutable_related_model_can_be_saved(): void
    {
        $post = ImmutablePost::with('category')->find(1);
        $category = $post->category;

        // Mutable models can be saved to database
        $category->name = 'Updated Technology';
        $category->save();

        // Verify change persisted
        $freshCategory = Category::find(1);
        $this->assertEquals('Updated Technology', $freshCategory->name);
    }

    // =========================================================================
    // INVERSE DIRECTION: Eloquent Model -> ImmutableModel (Standard Relations)
    //
    // This demonstrates that standard Laravel hasMany() works seamlessly with
    // ImmutableModels because ImmutableModel implements the necessary Laravel
    // interop methods (newInstance, newFromBuilder, newCollection, etc.).
    // =========================================================================

    public function test_eloquent_model_can_use_standard_has_many_to_immutable(): void
    {
        $category = Category::find(1);

        // Standard Laravel property access - NOT a method call
        $posts = $category->posts;

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(ImmutablePost::class, $posts->first());
    }

    public function test_eloquent_model_can_eager_load_immutable_relations(): void
    {
        $category = Category::with('posts')->find(1);

        $this->assertTrue($category->relationLoaded('posts'));
        $this->assertCount(2, $category->posts);
        $this->assertInstanceOf(ImmutablePost::class, $category->posts->first());
    }

    public function test_eloquent_has_many_to_immutable_returns_immutable_collection(): void
    {
        $category = Category::find(1);
        $posts = $category->posts;

        // Laravel wraps results using ImmutableModel::newCollection()
        // which returns ImmutableCollection
        $this->assertInstanceOf(ImmutableCollection::class, $posts);
    }

    public function test_eloquent_eager_load_returns_immutable_collection(): void
    {
        $category = Category::with('posts')->find(1);

        $this->assertInstanceOf(ImmutableCollection::class, $category->posts);
    }

    public function test_eloquent_relation_query_builder_works(): void
    {
        $category = Category::find(1);

        // Standard Laravel relation query builder chaining
        $publishedPosts = $category->posts()->where('published', true)->get();

        $this->assertCount(1, $publishedPosts);
        $this->assertInstanceOf(ImmutableCollection::class, $publishedPosts);
        $this->assertEquals('First Post', $publishedPosts->first()->title);
    }

    // =========================================================================
    // INVERSE DIRECTION: Immutability Protection
    // ImmutableModels loaded through Eloquent relations remain immutable
    // =========================================================================

    public function test_immutable_models_from_eloquent_relation_cannot_be_modified(): void
    {
        $category = Category::find(1);
        $posts = $category->posts;
        $firstPost = $posts->first();

        $this->expectException(ImmutableModelViolationException::class);

        $firstPost->title = 'Changed Title';
    }

    public function test_immutable_models_from_eloquent_relation_cannot_be_saved(): void
    {
        $category = Category::find(1);
        $posts = $category->posts;
        $firstPost = $posts->first();

        $this->expectException(ImmutableModelViolationException::class);

        $firstPost->save();
    }

    public function test_immutable_models_from_eager_loaded_relation_cannot_be_modified(): void
    {
        $category = Category::with('posts')->find(1);
        $firstPost = $category->posts->first();

        $this->expectException(ImmutableModelViolationException::class);

        $firstPost->title = 'Changed Title';
    }

    // =========================================================================
    // INVERSE DIRECTION: Mutable Parent Remains Mutable
    // =========================================================================

    public function test_mutable_parent_can_be_modified_while_immutable_children_cannot(): void
    {
        $category = Category::find(1);

        // Load immutable posts via standard relation
        $posts = $category->posts;

        // Parent is still mutable
        $category->name = 'Modified Category';
        $this->assertEquals('Modified Category', $category->name);

        // But children are immutable
        $this->expectException(ImmutableModelViolationException::class);
        $posts->first()->title = 'Cannot Change';
    }

    public function test_mutable_parent_can_be_saved_after_loading_immutable_relations(): void
    {
        $category = Category::find(1);

        // Load immutable children via standard relation
        $posts = $category->posts;
        $this->assertCount(2, $posts);

        // Parent can still be saved
        $category->name = 'Saved Category';
        $category->save();

        // Verify change persisted
        $freshCategory = Category::find(1);
        $this->assertEquals('Saved Category', $freshCategory->name);
    }

    // =========================================================================
    // RELATION METHOD QUERY BUILDER
    // =========================================================================

    public function test_mutable_relation_method_returns_query_builder(): void
    {
        $post = ImmutablePost::find(1);

        // Calling meta() should allow query chaining
        $featuredMeta = $post->meta()->where('key', 'featured')->get();

        $this->assertInstanceOf(Collection::class, $featuredMeta);
        $this->assertCount(1, $featuredMeta);
        $this->assertEquals('true', $featuredMeta->first()->value);
    }
}
