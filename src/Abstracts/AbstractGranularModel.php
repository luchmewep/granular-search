<?php


namespace Luchmewep\GranularSearch\Abstracts;


use Illuminate\Database\Eloquent\Model;
use Luchmewep\GranularSearch\Traits\GranularSearchableTrait;

abstract class AbstractGranularModel extends Model
{
    use GranularSearchableTrait;
}
