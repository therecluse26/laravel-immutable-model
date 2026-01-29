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

class BelongsToManyRelationshipTest extends TestCase
{
    private ImmutableUser $user;
    private ImmutablePost $post;
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

        // Create pivot entries
        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = ImmutableUser::find(1);
        $this->post = ImmutablePost::find(1);
        $this->tag1 = ImmutableTag::find(1);
        $this->tag2 = ImmutableTag::find(2);
    }

    public function test_belongs_to_many_lazy_load(): void
    {
        $tags = $this->post->tags;

        $this->assertInstanceOf(EloquentCollection::class, $tags);
        $this->assertCount(2, $tags);
        $this->assertInstanceOf(ImmutableTag::class, $tags->first());
    }

    public function test_belongs_to_many_returns_pivot_data(): void
    {
        $tags = $this->post->tags;
        $firstTag = $tags->first();

        $this->assertTrue($this->post->relationLoaded('tags'));

        // Check pivot is attached
        $pivot = $firstTag->pivot;
        $this->assertInstanceOf(ImmutablePivot::class, $pivot);
        $this->assertEquals(1, $pivot->order);
        $this->assertNotNull($pivot->created_at);
    }

    public function test_belongs_to_many_eager_load(): void
    {
        $posts = ImmutablePost::with('tags')->get();

        $this->assertCount(1, $posts);
        $this->assertTrue($posts->first()->relationLoaded('tags'));
        $this->assertCount(2, $posts->first()->tags);
    }

    public function test_belongs_to_many_eager_load_with_constraints(): void
    {
        $posts = ImmutablePost::with(['tags' => fn($q) => $q->where('name', 'PHP')])->get();

        $this->assertCount(1, $posts);
        $this->assertCount(1, $posts->first()->tags);
        $this->assertEquals('PHP', $posts->first()->tags->first()->name);
    }

    public function test_belongs_to_many_returns_empty_collection_when_no_relations(): void
    {
        // Create a post without tags
        DB::table('posts')->insert([
            'id' => 2,
            'user_id' => 1,
            'title' => 'Post Without Tags',
            'body' => 'Test body',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post = ImmutablePost::find(2);
        $tags = $post->tags;

        $this->assertInstanceOf(EloquentCollection::class, $tags);
        $this->assertCount(0, $tags);
    }

    public function test_belongs_to_many_inverse_direction(): void
    {
        // Tag -> Posts (inverse)
        $posts = $this->tag1->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(1, $posts);
        $this->assertInstanceOf(ImmutablePost::class, $posts->first());
    }

    public function test_belongs_to_many_relation_method_returns_builder(): void
    {
        $relation = $this->post->tags();

        // Should allow query methods
        $count = $relation->count();
        $this->assertEquals(2, $count);
    }

    public function test_belongs_to_many_blocks_attach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->tags()->attach(3);
    }

    public function test_belongs_to_many_blocks_detach(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->tags()->detach(1);
    }

    public function test_belongs_to_many_blocks_sync(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->tags()->sync([1, 2, 3]);
    }

    public function test_belongs_to_many_blocks_toggle(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->post->tags()->toggle([1]);
    }

    public function test_pivot_is_immutable(): void
    {
        $tag = $this->post->tags->first();
        $pivot = $tag->pivot;

        $this->expectException(ImmutableModelViolationException::class);

        $pivot->order = 5;
    }

    public function test_pivot_array_access_is_read_only(): void
    {
        $tag = $this->post->tags->first();
        $pivot = $tag->pivot;

        // Read should work
        $this->assertEquals(1, $pivot['order']);

        // Write should throw
        $this->expectException(ImmutableModelViolationException::class);
        $pivot['order'] = 5;
    }

    public function test_belongs_to_many_with_custom_pivot_accessor(): void
    {
        // Create a model with custom pivot accessor
        $post = new class extends ImmutablePost {
            public function customTags()
            {
                return $this->belongsToMany(ImmutableTag::class, 'post_tag', 'post_id', 'tag_id')
                    ->as('link')
                    ->withPivot('order');
            }
        };

        // Hydrate from existing data
        $post = $post::find(1);
        $tags = $post->customTags;

        $firstTag = $tags->first();
        $this->assertInstanceOf(ImmutablePivot::class, $firstTag->link);
        $this->assertEquals(1, $firstTag->link->order);
    }
}
