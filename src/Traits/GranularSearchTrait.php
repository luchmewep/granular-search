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
 * * This trait can be used by controller classes to use the Granular Search algorithm.
 * * Granular Search's goal is to make model filtering/searching easier with just one line code.
 *
 * * Most of the time, some keys inside the $request are also column names of the table associated with the $model.
 * * Also, most of the search algorithm created before has a repetitive pattern: $model->where($key, $value).
 * * To save time and apply DRY principle, this trait was born.
 *
 * * By design, this trait will ONLY process the $request keys that are parts of table column names of the $model.
 * * Since the $model is both an input and an output, the $model can be subjected to more filtering before/after using granular-search.
 * * Since a $request key can have an array as value, whereIn and whereInLike are also introduced in this algorithm.
 * * The output will be a Query Builder which can be executed using 'get()'.
 *
 * @author James Carlo S. Luchavez (carlo.luchavez@fourello.com)
 */
trait GranularSearchTrait
{
    /**
     * @param Request|array $request Contains all the information regarding the HTTP request
     * @param Model|Builder $model Model or query builder that will be subjected to searching/filtering
     * @param string $table_name Database table name associated with the $model
     * @param array $excluded_keys Request keys or table column names to be excluded from $request
     * @param array $like_keys Request keys or table column names to be search with LIKE
     * @param string $prepend_key
     * @param bool $is_recursive
     * @return Model|Builder|array
     */
    public static function getGranularSearch($request, $model, string $table_name, array $excluded_keys = [], array $like_keys = [], $prepend_key = '', $is_recursive = FALSE)
    {
        self::validateTableName($table_name);
        self::validateExcludedKeys($excluded_keys);

        $data = self::prepareData($request, $excluded_keys, $prepend_key, $is_recursive);
        $request_keys = array_keys($data);

        if(empty($data)) {
            return $model;
        }

        $table_keys = self::prepareTableKeys($table_name, $excluded_keys);

        self::validateLikeKeys($like_keys, $table_name);

        if(Arr::isFilled($data, 'q')) {
            $like_keys = array_values(array_intersect($table_keys, $like_keys));
            $exact_keys = array_values(array_diff($table_keys, $like_keys));
        }
        else {
            $like_keys = array_values(array_intersect($like_keys, $table_keys, $request_keys));
            $exact_keys = (array_diff($request_keys, $like_keys));
            $exact_keys = array_values(array_intersect($table_keys, $exact_keys));
        }

        $model = $model->where(function ($query) use ($data, $like_keys, $exact_keys) {
            // If $like_keys is a non-empty array, proceed with searching by LIKE
            if (empty($like_keys) === FALSE) {
                // If 'q' is present and is filled, proceed with all-column search
                if(Arr::isFilled($data, 'q')){
                    $search = $data['q'];
                    foreach ($like_keys as $col) {
                        if(is_array($search)){
                            $query = $query->orWhere(function ($q) use ($search, $col) {
                                foreach ($search as $s) {
                                    $q->orWhere($col, 'LIKE', '%' . $s . '%');
                                }
                            });
                        }else{
                            $query = $query->orWhere($col, 'LIKE', '%' . $search . '%');
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
                                        $q->orWhere($col, 'LIKE', '%' . $d . '%');
                                    }
                                });
                            } else {
                                $query = $query->where($col, 'LIKE', '%' . $data[$col] . '%');
                            }
                        }
                    }
                }
            }

            // Proceed with EQUAL search
            if(Arr::isFilled($data, 'q')){
                $search = $data['q'];
                foreach ($exact_keys as $col) {
                    if(is_array($search)){
                        $query = $query->orWhereIn($col, $search);
                    }else{
                        $query = $query->orWhere($col, $search);
                    }
                }
            }
            else{
                foreach ($exact_keys as $col) {
                    if (Arr::isFilled($data, $col)) {
                        if (is_array($data[$col])) {
                            $query = $query->whereIn($col, $data[$col]);
                        } else {
                            $query = $query->where($col, $data[$col]);
                        }
                    }
                }
            }
        });

        // Proceed with sorting
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
            if(is_array($desc)){
                foreach ($desc as $d) {
                    if(Schema::hasColumn($table_name, $d)){
                        $model = $model->orderBy($d, 'desc');
                    }
                }
            }else if(Schema::hasColumn($table_name, $desc)) {
                $model = $model->orderBy($desc, 'desc');
            }
        }

        return $model;
    }

    // Methods

    /**
     * Get associative array from $request variable.
     *
     * @param Request|array $request
     * @param array|null $excluded_keys
     * @param string $prepend_key
     * @param bool $is_recursive
     * @return array
     */
    private static function prepareData($request, $excluded_keys = [], $prepend_key = '', $is_recursive = FALSE): array
    {
        if(is_array($request) && (empty($request) || Arr::isAssoc($request))){
            Arr::forget($request, $excluded_keys);
            return self::extractPrependedArrayKeys($request, $prepend_key, $is_recursive);
        }
        if(is_subclass_of($request, Request::class)){
            $request = $request->except($excluded_keys);
            return self::extractPrependedArrayKeys($request, $prepend_key, $is_recursive);
        }
        throw new RuntimeException('The request variable must be array or an instance of Illuminate/Http/Request.');
    }

    /**
     * @param $data
     * @param string $prepend_key
     * @param bool $is_recursive
     * @return array
     */
    public static function extractPrependedArrayKeys($data, $prepend_key = '', $is_recursive = FALSE): array
    {
        if(empty($prepend_key) || ($is_recursive && Arr::isFilled($data,'q'))){
            return $data;
        }

        if(empty($data) === FALSE && Arr::isAssoc($data) === FALSE){
            throw new RuntimeException('The data variable must be an associative array.');
        }


        $result = [];
        $prepend = $prepend_key . '_';
        foreach ($data as $key=>$value){
            if(Str::startsWith($key, $prepend)){
                $result[Str::after($key, $prepend)] = $value;
            }
        }

        return $result;
    }

    private static function prepareTableKeys(string $table_name, ?array $excluded_keys = []): array
    {
        return array_values(array_diff(Schema::getColumnListing($table_name), $excluded_keys));
    }

    /**
     * Validate $table_name.
     *
     * @param string $table_name
     */
    private static function validateTableName(string $table_name): void
    {
        if(Schema::hasTable($table_name) === FALSE){
            throw new RuntimeException('Table name provided does not exist in database.');
        }
    }

    /**
     * Validate $excluded_keys.
     *
     * @param array $excluded_keys
     */
    private static function validateExcludedKeys(array $excluded_keys): void
    {
        if(is_array($excluded_keys) === FALSE){
            throw new RuntimeException('Only arrays are allowed for $excluded_keys');
        }
        if(Arr::isAssoc($excluded_keys)){
            throw new RuntimeException('Associative array not allowed for $excluded_keys. Provide sequential array instead.');
        }
    }

    /**
     * Validate $like_keys with respect to specified database table.
     *
     * @param array $like_keys
     * @param string $table_name
     */
    private static function validateLikeKeys(array $like_keys, string $table_name): void
    {
        if(is_array($like_keys) === FALSE){
            throw new RuntimeException('Only arrays are allowed for $like_keys');
        }

        if(Arr::isAssoc($like_keys)){
            throw new RuntimeException('Associative arrays not allowed for $like_keys. Provide sequential array instead.');
        }

        foreach ($like_keys as $key){
            if(Schema::hasColumn($table_name, $key) === FALSE){
                throw new RuntimeException('An element of $like_keys is not found on specified database table.');
            }
        }
    }

    /**
     * @param Request|array $request
     */
    public static function validateRequest($request): void
    {
        if((is_array($request) && Arr::isAssoc($request) === FALSE) || is_subclass_of($request, Request::class) === FALSE){
            throw new RuntimeException('The request variable must be array or an instance of Illuminate/Http/Request.');
        }
    }

    /**
     * @param Request|array $request
     * @param string $key
     * @return bool
     */
    public static function requestArrayHas($request, $key = ''): bool
    {
        if(is_array($request) && (empty($request) || Arr::isAssoc($request))){
            return Arr::has($request, $key);
        }
        else if(is_subclass_of($request, Request::class)){
            return $request->has($key);
        }
        return false;
    }
}
