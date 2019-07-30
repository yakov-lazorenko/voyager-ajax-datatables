<?php

namespace TCG\Voyager\Http\Controllers;

use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadAjaxListTrait;

class TranslationBreadAjaxController extends TranslationBreadController
{
    use BreadAjaxListTrait;
}
