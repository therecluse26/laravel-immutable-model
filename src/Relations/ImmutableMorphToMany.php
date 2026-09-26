<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Relations;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * MorphToMany relationship that builds immutable pivot models.
 *
 * Writes are blocked by ReadOnlyConnection like every other relation.
 * This class exists only because Laravel's MorphToMany::newPivot()
 * hard-codes MorphPivot instead of asking the related model.
 */
class ImmutableMorphToMany extends MorphToMany
{
    /**
     * Create a new pivot model instance.
     *
     * Uses ImmutableMorphPivot unless a custom pivot class is set with using().
     * A custom pivot class must extend ImmutableMorphPivot.
     * Delegates to Laravel's implementation so its pivot setup stays in sync.
     *
     * @param array $attributes
     * @param bool $exists
     * @return \Illuminate\Database\Eloquent\Relations\MorphPivot
     *
     * @throws ImmutableModelConfigurationException
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        if ($this->using) {
            if (! is_a($this->using, ImmutableMorphPivot::class, true)) {
                throw ImmutableModelConfigurationException::mutablePivot($this->using, ImmutableMorphPivot::class);
            }

            return parent::newPivot($attributes, $exists);
        }

        $this->using = ImmutableMorphPivot::class;

        try {
            return parent::newPivot($attributes, $exists);
        } finally {
            $this->using = null;
        }
    }
}
