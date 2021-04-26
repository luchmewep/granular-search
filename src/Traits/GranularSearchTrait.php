<?php

namespace Luchmewep\GranularSearch\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * This trait can be used by controller classes to use the Granular Search algorithm.
 * Granular Search's goal is to make model filtering/searching easier with just one line code.
 *
 * Most of the time, some keys inside the $request are also column names of the table associated with the $model.
 * Also, most of the search algorithm created before has a repetitive pattern: $model->where($key, $value).
 * To save time and apply DRY principle, this trait was born.
 *
 * By design, this trait will ONLY process the $request keys that are parts of table column names of the $model.
 * Since the $model is both an input and an output, the $model can be subjected to more filtering before/after using granular-search.
 * Since a $request key can have an array as value, whereIn and whereInLike are also introduced in this algorithm.
 * The output will be a Query Builder which can be executed using 'get()'.
 *
 * @author James Carlo S. Luchavez (carlo.luchavez@fourello.com)
 */
trait GranularSearchTrait
{
    protected static $q_alias = 'q';

    /**
     * Filter the model collection using the contents of the $request variable.
     *
     * @param Request|array $request Contains all the information regarding the HTTP request
     * @param Model|Builder $model Model or query builder that will be subjected to searching/filtering
     * @param string $table_name Database table name associated with the $model
     * @param array $excluded_keys Request keys or table column names to be excluded from $request
     * @param array $like_keys Request keys or table column names to be search with LIKE
     * @param string $prepend_key
     * @param bool $ignore_q
     * @param bool $force_or
     * @return Model|Builder
     */
    public static function getGranularSearch($request, $model, string $table_name, array $excluded_keys = [], array $like_keys = [], string $prepend_key = '', bool $ignore_q = false, bool $force_or = false)
    {
        self::validateRequest($request);
        self::validateTableName($table_name);
        self::validateExcludedKeys($excluded_keys);

        // Always convert $request to Associative Array
        if (is_subclass_of($request, Request::class)) {
            $request = $request->all();
        }

        $data = self::prepareData($request, $excluded_keys, $prepend_key, $ignore_q);
        $request_keys = array_keys($data);

        if(empty($data)) {
            return $model;
        }

        $accept_q = !$ignore_q && Arr::isFilled($data, self::$q_alias);

        $table_keys = static::prepareTableKeys($table_name, $excluded_keys);

        $like_keys = array_values(array_intersect($like_keys, $table_keys));

        if($accept_q) {
            $exact_keys = array_values(array_diff($table_keys, $like_keys));
        }
        else {
            $like_keys = array_values(array_intersect($request_keys, $like_keys));
            $exact_keys = array_values(array_intersect($request_keys, $table_keys));
            $exact_keys = array_values(array_diff($exact_keys, $like_keys));
        }

        $model = $model->where(function ($query) use ($force_or, $accept_q, $data, $like_keys, $exact_keys) {
            // 'LIKE' SEARCHING
            if (empty($like_keys) === false) {
                // If 'q' is present and is filled, proceed with all-column search
                if($accept_q){
                    $search = $data[self::$q_alias];
                    foreach ($like_keys as $col) {
                        $value = Arr::get($data, $col, $search);
                        if(is_array($value)){
                            $query = $query->orWhere(function ($q) use ($value, $col) {
                                foreach ($value as $s) {
                                    $q->orWhere($col, 'LIKE', self::getLikeString($s));
                                }
                            });
                        }else{
                            $query = $query->orWhere($col, 'LIKE', self::getLikeString($value));
                        }
                    }
                }

                // If 'q' is not present, proceed with column-specific search
                else {
                    foreach ($like_keys as $col) {
                        if (Arr::isFilled($data, $col)) {
                            if (is_array($data[$col])) {
                                $query = $query->where(function ($q) use ($data, $col) {
                                    foreach ($data[$col] as $d) {
                                        $q->orWhere($col, 'LIKE', self::getLikeString($d));
                                    }
                                });
                            } else {
                                $query = $query->where($col, 'LIKE', self::getLikeString($data[$col]));
                            }
                        }
                    }
                }
            }

            // 'EXACT' SEARCHING
            if($accept_q){
                $search = $data[self::$q_alias];
                foreach ($exact_keys as $col) {
                    $value = Arr::get($data, $col, $search);
                    if(is_array($value)){
                        $query = $query->orWhereIn($col, $value);
                    }else{
                        $query = $query->orWhere($col, $value);
                    }
                }
            }
            else{
                foreach ($exact_keys as $col) {
                    if (Arr::isFilled($data, $col)) {
                        if (is_array($data[$col])) {
                            $query = $force_or ? $query->orWhereIn($col, $data[$col]) : $query->whereIn($col, $data[$col]);
                        } else {
                            $query = $force_or ? $query->orWhere($col, $data[$col]) : $query->where($col, $data[$col]);
                        }
                    }
                }
            }
        });

        // SORTING
        if(Arr::isFilled($data, 'sortBy'))
        {
            $asc = $data['sortBy'];
            if(is_array($asc)){
                foreach ($asc as $a) {
                    if(Schema::hasColumn($table_name, $a)){
                        $model = $model->orderBy($a);
                    }
                }
            }
            else if(Schema::hasColumn($table_name, $asc)) {
                $model = $model->orderBy($asc);
            }
        }

        else if(Arr::isFilled($data, 'sortByDesc')){
            $desc = $data['sortByDesc'];
            if(is_array($desc)) {
                foreach ($desc as $d) {
                    if(Schema::hasColumn($table_name, $d)){
                        $model = $model->orderBy($d, 'desc');
                    }
                }
            } else if(Schema::hasColumn($table_name, $desc)) {
                $model = $model->orderBy($desc, 'desc');
            }
        }

        return $model;
    }

    // Methods

    /**
     * Get a processed associative array from $request variable.
     *
     * @param array $request
     * @param array|null $excluded_keys
     * @param string $prepend_key
     * @param bool $ignore_q
     * @return array
     */
    private static function prepareData(array $request, $excluded_keys = [], $prepend_key = '', $ignore_q = false): array
    {
        if(is_array($request) && (empty($request) || Arr::isAssoc($request)))
        {
            Arr::forget($request, $excluded_keys);
            return static::extractPrependedKeys($request, $prepend_key, $ignore_q);
        }
        throw new RuntimeException('$request variable must be an associative array.');
    }

    /**
     * Remove the prepend string from the prepended request keys.
     *
     * @param Request|array $data
     * @param string $prepend_key
     * @param bool $ignore_q
     * @return array
     */
    public static function extractPrependedKeys($data, string $prepend_key = '', bool $ignore_q = false): array
    {
        if(is_subclass_of($data, Request::class)) {
            $data = $data->all();
        }

        if(empty($prepend_key)) {
            return $data;
        }

        if(empty($data) === false && Arr::isAssoc($data) === false) {
            throw new RuntimeException('$data must be an associative array.');
        }

        $result = [];
        $prepend = $prepend_key . '_';

        foreach ($data as $key=>$value) {
            if(empty($value)) {
                continue;
            }
            if(Str::startsWith($key, $prepend)){
                $result[Str::after($key, $prepend)] = $value;
            }
            else if ($key === self::$q_alias && $ignore_q === false){
                $result[self::$q_alias] = $value;
            }
        }

        return $result;
    }

    private static function prepareTableKeys(string $table_name, ?array $excluded_keys = []): array
    {
        return array_values(array_diff(Schema::getColumnListing($table_name), $excluded_keys));
    }

    /**
     * Validate if the $table_name is an actual database table.
     *
     * @param string $table_name
     */
    private static function validateTableName(string $table_name): void
    {
        if(Schema::hasTable($table_name) === false){
            throw new RuntimeException('Table name provided does not exist in database.');
        }
    }

    /**
     * Validate $excluded_keys if it is an associative array.
     *
     * @param array $excluded_keys
     */
    private static function validateExcludedKeys(array $excluded_keys): void
    {
        if(Arr::isAssoc($excluded_keys)){
            throw new RuntimeException('$excluded_keys must be a sequential array, not an associative one.');
        }
    }

    /**
     * Determine if the $request is either a Request instance/subclass or an associative array.
     *
     * @param Request|array $request
     */
    public static function validateRequest($request): void
    {
        if((is_array($request) && empty($request) === false && Arr::isAssoc($request) === false) && is_subclass_of($request, Request::class) === false){
            throw new RuntimeException('$request must be an array or an instance/subclass of Illuminate/Http/Request.');
        }
    }

    public static function getLikeString(string $str){
        $result = '%';
        foreach (str_split($str) as $s){
            $result .= $s . '%';
        }
        return $result;
    }

    /**
     * Check if the Request or associative array has a specific key.
     *
     * @param Request|array $request
     * @param string $key
     * @param bool|null $is_exact
     * @return bool
     */
    public static function requestOrArrayHas($request, $key = '', ?bool $is_exact = true): bool
    {
        if(is_array($request) && (empty($request) || Arr::isAssoc($request))){
            if($is_exact){
                return Arr::has($request, $key);
            }else{
                return (bool) preg_grep("/$key/", array_keys($request));
            }
        }
        else if(is_subclass_of($request, Request::class)){
            if($is_exact){
                return $request->has($key);
            }else{
                return (bool) preg_grep("/$key/", $request->keys());
            }
        }
        return false;
    }

    /**
     * @param Request|array $request
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function requestOrArrayGet($request, string $key, $default = null) {
        if(static::requestOrArrayHas($request, $key)) {
            if(is_array($request)) {
                return $request[$key];
            } else {
                return $request->$key;
            }
        }
        return $default;
    }

    /**
     * @param Request|array $request
     * @param string $key
     * @return bool
     */
    public static function isRequestOrArrayFilled($request, string $key): bool
    {
        if(static::requestOrArrayHas($request, $key)){
            if(is_array($request)) {
                return Arr::isFilled($request, $key);
            } else {
                return $request->filled($key);
            }
        }
        return false;
    }

    /**
     * @param Request|array $request
     * @return bool
     */
    public static function hasQ($request) {
        return static::isRequestOrArrayFilled($request, static::$q_alias, true);
    }

    /**
     * Checks if a table has been queried already.
     *
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @param $table
     * @return bool
     */
    public static function isTableHasQueried($query, $table): bool
    {
        $wheres = [];

        if($query instanceof Builder){
            $wheres = $query->getQuery()->wheres;
        } elseif($query instanceof \Illuminate\Database\Query\Builder) {
            $wheres = $query->wheres;
        }

        foreach ($wheres as $where) {
            if (isset($where['query']) && ($where['query']->from === $table || self::isTableHasQueried($where['query'], $table))) {
                return true;
            }
        }

        return false;
    }
}
