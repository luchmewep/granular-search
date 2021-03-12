<?php


namespace Luchmewep\GranularSearch\Abstracts;

use Illuminate\Foundation\Auth\User;
use Luchmewep\GranularSearch\Traits\GranularSearchableTrait;

abstract class AbstractUserGranularModel extends User
{
    use GranularSearchableTrait;
}
