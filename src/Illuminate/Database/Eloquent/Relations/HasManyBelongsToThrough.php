<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class HasManyBelongsToThrough extends HasManyThrough
{

    /**
     * Get the qualified foreign key on the parent model.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->parent->getTable().'.'.$this->secondKey;
    }

    /**
     * Get the fully qualified related key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->related->getTable().'.'.$this->secondLocalKey;
    }

}
