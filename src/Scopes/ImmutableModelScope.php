<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Scopes;

use Brighten\ImmutableModel\ImmutableQueryBuilder;

/**
 * Interface for global scopes on immutable models.
 *
 * Global scopes are applied to every query made against an immutable model.
 * Unlike Eloquent scopes, these are query-only and have no access to model
 * instances or lifecycle hooks.
 *
 * To use a global scope:
 * 1. Create a class implementing this interface
 * 2. Register it in your model's $globalScopes array
 *
 * Example:
 * ```
 * class TenantScope implements ImmutableModelScope
 * {
 *     public function apply(ImmutableQueryBuilder $builder): void
 *     {
 *         $builder->where('tenant_id', '=', auth()->user()->tenant_id);
 *     }
 * }
 *
 * abstract class TenantScopedModel extends ImmutableModel
 * {
 *     protected static array $globalScopes = [
 *         TenantScope::class,
 *     ];
 * }
 * ```
 */
interface ImmutableModelScope
{
    /**
     * Apply the scope to a given query builder.
     *
     * This method will be called automatically on every query made against
     * models that register this scope. The scope should add its query
     * constraints to the builder.
     *
     * @param ImmutableQueryBuilder $builder The query builder to modify
     */
    public function apply(ImmutableQueryBuilder $builder): void;
}
