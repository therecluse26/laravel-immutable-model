<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use stdClass;

class HydrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'settings' => json_encode(['theme' => 'dark']),
                'email_verified_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
        ]);
    }

    public function test_can_hydrate_from_array(): void
    {
        $row = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'settings' => null,
            'email_verified_at' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $user = ImmutableUser::fromRow($row);

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
    }

    public function test_can_hydrate_from_stdclass(): void
    {
        $row = new stdClass();
        $row->id = 1;
        $row->name = 'Test User';
        $row->email = 'test@example.com';
        $row->settings = null;
        $row->email_verified_at = null;
        $row->created_at = '2024-01-01 00:00:00';
        $row->updated_at = '2024-01-01 00:00:00';

        $user = ImmutableUser::fromRow($row);

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('Test User', $user->name);
    }

    public function test_can_hydrate_collection_from_rows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
        ];

        $users = ImmutableUser::fromRows($rows);

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(2, $users);
        $this->assertEquals('User 1', $users[0]->name);
        $this->assertEquals('User 2', $users[1]->name);
    }

    public function test_can_hydrate_via_query_get(): void
    {
        $users = ImmutableUser::query()->get();

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(2, $users);
        $this->assertInstanceOf(ImmutableUser::class, $users[0]);
    }

    public function test_can_hydrate_via_query_first(): void
    {
        $user = ImmutableUser::query()->first();

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function test_can_hydrate_via_static_find(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function test_find_returns_null_for_missing(): void
    {
        $user = ImmutableUser::find(999);

        $this->assertNull($user);
    }

    public function test_can_hydrate_via_static_all(): void
    {
        $users = ImmutableUser::all();

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(2, $users);
    }

    public function test_raw_attributes_are_preserved(): void
    {
        $user = ImmutableUser::find(1);

        $this->assertEquals('John Doe', $user->getRawAttribute('name'));
        $this->assertEquals('john@example.com', $user->getRawAttribute('email'));
    }

    public function test_get_attributes_returns_all_raw_attributes(): void
    {
        $user = ImmutableUser::find(1);
        $attributes = $user->getAttributes();

        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('email', $attributes);
    }

}
