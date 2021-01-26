<?php

namespace App\Http\Traits;

use Illuminate\Support\Carbon;

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
trait GranularTimeSearchTrait
{
    public function getGranularTimeSearch($request, $model, ?string $time_column = 'created_at')
    {
        $data = $request->only(['date', 'date_from', 'date_to', 'datetime_from', 'datetime_to']);
        if ($request->has('date')) {
            $model = $model->whereDate($time_column, (new Carbon($data['date']))->toDateString());
        } elseif ($request->has(['date_from', 'date_to'])) {
            $model = $model->whereBetween($time_column, [(new Carbon($data['date_from']))->startOfDay(), (new Carbon($data['date_to']))->endOfDay()]);
        } elseif ($request->has(['datetime_from', 'datetime_to'])) {
            $model = $model->whereBetween($time_column, [$data['datetime_from'], $data['datetime_to']]);
        }
        return $model;
    }
}
