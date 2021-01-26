<?php

namespace Luchmewep\GranularSearch\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
trait GranularGroupByTimeTrait
{
    public function getGranularGroupByTime($request, $model, string $time_type, ?string $time_column = 'created_at', ?string $addAggregateSelect = null)
    {
        $value = 1;
        if ($request->has('value')) {
            $value = intval($request->value);
        }

        $groupByTimeString = $this->getGroupByTime($time_type, $value, $time_column);
        $model = $model->selectRaw('COUNT(*) as time_count')->selectRaw($groupByTimeString)->groupByRaw('timestamp');
        if (!is_null($addAggregateSelect)) $model = $model->selectRaw($addAggregateSelect);
        return $model;
    }

    public function getGroupByTime(string $time_type, int $value = 1, string $time_column = "created_at"): string
    {
        $value = $this->convertTimeToSeconds($time_type, $value);
        return 'FLOOR(UNIX_TIMESTAMP(' . $time_column . ')/' . $value . ')*' . $value . ' as timestamp';
    }

    public function convertTimeToSeconds($time_type, $value)
    {
        switch ($time_type) {
            case 'minute':
                return $value * 60;
            case 'hour':
                // return $value * 60 * 60;
                return $value * 3600;
            case 'day':
                // return $value * 60 * 60 * 24;
                return $value * 86400;
            case 'week':
                // return $value * 60 * 60 * 24 * 7;
                return $value * 604800;
            case 'month':
                // return $value * 60 * 60 * 24 * 7 * 4;
                return $value * 2419200;
            case 'quarter':
                // return $value * 60 * 60 * 24 * 7 * 4 * 3;
                return $value * 7257600;
        }
    }
}
