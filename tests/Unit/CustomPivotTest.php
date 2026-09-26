<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Relations\ImmutableMorphPivot;
use Brighten\ImmutableModel\Relations\ImmutablePivot;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\Pivots\CustomImmutableMorphPivot;
use Brighten\ImmutableModel\Tests\Models\Pivots\CustomImmutablePivot;
use Brighten\ImmutableModel\Tests\Models\Pivots\CustomMutableMorphPivot;
use Brighten\ImmutableModel\Tests\Models\Pivots\CustomMutablePivot;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Tests for custom pivot classes set with using().
 *
 * A pivot model has its own connection, so a mutable pivot class could
 * write. Custom pivots on immutable relations must extend ImmutablePivot
 * or ImmutableMorphPivot; any other class throws a configuration error.
 */
class CustomPivotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);
        DB::table('posts')->insert(['id' => 1, 'user_id' => 1, 'title' => 'First', 'body' => 'Body']);
        DB::table('tags')->insert(['id' => 1, 'name' => 'PHP']);
        DB::table('post_tag')->insert(['post_id' => 1, 'tag_id' => 1, 'order' => 3]);
        DB::table('taggables')->insert(['tag_id' => 1, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1]);
    }

    // =========================================================================
    // BelongsToMany
    // =========================================================================

    public function test_custom_immutable_pivot_is_used_and_readable(): void
    {
        $pivot = ImmutablePost::findOrFail(1)->tagsWithCustomPivot->first()->pivot;

        $this->assertInstanceOf(CustomImmutablePivot::class, $pivot);
        $this->assertSame(3, (int) $pivot->order);
        $this->assertSame('order-3', $pivot->order_label);
    }

    public function test_custom_immutable_pivot_cannot_write(): void
    {
        $pivot = ImmutablePost::findOrFail(1)->tagsWithCustomPivot->first()->pivot;

        try {
            $pivot->newQuery()->delete();
            $this->fail('Expected ImmutableModelViolationException.');
        } catch (ImmutableModelViolationException) {
            $this->assertSame(1, DB::table('post_tag')->count());
        }
    }

    public function test_mutable_custom_pivot_throws_configuration_exception(): void
    {
        $this->expectException(ImmutableModelConfigurationException::class);
        $this->expectExceptionMessage(CustomMutablePivot::class);
        $this->expectExceptionMessage(ImmutablePivot::class);

        ImmutablePost::findOrFail(1)->tagsWithMutablePivot->first();
    }

    public function test_mutable_custom_pivot_throws_when_eager_loaded(): void
    {
        $this->expectException(ImmutableModelConfigurationException::class);

        ImmutablePost::with('tagsWithMutablePivot')->get();
    }

    // =========================================================================
    // MorphToMany
    // =========================================================================

    public function test_custom_immutable_morph_pivot_is_used_and_readable(): void
    {
        $pivot = ImmutablePost::findOrFail(1)->morphTagsWithCustomPivot->first()->pivot;

        $this->assertInstanceOf(CustomImmutableMorphPivot::class, $pivot);
        $this->assertSame(ImmutablePost::class, $pivot->taggable_type);
    }

    public function test_custom_immutable_morph_pivot_cannot_write(): void
    {
        $pivot = ImmutablePost::findOrFail(1)->morphTagsWithCustomPivot->first()->pivot;

        try {
            $pivot->newQuery()->delete();
            $this->fail('Expected ImmutableModelViolationException.');
        } catch (ImmutableModelViolationException) {
            $this->assertSame(1, DB::table('taggables')->count());
        }
    }

    public function test_mutable_custom_morph_pivot_throws_configuration_exception(): void
    {
        $this->expectException(ImmutableModelConfigurationException::class);
        $this->expectExceptionMessage(CustomMutableMorphPivot::class);
        $this->expectExceptionMessage(ImmutableMorphPivot::class);

        ImmutablePost::findOrFail(1)->morphTagsWithMutablePivot->first();
    }
}
