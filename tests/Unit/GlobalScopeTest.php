<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Brighten\ImmutableModel\Tests\Models\ScopedModel;
use Brighten\ImmutableModel\Tests\TestCase;

class GlobalScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        ScopedModel::setConnectionResolver($this->app['db']);
        ScopedModel::$globalScopes = [];
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'settings' => null, 'email_verified_at' => '2024-01-01 00:00:00', 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-02 00:00:00', 'updated_at' => '2024-01-02 00:00:00'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'settings' => null, 'email_verified_at' => '2024-01-03 00:00:00', 'created_at' => '2024-01-03 00:00:00', 'updated_at' => '2024-01-03 00:00:00'],
        ]);
    }

    public function test_global_scope_is_applied(): void
    {
        ScopedModel::$globalScopes = [VerifiedUserScope::class];

        $users = ScopedModel::query()->get();

        // Should only return verified users (Alice and Charlie)
        $this->assertCount(2, $users);
    }

    public function test_multiple_global_scopes_are_applied(): void
    {
        ScopedModel::$globalScopes = [
            VerifiedUserScope::class,
            RecentUserScope::class,
        ];

        $users = ScopedModel::query()->get();

        // Only Charlie is both verified and recent (created after Jan 2)
        $this->assertCount(1, $users);
        $this->assertEquals('Charlie', $users->first()->name);
    }

    public function test_without_global_scopes_removes_all(): void
    {
        ScopedModel::$globalScopes = [VerifiedUserScope::class];

        $users = ScopedModel::withoutGlobalScopes()->get();

        // Should return all 3 users
        $this->assertCount(3, $users);
    }

    public function test_without_global_scope_removes_specific(): void
    {
        ScopedModel::$globalScopes = [
            VerifiedUserScope::class,
            RecentUserScope::class,
        ];

        // Remove only the verified scope
        $users = ScopedModel::withoutGlobalScope(VerifiedUserScope::class)->get();

        // Bob and Charlie are recent (created Jan 2 or later)
        $this->assertCount(2, $users);
    }

    public function test_get_global_scopes_returns_registered_scopes(): void
    {
        ScopedModel::$globalScopes = [
            VerifiedUserScope::class,
            RecentUserScope::class,
        ];

        $scopes = ScopedModel::getGlobalScopes();

        $this->assertContains(VerifiedUserScope::class, $scopes);
        $this->assertContains(RecentUserScope::class, $scopes);
    }
}

/**
 * Test scope that filters to verified users only.
 */
class VerifiedUserScope implements ImmutableModelScope
{
    public function apply(ImmutableQueryBuilder $builder): void
    {
        $builder->whereNotNull('email_verified_at');
    }
}

/**
 * Test scope that filters to recently created users.
 */
class RecentUserScope implements ImmutableModelScope
{
    public function apply(ImmutableQueryBuilder $builder): void
    {
        $builder->where('created_at', '>=', '2024-01-02 00:00:00');
    }
}
