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
 * @method Builder ofRelation(string $relation_name, ?string $prepend_key = '', ?bool $is_recursive = FALSE)
 * @method Builder ofRelations(?array $relations = [], ?array $excluded_relations =  [], ?bool $is_recursive = FALSE)
 * @method Builder granularSearch($request, ?string $prepend_key = '', $is_recursive = FALSE)
 * @method Builder granularSearchWithRelations(Request $request)
 */

trait GranularSearchableTrait
{
    use GranularSearchTrait;

    protected static $granular_excluded_keys = [];
    protected static $granular_like_keys = [];
    protected static $granular_relations = [];
    protected static $request;

    /**
     * Query scope for the Eloquent model to filter via single related model.
     *
     * @param Builder $query
     * @param string $relation_name
     * @param string|null $prepend_key
     * @param bool $is_recursive
     * @return Builder
     */
    public function scopeOfRelation(Builder $query, string $relation_name, ?string $prepend_key = '', $is_recursive = FALSE): Builder
    {
        $this->validateRelation($relation_name);
        if(static::requestArrayHas(static::$request, 'q')){
            return $query->orWhereHas($relation_name, function ($q) use ($prepend_key, $relation_name, $is_recursive) {
                $q->granularSearch(static::$request, empty($prepend_key) ? Str::snake(Str::singular($relation_name)) : $prepend_key, $is_recursive);
            });
        }
        else{
            return $query->whereHas($relation_name, function ($q) use ($prepend_key, $relation_name, $is_recursive) {
                $q->granularSearch(static::$request, empty($prepend_key) ? Str::snake(Str::singular($relation_name)) : $prepend_key, $is_recursive);
            });
        }
    }

    /**
     * Query scope for the Eloquent model to filter via multiple related models.
     *
     * @param Builder $query
     * @param array|null $relations
     * @param array|null $excluded_relations
     * @param bool $is_recursive
     * @return Builder
     */

    public function scopeOfRelations(Builder $query, ?array $relations = [], ?array $excluded_relations =  [], $is_recursive = FALSE): Builder
    {
        $relations = empty($relations) ? static::$granular_relations : $relations;
        $relations = empty($excluded_relations) ? $relations : array_values(array_diff($relations, $excluded_relations));
        foreach ($relations as $relation)
        {
            $query->ofRelation($relation, null, $is_recursive);
        }
        return $query;
    }

    /**
     * Query scope for the Eloquent model to filter via table-related request keys.
     *
     * @param Builder $query
     * @param Request|array $request
     * @param string|null $prepend_key
     * @param bool $is_recursive
     * @return Builder|Model
     */
    public function scopeGranularSearch(Builder $query, $request, ?string $prepend_key = '', $is_recursive = FALSE)
    {
        static::validateRequest($request);
        static::$request = $request;
        return $this->getGranularSearch($request, $query, static::getTableName(), static::$granular_excluded_keys, static::$granular_like_keys, $prepend_key, $is_recursive);
    }

    /**
     * Query scope for the Eloquent to filter via table-related requests keys and via related models.
     *
     * @param Builder $query
     * @param Request $request
     * @return mixed
     */
    public function scopeGranularSearchWithRelations(Builder $query, Request $request){
        return $query->granularSearch($request)->ofRelations($request->get('relations', []), $request->get('excluded_relations', []), $request->has('relations'));
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
