<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use BadMethodCallException;
use Brighten\ImmutableModel\ImmutableEloquentBuilder;
use Brighten\ImmutableModel\Tests\Models\ScopedModel;
use Brighten\ImmutableModel\Tests\TestCase;

class LocalScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        ScopedModel::setConnectionResolver($this->app['db']);
        // Clear any booted models to ensure clean slate for each test
        ScopedModel::clearBootedModels();
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'settings' => null, 'email_verified_at' => '2024-01-01 00:00:00', 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-02 00:00:00', 'updated_at' => '2024-01-02 00:00:00'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'settings' => null, 'email_verified_at' => '2024-01-03 00:00:00', 'created_at' => '2024-01-03 00:00:00', 'updated_at' => '2024-01-03 00:00:00'],
        ]);
    }

    // =========================================================================
    // hasNamedScope() TESTS
    // =========================================================================

    public function test_has_named_scope_returns_true_for_defined_scope(): void
    {
        $model = new ScopedModel();

        $this->assertTrue($model->hasNamedScope('verified'));
        $this->assertTrue($model->hasNamedScope('recent'));
        $this->assertTrue($model->hasNamedScope('nameLike'));
        $this->assertTrue($model->hasNamedScope('orderByName'));
    }

    public function test_has_named_scope_returns_false_for_undefined_scope(): void
    {
        $model = new ScopedModel();

        $this->assertFalse($model->hasNamedScope('nonexistent'));
        $this->assertFalse($model->hasNamedScope('active'));
        $this->assertFalse($model->hasNamedScope(''));
    }

    // =========================================================================
    // LOCAL SCOPE EXECUTION TESTS
    // =========================================================================

    public function test_local_scope_can_be_called(): void
    {
        $users = ScopedModel::query()->verified()->get();

        // Should only return verified users (Alice and Charlie)
        $this->assertCount(2, $users);
    }

    public function test_local_scope_modifies_query(): void
    {
        $builder = ScopedModel::query()->verified();

        $this->assertInstanceOf(ImmutableEloquentBuilder::class, $builder);

        // Verify the query was modified by checking results
        $users = $builder->get();
        $this->assertCount(2, $users);

        // All returned users should have email_verified_at set
        foreach ($users as $user) {
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function test_multiple_local_scopes_can_be_chained(): void
    {
        $users = ScopedModel::query()
            ->verified()
            ->orderByName('asc')
            ->get();

        $this->assertCount(2, $users);
        // Should be ordered: Alice, Charlie
        $this->assertEquals('Alice', $users->first()->name);
        $this->assertEquals('Charlie', $users->last()->name);
    }

    public function test_local_scope_with_parameters(): void
    {
        $users = ScopedModel::query()
            ->nameLike('A%')
            ->get();

        // Only Alice matches 'A%'
        $this->assertCount(1, $users);
        $this->assertEquals('Alice', $users->first()->name);
    }

    public function test_local_scope_with_default_parameter(): void
    {
        // The recent scope has a default parameter of 7 days
        // Since our test data is from 2024-01-01, none should be "recent"
        // relative to now(), but we can test that the method accepts no params
        $builder = ScopedModel::query()->recent();
        $this->assertInstanceOf(ImmutableEloquentBuilder::class, $builder);
    }

    public function test_local_scope_with_explicit_parameter(): void
    {
        // Pass explicit days parameter
        $builder = ScopedModel::query()->recent(30);
        $this->assertInstanceOf(ImmutableEloquentBuilder::class, $builder);
    }

    public function test_calling_undefined_scope_throws_exception(): void
    {
        $this->expectException(BadMethodCallException::class);

        ScopedModel::query()->nonexistentScope()->get();
    }

    public function test_local_scope_called_via_static_method(): void
    {
        // Scopes should be callable directly on the model class
        $users = ScopedModel::verified()->get();

        $this->assertCount(2, $users);
    }

    public function test_scope_returns_builder_for_chaining(): void
    {
        $builder = ScopedModel::query()->verified();

        $this->assertInstanceOf(ImmutableEloquentBuilder::class, $builder);

        // Should be able to continue chaining
        $builder2 = $builder->where('id', '>', 0);
        $this->assertInstanceOf(ImmutableEloquentBuilder::class, $builder2);
    }
}
