<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableImage;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MorphRelationshipTest extends TestCase
{
    private ImmutablePost $post;
    private ImmutableUser $user;

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

        // Create images (polymorphic)
        DB::table('images')->insert([
            [
                'id' => 1,
                'imageable_type' => ImmutablePost::class,
                'imageable_id' => 1,
                'path' => '/images/post1-featured.jpg',
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'imageable_type' => ImmutablePost::class,
                'imageable_id' => 1,
                'path' => '/images/post1-gallery1.jpg',
                'is_featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'imageable_type' => ImmutablePost::class,
                'imageable_id' => 1,
                'path' => '/images/post1-gallery2.jpg',
                'is_featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->post = ImmutablePost::find(1);
        $this->user = ImmutableUser::find(1);
    }

    // =========================================================================
    // MorphOne Tests
    // =========================================================================

    public function test_morph_one_lazy_load(): void
    {
        $image = $this->post->featuredImage;

        $this->assertInstanceOf(ImmutableImage::class, $image);
        $this->assertEquals('/images/post1-featured.jpg', $image->path);
    }

    public function test_morph_one_eager_load(): void
    {
        $posts = ImmutablePost::with('featuredImage')->get();

        $this->assertTrue($posts->first()->relationLoaded('featuredImage'));
        $this->assertInstanceOf(ImmutableImage::class, $posts->first()->featuredImage);
    }

    public function test_morph_one_returns_null_when_no_relation(): void
    {
        // Create a post without images
        DB::table('posts')->insert([
            'id' => 2,
            'user_id' => 1,
            'title' => 'Post Without Images',
            'body' => 'Test body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = ImmutablePost::find(2);
        $image = $post->featuredImage;

        $this->assertNull($image);
    }

    public function test_morph_one_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->featuredImage()->create(['path' => '/new.jpg']);
    }

    // =========================================================================
    // MorphMany Tests
    // =========================================================================

    public function test_morph_many_lazy_load(): void
    {
        $images = $this->post->images;

        $this->assertInstanceOf(ImmutableCollection::class, $images);
        $this->assertCount(3, $images);
        $this->assertInstanceOf(ImmutableImage::class, $images->first());
    }

    public function test_morph_many_eager_load(): void
    {
        $posts = ImmutablePost::with('images')->get();

        $this->assertTrue($posts->first()->relationLoaded('images'));
        $this->assertCount(3, $posts->first()->images);
    }

    public function test_morph_many_with_constraints(): void
    {
        $posts = ImmutablePost::with(['images' => fn($q) => $q->where('is_featured', false)])->get();

        $this->assertCount(2, $posts->first()->images);
    }

    public function test_morph_many_returns_empty_collection_when_no_relations(): void
    {
        // Create a post without images
        DB::table('posts')->insert([
            'id' => 3,
            'user_id' => 1,
            'title' => 'Another Post Without Images',
            'body' => 'Test body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = ImmutablePost::find(3);
        $images = $post->images;

        $this->assertInstanceOf(ImmutableCollection::class, $images);
        $this->assertCount(0, $images);
    }

    public function test_morph_many_blocks_create_many(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->images()->createMany([
            ['path' => '/new1.jpg'],
            ['path' => '/new2.jpg'],
        ]);
    }

    // =========================================================================
    // MorphTo Tests
    // =========================================================================

    public function test_morph_to_lazy_load(): void
    {
        $image = ImmutableImage::find(1);
        $imageable = $image->imageable;

        $this->assertInstanceOf(ImmutablePost::class, $imageable);
        $this->assertEquals('Test Post', $imageable->title);
    }

    public function test_morph_to_eager_load(): void
    {
        $images = ImmutableImage::with('imageable')->get();

        $this->assertTrue($images->first()->relationLoaded('imageable'));
        $this->assertInstanceOf(ImmutablePost::class, $images->first()->imageable);
    }

    public function test_morph_to_returns_null_when_no_relation(): void
    {
        // Create an image with no imageable
        DB::table('images')->insert([
            'id' => 10,
            'imageable_type' => null,
            'imageable_id' => null,
            'path' => '/orphan.jpg',
            'is_featured' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $image = ImmutableImage::find(10);
        $imageable = $image->imageable;

        $this->assertNull($imageable);
    }

    public function test_morph_to_eager_load_with_different_types(): void
    {
        // Create a user image
        DB::table('images')->insert([
            'id' => 4,
            'imageable_type' => ImmutableUser::class,
            'imageable_id' => 1,
            'path' => '/images/user-avatar.jpg',
            'is_featured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Eager load all images with their imageables
        $images = ImmutableImage::with('imageable')->whereIn('id', [1, 4])->get();

        $this->assertCount(2, $images);

        $postImage = $images->firstWhere('id', 1);
        $userImage = $images->firstWhere('id', 4);

        $this->assertInstanceOf(ImmutablePost::class, $postImage->imageable);
        $this->assertInstanceOf(ImmutableUser::class, $userImage->imageable);
    }

    public function test_morph_to_blocks_associate(): void
    {
        $image = ImmutableImage::find(1);

        $this->expectException(ImmutableModelViolationException::class);

        $image->imageable()->associate($this->user);
    }

    public function test_morph_to_blocks_dissociate(): void
    {
        $image = ImmutableImage::find(1);

        $this->expectException(ImmutableModelViolationException::class);

        $image->imageable()->dissociate();
    }

    // =========================================================================
    // Relation Method Returns Builder Tests
    // =========================================================================

    public function test_morph_one_relation_method_returns_builder(): void
    {
        // Note: count() on a MorphOne returns all matching records, not just 1.
        // The "One" in MorphOne only affects getResults() which uses first().
        // All 3 images match the morph constraint (imageable_type/id).
        $count = $this->post->featuredImage()->count();
        $this->assertEquals(3, $count);
    }

    public function test_morph_many_relation_method_returns_builder(): void
    {
        $count = $this->post->images()->count();
        $this->assertEquals(3, $count);
    }

    public function test_morph_to_relation_method_returns_builder(): void
    {
        $image = ImmutableImage::find(1);
        $count = $image->imageable()->count();
        $this->assertEquals(1, $count);
    }
}
