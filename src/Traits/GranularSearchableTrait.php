<?php


namespace Luchmewep\GranularSearch\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Trait GranularSearchableTrait
 * @package Luchmewep\GranularSearch\Traits
 *
 * @method Builder ofRelation(string $relation_name, string $key, $value)
 * @method Builder ofRelationViaRequest(string $relation_name, ?string $prepend_key = '')
 * @method Builder ofRelationsViaRequest(?array $relations = [])
 * @method Builder granularSearch($request, ?string $prepend_key = '',  $ignore_q = FALSE)
 * @method Builder granularSearchWithRelations($request)
 */

trait GranularSearchableTrait
{
    use GranularSearchTrait;

    protected static $granular_excluded_keys = [];
    protected static $granular_like_keys = [];
    protected static $granular_q_relations = [];
    protected static $request;

    /**
     * Query scope for the Eloquent model to filter via single related model.
     *
     * @param Builder $query
     * @param string $relation_name
     * @param string $key
     * @param mixed $value
     * @return Builder
     */
    public function scopeOfRelation(Builder $query, string $relation_name, string $key, $value): Builder
    {
        $this->validateRelation($relation_name);
        return $query->whereHas($relation_name, function ($q) use ($key, $value) {
            $q->granularSearch([$key => $value]);
        });
    }

    /**
     * Query scope for the Eloquent model to filter via single related model.
     *
     * @param Builder $query
     * @param string $relation_name
     * @param string|null $prepend_key
     * @return Builder
     */
    public function scopeOfRelationViaRequest(Builder $query, string $relation_name, ?string $prepend_key = ''): Builder
    {
        $this->validateRelation($relation_name);
        $prepend_key = empty($prepend_key) ? Str::snake(Str::singular($relation_name)) : $prepend_key;
        if(static::requestArrayHas(static::$request, 'q') && in_array($relation_name, static::$granular_q_relations, true) && static::requestArrayHas(static::$request, $prepend_key . '_', false) === FALSE) {
            return $query->orWhereHas($relation_name, function ($q) use ($prepend_key) {
                $q->granularSearch(static::$request, $prepend_key);
            });
        }
        else{
            return $query->whereHas($relation_name, function ($q) use ($prepend_key) {
                $q->granularSearch(static::$request, $prepend_key, TRUE);
            });
        }
    }

    /**
     * Query scope for the Eloquent model to filter via multiple related models.
     *
     * @param Builder $query
     * @param array|null $relations
     * @return Builder
     */

    public function scopeOfRelationsViaRequest(Builder $query, ?array $relations = []): Builder
    {
        $relations = empty($relations) ? static::$granular_q_relations : $relations;
        foreach ($relations as $relation)
        {
            $query->ofRelationViaRequest($relation, null);
        }
        return $query;
    }

    /**
     * Query scope for the Eloquent model to filter via table-related request keys.
     *
     * @param Builder $query
     * @param Request|array $request
     * @param string|null $prepend_key
     * @param bool $ignore_q
     * @return Builder|Model
     */
    public function scopeGranularSearch(Builder $query, $request, ?string $prepend_key = '',  $ignore_q = FALSE)
    {
        static::validateRequest($request);
        static::$request = $request;
        return $this->getGranularSearch($request, $query, static::getTableName(), static::$granular_excluded_keys, static::$granular_like_keys, $prepend_key, $ignore_q);
    }

    /**
     * Query scope for the Eloquent to filter via table-related requests keys and via related models.
     *
     * @param Builder $query
     * @param Request|array $request
     * @return mixed
     */
    public function scopeGranularSearchWithRelations(Builder $query, $request){
        return $query->granularSearch($request)->ofRelationsViaRequest(static::requestArrayGet($request, 'q_relations', []));
    }

    /**
     * Get table name of the model instance.
     *
     * @return mixed
     */
    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    // Other Methods

    /**
     * Determine if the class using the trait is a subclass of Eloquent Model.
     *
     * @return bool
     */
    public static function isModel(): bool
    {
        return is_subclass_of(static::class, Model::class);
    }

    /**
     * Check for the existence of a relation to an Eloquent model.
     *
     * @param string $relation
     * @return bool
     */
    public static function hasGranularRelation(string $relation): bool
    {
        try {
            if (method_exists(static::class, $relation)) {
                return ((new ReflectionClass(static::class))->newInstanceWithoutConstructor())->$relation() instanceof Relation;
            }
            else{
                return false;
            }
        }
        catch (\TypeError | ReflectionException $exception){
            return false;
        }
    }

    /**
     * Validate if the $relation really exists on the Eloquent model.
     *
     * @param string $relation
     */
    private function validateRelation(string $relation): void
    {
        if(static::hasGranularRelation($relation) === FALSE){
            throw new RuntimeException('The model does not have such relation: ' . $relation);
        }
    }
}
