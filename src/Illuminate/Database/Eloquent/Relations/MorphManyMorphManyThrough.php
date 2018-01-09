<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MorphManyMorphManyThrough extends MorphManyHasManyThrough
{
    /**
     * The foreign key type for the relationship.
     *
     * @var string
     */
    protected $secondMorphType;

    /**
     * The class name of the parent model.
     *
     * @var string
     */
    protected $secondMorphClass;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $farParent
     * @param  \Illuminate\Database\Eloquent\Model  $throughParent
     * @param  string  $firstType
     * @param  string  $firstKey
     * @param  string  $secondType
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $throughParent, $firstType, $firstKey, $secondType, $secondKey, $localKey, $secondLocalKey)
    {
        $this->secondMorphType = $secondType;

        $this->secondMorphClass = $throughParent->getMorphClass();

        parent::__construct($query, $farParent, $throughParent, $firstType, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            parent::addConstraints();

            $this->query->where($this->getQualifiedSecondKeyType(), $this->secondMorphClass);
        }
    }

    /**
     * Get the qualified foreign key type on the "through" model.
     *
     * @return string
     */
    public function getQualifiedSecondKeyType()
    {
        return $this->related->getTable().'.'.$this->secondMorphType;
    }
}
