<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Relations\ImmutablePivot;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableTag;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MorphToManyRelationshipTest extends TestCase
{
    private ImmutablePost $post;
    private ImmutableUser $user;
    private ImmutableTag $tag1;
    private ImmutableTag $tag2;

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
        ]);

        // Create polymorphic pivot entries (taggables table)
        DB::table('taggables')->insert([
            [
                'tag_id' => 1,
                'taggable_type' => ImmutablePost::class,
                'taggable_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tag_id' => 2,
                'taggable_type' => ImmutablePost::class,
                'taggable_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->post = ImmutablePost::find(1);
        $this->user = ImmutableUser::find(1);
        $this->tag1 = ImmutableTag::find(1);
        $this->tag2 = ImmutableTag::find(2);
    }

    // =========================================================================
    // MorphToMany Tests (Post -> Tags via taggables)
    // =========================================================================

    public function test_morph_to_many_lazy_load(): void
    {
        $tags = $this->post->morphTags;

        $this->assertInstanceOf(EloquentCollection::class, $tags);
        $this->assertCount(2, $tags);
        $this->assertInstanceOf(ImmutableTag::class, $tags->first());
    }

    public function test_morph_to_many_eager_load(): void
    {
        $posts = ImmutablePost::with('morphTags')->get();

        $this->assertTrue($posts->first()->relationLoaded('morphTags'));
        $this->assertCount(2, $posts->first()->morphTags);
    }

    public function test_morph_to_many_with_constraints(): void
    {
        $posts = ImmutablePost::with(['morphTags' => fn($q) => $q->where('name', 'PHP')])->get();

        $this->assertCount(1, $posts->first()->morphTags);
        $this->assertEquals('PHP', $posts->first()->morphTags->first()->name);
    }

    public function test_morph_to_many_returns_empty_collection_when_no_relations(): void
    {
        // Create a post without morph tags
        DB::table('posts')->insert([
            'id' => 2,
            'user_id' => 1,
            'title' => 'Post Without Morph Tags',
            'body' => 'Test body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = ImmutablePost::find(2);
        $tags = $post->morphTags;

        $this->assertInstanceOf(EloquentCollection::class, $tags);
        $this->assertCount(0, $tags);
    }

    public function test_morph_to_many_returns_pivot(): void
    {
        $tags = $this->post->morphTags;
        $firstTag = $tags->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstTag->pivot);
        $this->assertNotNull($firstTag->pivot->created_at);
    }

    public function test_morph_to_many_relation_method_returns_builder(): void
    {
        $count = $this->post->morphTags()->count();
        $this->assertEquals(2, $count);
    }

    public function test_morph_to_many_blocks_attach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->morphTags()->attach(3);
    }

    public function test_morph_to_many_blocks_detach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->morphTags()->detach(1);
    }

    public function test_morph_to_many_blocks_sync(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->morphTags()->sync([1, 2, 3]);
    }

    // =========================================================================
    // MorphedByMany Tests (Tag -> Posts via taggables - inverse)
    // =========================================================================

    public function test_morphed_by_many_lazy_load(): void
    {
        $posts = $this->tag1->taggablePosts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(1, $posts);
        $this->assertInstanceOf(ImmutablePost::class, $posts->first());
    }

    public function test_morphed_by_many_eager_load(): void
    {
        $tags = ImmutableTag::with('taggablePosts')->get();

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->first()->relationLoaded('taggablePosts'));
        $this->assertCount(1, $tags->first()->taggablePosts);
    }

    public function test_morphed_by_many_with_constraints(): void
    {
        $tags = ImmutableTag::with(['taggablePosts' => fn($q) => $q->where('title', 'Test Post')])->get();

        $this->assertCount(1, $tags->first()->taggablePosts);
    }

    public function test_morphed_by_many_returns_empty_collection_when_no_relations(): void
    {
        // Create a tag without any taggables
        DB::table('tags')->insert([
            'id' => 3,
            'name' => 'Orphan Tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tag = ImmutableTag::find(3);
        $posts = $tag->taggablePosts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(0, $posts);
    }

    public function test_morphed_by_many_returns_pivot(): void
    {
        $posts = $this->tag1->taggablePosts;
        $firstPost = $posts->first();

        $this->assertInstanceOf(ImmutablePivot::class, $firstPost->pivot);
        $this->assertNotNull($firstPost->pivot->created_at);
    }

    public function test_morphed_by_many_relation_method_returns_builder(): void
    {
        $count = $this->tag1->taggablePosts()->count();
        $this->assertEquals(1, $count);
    }

    public function test_morphed_by_many_blocks_attach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->tag1->taggablePosts()->attach(2);
    }

    public function test_morphed_by_many_blocks_detach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->tag1->taggablePosts()->detach(1);
    }

    // =========================================================================
    // Pivot Immutability Tests
    // =========================================================================

    public function test_morph_pivot_is_immutable(): void
    {
        $tag = $this->post->morphTags->first();
        $pivot = $tag->pivot;

        $this->expectException(ImmutableModelViolationException::class);

        $pivot->tag_id = 5;
    }

    public function test_morph_pivot_array_access_is_read_only(): void
    {
        $tag = $this->post->morphTags->first();
        $pivot = $tag->pivot;

        // Read should work
        $this->assertEquals(1, $pivot['tag_id']);

        // Write should throw
        $this->expectException(ImmutableModelViolationException::class);
        $pivot['tag_id'] = 5;
    }

    // =========================================================================
    // Multiple Morph Types Tests
    // =========================================================================

    public function test_morph_to_many_with_multiple_types(): void
    {
        // Add a user to taggables (different morph type)
        DB::table('taggables')->insert([
            'tag_id' => 1,
            'taggable_type' => ImmutableUser::class,
            'taggable_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tag = ImmutableTag::find(1);

        // Should have both post and user taggables
        $posts = $tag->taggablePosts;
        $users = $tag->taggableUsers;

        $this->assertCount(1, $posts);
        $this->assertCount(1, $users);
        $this->assertInstanceOf(ImmutablePost::class, $posts->first());
        $this->assertInstanceOf(ImmutableUser::class, $users->first());
    }
}
