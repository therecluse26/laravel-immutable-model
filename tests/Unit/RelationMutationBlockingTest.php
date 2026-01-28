<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Tests\Models\ImmutableComment;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableImage;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive tests for mutation blocking on all relation types.
 *
 * Ensures that all mutation methods throw ImmutableModelViolationException
 * when called on immutable relation builders.
 */
class RelationMutationBlockingTest extends TestCase
{
    private ImmutableUser $user;
    private ImmutablePost $post;
    private ImmutableCountry $country;
    private ImmutableImage $image;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create post
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
        ]);

        // Create pivot entries
        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create images
        DB::table('images')->insert([
            'id' => 1,
            'imageable_type' => ImmutablePost::class,
            'imageable_id' => 1,
            'path' => '/test.jpg',
            'is_featured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create country and suppliers
        DB::table('countries')->insert([
            'id' => 1,
            'name' => 'United States',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('suppliers')->insert([
            'id' => 1,
            'country_id' => 1,
            'name' => 'Supplier A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', 1)->update(['supplier_id' => 1]);

        // Create taggables
        DB::table('taggables')->insert([
            'tag_id' => 1,
            'taggable_type' => ImmutablePost::class,
            'taggable_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = ImmutableUser::find(1);
        $this->post = ImmutablePost::find(1);
        $this->country = ImmutableCountry::find(1);
        $this->image = ImmutableImage::find(1);
    }

    // =========================================================================
    // HasMany Mutation Blocking
    // =========================================================================

    public function test_has_many_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->create(['title' => 'New Post', 'body' => 'Body']);
    }

    public function test_has_many_blocks_create_many(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->createMany([
            ['title' => 'Post 1', 'body' => 'Body 1'],
            ['title' => 'Post 2', 'body' => 'Body 2'],
        ]);
    }

    public function test_has_many_blocks_save(): void
    {
        $post = new ImmutablePost();

        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->save($post);
    }

    public function test_has_many_blocks_save_many(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->saveMany([new ImmutablePost()]);
    }

    public function test_has_many_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->update(['published' => false]);
    }

    public function test_has_many_blocks_delete(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->posts()->delete();
    }

    // =========================================================================
    // BelongsToMany Mutation Blocking
    // =========================================================================

    public function test_belongs_to_many_blocks_attach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->attach(2);
    }

    public function test_belongs_to_many_blocks_detach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->detach(1);
    }

    public function test_belongs_to_many_blocks_sync(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->sync([1, 2]);
    }

    public function test_belongs_to_many_blocks_sync_without_detaching(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->syncWithoutDetaching([2]);
    }

    public function test_belongs_to_many_blocks_toggle(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->toggle([1, 2]);
    }

    public function test_belongs_to_many_blocks_update_existing_pivot(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->updateExistingPivot(1, ['order' => 5]);
    }

    public function test_belongs_to_many_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->create(['name' => 'New Tag']);
    }

    public function test_belongs_to_many_blocks_save(): void
    {
        $tag = new ImmutableTag();

        $this->expectException(ImmutableModelViolationException::class);
        $this->post->tags()->save($tag);
    }

    // =========================================================================
    // HasManyThrough Mutation Blocking
    // =========================================================================

    public function test_has_many_through_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->country->users()->create(['name' => 'New User', 'email' => 'new@test.com']);
    }

    public function test_has_many_through_blocks_save(): void
    {
        $user = new ImmutableUser();

        $this->expectException(ImmutableModelViolationException::class);
        $this->country->users()->save($user);
    }

    public function test_has_many_through_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->country->users()->update(['name' => 'Updated']);
    }

    public function test_has_many_through_blocks_delete(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->country->users()->delete();
    }

    // =========================================================================
    // MorphOne Mutation Blocking
    // =========================================================================

    public function test_morph_one_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->featuredImage()->create(['path' => '/new.jpg']);
    }

    public function test_morph_one_blocks_save(): void
    {
        $image = new ImmutableImage();

        $this->expectException(ImmutableModelViolationException::class);
        $this->post->featuredImage()->save($image);
    }

    public function test_morph_one_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->featuredImage()->update(['path' => '/updated.jpg']);
    }

    public function test_morph_one_blocks_delete(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->featuredImage()->delete();
    }

    // =========================================================================
    // MorphMany Mutation Blocking
    // =========================================================================

    public function test_morph_many_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->images()->create(['path' => '/new.jpg']);
    }

    public function test_morph_many_blocks_create_many(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->images()->createMany([
            ['path' => '/new1.jpg'],
            ['path' => '/new2.jpg'],
        ]);
    }

    public function test_morph_many_blocks_save(): void
    {
        $image = new ImmutableImage();

        $this->expectException(ImmutableModelViolationException::class);
        $this->post->images()->save($image);
    }

    public function test_morph_many_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->images()->update(['is_featured' => false]);
    }

    public function test_morph_many_blocks_delete(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->images()->delete();
    }

    // =========================================================================
    // MorphTo Mutation Blocking
    // =========================================================================

    public function test_morph_to_blocks_associate(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->image->imageable()->associate($this->user);
    }

    public function test_morph_to_blocks_dissociate(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->image->imageable()->dissociate();
    }

    public function test_morph_to_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->image->imageable()->update(['title' => 'Updated']);
    }

    public function test_morph_to_blocks_delete(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->image->imageable()->delete();
    }

    // =========================================================================
    // MorphToMany Mutation Blocking
    // =========================================================================

    public function test_morph_to_many_blocks_attach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->morphTags()->attach(2);
    }

    public function test_morph_to_many_blocks_detach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->morphTags()->detach(1);
    }

    public function test_morph_to_many_blocks_sync(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->morphTags()->sync([1, 2]);
    }

    public function test_morph_to_many_blocks_toggle(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->morphTags()->toggle([1]);
    }

    public function test_morph_to_many_blocks_update_existing_pivot(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->morphTags()->updateExistingPivot(1, ['extra' => 'data']);
    }

    // =========================================================================
    // MorphedByMany Mutation Blocking
    // =========================================================================

    public function test_morphed_by_many_blocks_attach(): void
    {
        $tag = ImmutableTag::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $tag->taggablePosts()->attach(2);
    }

    public function test_morphed_by_many_blocks_detach(): void
    {
        $tag = ImmutableTag::find(1);

        $this->expectException(ImmutableModelViolationException::class);
        $tag->taggablePosts()->detach(1);
    }

    // =========================================================================
    // BelongsTo Mutation Blocking
    // =========================================================================

    public function test_belongs_to_blocks_associate(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->user()->associate($this->user);
    }

    public function test_belongs_to_blocks_dissociate(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->user()->dissociate();
    }

    public function test_belongs_to_blocks_update(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->post->user()->update(['name' => 'Updated']);
    }

    // =========================================================================
    // HasOne Mutation Blocking
    // =========================================================================

    public function test_has_one_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);
        $this->user->profile()->create(['bio' => 'New bio']);
    }

    public function test_has_one_blocks_save(): void
    {
        $profile = new \Brighten\ImmutableModel\Tests\Models\ImmutableProfile();

        $this->expectException(ImmutableModelViolationException::class);
        $this->user->profile()->save($profile);
    }

    public function test_has_one_blocks_update(): void
    {
        // First create a profile
        DB::table('profiles')->insert([
            'id' => 1,
            'user_id' => 1,
            'bio' => 'Test bio',
        ]);

        $this->expectException(ImmutableModelViolationException::class);
        $this->user->profile()->update(['bio' => 'Updated bio']);
    }

    // =========================================================================
    // Pivot Mutation Blocking
    // =========================================================================

    public function test_pivot_property_mutation_blocked(): void
    {
        $tag = $this->post->tags->first();
        $pivot = $tag->pivot;

        $this->expectException(ImmutableModelViolationException::class);
        $pivot->order = 99;
    }

    public function test_pivot_array_mutation_blocked(): void
    {
        $tag = $this->post->tags->first();
        $pivot = $tag->pivot;

        $this->expectException(ImmutableModelViolationException::class);
        $pivot['order'] = 99;
    }

    public function test_pivot_unset_blocked(): void
    {
        $tag = $this->post->tags->first();
        $pivot = $tag->pivot;

        $this->expectException(ImmutableModelViolationException::class);
        unset($pivot['order']);
    }
}
