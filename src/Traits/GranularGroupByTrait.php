<?php

namespace Luchmewep\GranularSearch\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * This trait can be used by controller classes to use the Granular Date Search algorithm.
 * * Granular Date Search's goal is to make model filtering/searching by date easier with just one line code.
 *
 * * By design, this trait will ONLY process the $request keys that are also part of column names of the $model table.
 * * Since the $model variable is both an input and an output, the $model variable can be subjected to more filtering before/after using granularDateSearch.
 * * The output will be a Query Builder which can be executed using 'get()'.
 *
 * * The parameters are as follows:
 * * $request - Request. It contains all the information regarding the HTTP request.
 * * $model - Model|Query Builder. The model or query builder that will be subjected to searching/filtering.
 * * $time_column - string (default: 'created_at'). The table column with date type to be used for filtering.
 *
 * * Expected $request params and use:
 * * 'date' - Search all rows within a specific date.
 * * 'date_from' & 'date_to' - Seach all rows between two dates.
 * * 'datetime_from' & 'datetime_to' - Seach all rows between two datetimes.
 *
 * @author James Carlo S. Luchavez (james.luchavez@fourello.com)
 */
trait GranularGroupByTrait
{
    public function getGranularGroupBy($model, string $group_by, string $table_name, ?array $excluded_keys = null, ?string $addAggregateSelect = null)
    {
        $table_keys = Schema::getColumnListing($table_name);
        if (!is_null($excluded_keys)) $table_keys = $table_keys->except($excluded_keys);

        if (in_array($group_by, $table_keys)) {
            $model = $model->selectRaw("{$group_by}, COUNT(*) as count")->groupByRaw($group_by);
            if (!is_null($addAggregateSelect)) $model = $model->selectRaw($addAggregateSelect);
        }

        return $model;
    }
}
