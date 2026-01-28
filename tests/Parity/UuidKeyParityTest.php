<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentProduct;
use Brighten\ImmutableModel\Tests\Models\ImmutableProduct;
use Illuminate\Support\Str;

/**
 * Tests that UUID primary key behavior matches between Eloquent and ImmutableModel.
 */
class UuidKeyParityTest extends ParityTestCase
{
    private string $uuid1;
    private string $uuid2;
    private string $uuid3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductData();
    }

    protected function seedProductData(): void
    {
        $this->uuid1 = (string) Str::uuid();
        $this->uuid2 = (string) Str::uuid();
        $this->uuid3 = (string) Str::uuid();

        $this->app['db']->table('products')->insert([
            [
                'uuid' => $this->uuid1,
                'name' => 'Widget A',
                'price' => 19.99,
                'sku' => 'WIDGET-A',
                'metadata' => json_encode(['color' => 'red', 'size' => 'small']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => $this->uuid2,
                'name' => 'Widget B',
                'price' => 29.99,
                'sku' => 'WIDGET-B',
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => $this->uuid3,
                'name' => 'Widget C',
                'price' => 39.99,
                'sku' => 'WIDGET-C',
                'metadata' => json_encode(['color' => 'blue']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    // =========================================================================
    // FIND BY UUID
    // =========================================================================

    public function test_find_by_uuid(): void
    {
        $eloquent = EloquentProduct::find($this->uuid1);
        $immutable = ImmutableProduct::find($this->uuid1);

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_find_nonexistent_uuid(): void
    {
        $fakeUuid = (string) Str::uuid();

        $eloquent = EloquentProduct::find($fakeUuid);
        $immutable = ImmutableProduct::find($fakeUuid);

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_find_or_fail_by_uuid(): void
    {
        $eloquent = EloquentProduct::findOrFail($this->uuid1);
        $immutable = ImmutableProduct::findOrFail($this->uuid1);

        $this->assertModelParity($eloquent, $immutable);
    }

    // =========================================================================
    // KEY TYPE
    // =========================================================================

    public function test_key_type_is_string(): void
    {
        $eloquent = new EloquentProduct();
        $immutable = new ImmutableProduct();

        $this->assertEquals('string', $eloquent->getKeyType());
        $this->assertEquals('string', $immutable->getKeyType());
    }

    public function test_incrementing_is_false(): void
    {
        $eloquent = new EloquentProduct();
        // ImmutableModel doesn't have getIncrementing, but $incrementing property should be false
        $this->assertFalse($eloquent->getIncrementing());
    }

    // =========================================================================
    // QUERY WITH UUID
    // =========================================================================

    public function test_where_uuid(): void
    {
        $eloquent = EloquentProduct::where('uuid', $this->uuid1)->first();
        $immutable = ImmutableProduct::where('uuid', $this->uuid1)->first();

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_where_in_uuids(): void
    {
        $eloquent = EloquentProduct::whereIn('uuid', [$this->uuid1, $this->uuid2])
            ->orderBy('name')
            ->get();
        $immutable = ImmutableProduct::whereIn('uuid', [$this->uuid1, $this->uuid2])
            ->orderBy('name')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    public function test_uuid_preserved_in_to_array(): void
    {
        $eloquent = EloquentProduct::find($this->uuid1)->toArray();
        $immutable = ImmutableProduct::find($this->uuid1)->toArray();

        $this->assertEquals($eloquent['uuid'], $immutable['uuid']);
        $this->assertEquals($this->uuid1, $eloquent['uuid']);
        $this->assertEquals($this->uuid1, $immutable['uuid']);
    }

    public function test_get_key_returns_uuid(): void
    {
        $eloquent = EloquentProduct::find($this->uuid1);
        $immutable = ImmutableProduct::find($this->uuid1);

        $this->assertEquals($eloquent->getKey(), $immutable->getKey());
        $this->assertEquals($this->uuid1, $eloquent->getKey());
        $this->assertEquals($this->uuid1, $immutable->getKey());
    }

    // =========================================================================
    // ALL PRODUCTS
    // =========================================================================

    public function test_all(): void
    {
        $eloquent = EloquentProduct::orderBy('name')->get();
        $immutable = ImmutableProduct::query()->orderBy('name')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }
}
