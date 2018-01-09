<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use Illuminate\Database\MySqlConnection;

class ManyBelongsTo extends Relation
{
    use SupportsDefaultModels;

    /**
     * The child model instance of the relation.
     */
    protected $child;

    /**
     * The foreign keys of the parent model.
     *
     * @var string
     */
    protected $foreignKeys;

    /**
     * The associated key on the parent model.
     *
     * @var string
     */
    protected $ownerKey;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $child
     * @param  string  $foreignKeys
     * @param  string  $ownerKey
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $child, $foreignKeys, $ownerKey, $relation)
    {
        if (! $child->getConnection() instanceof MySqlConnection) {
            throw new \RuntimeException( 'Relation '.class_basename(static::class).' is only support for MySQL.' );
        }

        $this->ownerKey = $ownerKey;
        $this->relation = $relation;
        $this->foreignKeys = $foreignKeys;

        // In the underlying base relationship class, this variable is referred to as
        // the "parent" since most relationships are not inversed. But, since this
        // one is we will create a "child" variable for much better readability.
        $this->child = $child;

        parent::__construct($query, $child);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $results = parent::get($columns)->keyBy($this->ownerKey);

        return (new Collection(array_map(function ($key) use ($results) {

            return $results->get($key, null);

        }, explode(',', $this->child->{$this->foreignKeys}))))->filter();
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        $results= $this->query->get()->keyBy($this->ownerKey);

        if (! $results) {
            return new Collection($this->getDefaultFor($this->parent));
        }

        $parentChain = $this->child->{$this->foreignKeys};

        $parentChain = $parentChain ? explode(',', $parentChain) : [];

        return new Collection(array_map(function ($key) use ($results) {

            return $results->get($key, null);

        }, $parentChain));
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign keys that's on a parent.
            $table = $this->related->getTable();

            $this->query->whereIn($table.'.'.$this->ownerKey, explode(',', $this->child->{$this->foreignKeys}));
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->related->getTable().'.'.$this->ownerKey;

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    /**
     * Gather the keys from an array of related models.
     *
     * @param  array  $models
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = [];

        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            if (! is_null($value = $model->{$this->foreignKeys})) {
                $keys[] = explode(',', $value);
            }
        }

        // If there are no keys that were not null we will just return an array with null
        // so this query wont fail plus returns zero results, which should be what the
        // developer expects to happen in this situation. Otherwise we'll sort them.
        if (count($keys) === 0) {
            return [null];
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection($this->getDefaultFor($model)));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreigns = $this->foreignKeys;

        $owner = $this->ownerKey;

        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getAttribute($owner)] = $result;
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            $relatedModels= new Collection;

            foreach (explode(',', $model->{$foreigns}) as $foreign) {
                if (isset($dictionary[$foreign])) {
                    $relatedModels->push($dictionary[$foreign]);
                }
            }

            $model->setRelation($relation, $relatedModels);
        }

        return $models;
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Illuminate\Database\Eloquent\Model|int|string  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate($models)
    {
        if ($models instanceof Model) {
            $ownerKey= $models->getAttribute($this->ownerKey);
        } elseif ($models instanceof Collection) {
            $ownerKey= $models->implode($this->ownerKey, ',');
        } elseif (is_array($models)) {
            $ownerKey= implode(',', $models);
        } else {
            $ownerKey= $models;
        }

        $this->child->setAttribute($this->foreignKeys, $ownerKey);

        if ($models instanceof Model) {
            $this->child->setRelation($this->relation, new Collection($models));
        } elseif ($models instanceof Collection) {
            $this->child->setRelation($this->relation, $models);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dissociate()
    {
        $this->child->setAttribute($this->foreignKeys, null);

        return $this->child->setRelation($this->relation, null);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return $query->select($columns)->whereRaw(
            'FIND_IN_SET(`'.$query->getModel()->getTable().'`.`'.$this->ownerKey.'`, `'.$this->child->getTable().'`.`'.$this->foreignKeys.'`)'
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->select($columns)->from(
            '`'.$query->getModel()->getTable().'` as `'.($hash = $this->getRelationCountHash()).'`'
        );

        $query->getModel()->setTable($hash);

        return $query->whereRaw(
            'FIND_IN_SET(`'.$hash.'`.`'.$query->getModel()->getKeyName().'`, `'.$this->child->getTable().'`.`'.$this->foreignKeys.'`)'
        );
    }

    /**
     * Get a relationship join table hash.
     *
     * @return string
     */
    public function getRelationCountHash()
    {
        return 'laravel_reserved_'.static::$selfJoinCount++;
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance();
    }

    /**
     * Get the foreign keys of the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKeys;
    }

    /**
     * Get the fully qualified foreign keys of the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKey()
    {
        return $this->child->getTable().'.'.$this->foreignKeys;
    }

    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function getOwnerKey()
    {
        return $this->ownerKey;
    }

    /**
     * Get the fully qualified associated key of the relationship.
     *
     * @return string
     */
    public function getQualifiedOwnerKeyName()
    {
        return $this->related->getTable().'.'.$this->ownerKey;
    }

    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function getRelation()
    {
        return $this->relation;
    }
}
