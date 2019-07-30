<?php

namespace TCG\Voyager\Http\Controllers;

use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadAjaxListTrait;

class BreadAjaxController extends VoyagerBreadController
{
    use BreadAjaxListTrait;
}
