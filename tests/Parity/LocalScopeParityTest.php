<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;

/**
 * Parity tests for local scopes.
 *
 * Ensures ImmutableModel local scopes produce identical results to Eloquent.
 */
class LocalScopeParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // BASIC LOCAL SCOPE PARITY
    // =========================================================================

    public function test_simple_scope_returns_same_results(): void
    {
        $this->assertQueryParity(
            fn () => EloquentUser::verified()->get(),
            fn () => ImmutableUser::verified()->get(),
            'Simple local scope should return identical results'
        );
    }

    public function test_scope_with_parameter_returns_same_results(): void
    {
        $this->assertQueryParity(
            fn () => EloquentUser::nameLike('A%')->get(),
            fn () => ImmutableUser::nameLike('A%')->get(),
            'Scope with parameter should return identical results'
        );
    }

    public function test_chained_scopes_return_same_results(): void
    {
        $this->assertQueryParity(
            fn () => EloquentUser::verified()->orderByName('asc')->get(),
            fn () => ImmutableUser::verified()->orderByName('asc')->get(),
            'Chained scopes should return identical results'
        );
    }

    public function test_scope_with_additional_where_returns_same_results(): void
    {
        $this->assertQueryParity(
            fn () => EloquentUser::verified()->where('id', '>', 1)->get(),
            fn () => ImmutableUser::verified()->where('id', '>', 1)->get(),
            'Scope combined with where clause should return identical results'
        );
    }

    // =========================================================================
    // SCOPE WITH AGGREGATES
    // =========================================================================

    public function test_scope_with_count_returns_same_results(): void
    {
        $eloquentCount = EloquentUser::verified()->count();
        $immutableCount = ImmutableUser::verified()->count();

        $this->assertEquals(
            $eloquentCount,
            $immutableCount,
            'Scope with count should return identical results'
        );
    }

    public function test_scope_with_first_returns_same_results(): void
    {
        $this->assertQueryParity(
            fn () => EloquentUser::verified()->orderByName()->first(),
            fn () => ImmutableUser::verified()->orderByName()->first(),
            'Scope with first() should return identical results'
        );
    }

    // =========================================================================
    // SCOPE ORDER OF APPLICATION
    // =========================================================================

    public function test_scope_order_does_not_affect_results(): void
    {
        // Apply scopes in different orders, should get same results
        $this->assertQueryParity(
            fn () => EloquentUser::orderByName('asc')->verified()->get(),
            fn () => ImmutableUser::orderByName('asc')->verified()->get(),
            'Scope order should not affect results'
        );
    }

    // =========================================================================
    // HAS NAMED SCOPE PARITY
    // =========================================================================

    public function test_has_named_scope_parity(): void
    {
        $eloquentUser = new EloquentUser();
        $immutableUser = new ImmutableUser();

        // Both should return true for defined scopes
        $this->assertEquals(
            $eloquentUser->hasNamedScope('verified'),
            $immutableUser->hasNamedScope('verified'),
            'hasNamedScope should return same value for defined scope'
        );

        // Both should return false for undefined scopes
        $this->assertEquals(
            $eloquentUser->hasNamedScope('nonexistent'),
            $immutableUser->hasNamedScope('nonexistent'),
            'hasNamedScope should return same value for undefined scope'
        );
    }
}
