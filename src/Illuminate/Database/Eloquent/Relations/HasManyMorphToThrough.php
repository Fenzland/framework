<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class HasManyMorphToThrough extends MorphManyBelongsToThrough
{
    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $farParent
     * @param  \Illuminate\Database\Eloquent\Model  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondType
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $throughParent, $firstKey, $secondType, $secondKey, $localKey, $secondLocalKey)
    {
        $this->morphClass = $query->getModel()->getMorphClass();

        parent::__construct($query, $farParent, $throughParent, $secondType, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Get the qualified foreign key type on the related model.
     *
     * @return string
     */
    public function getQualifiedFirstKeyType()
    {
        return $this->throughParent->getTable().'.'.$this->morphType;
    }
}
