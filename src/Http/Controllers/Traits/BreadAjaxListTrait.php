<?php

namespace TCG\Voyager\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\BreadAjaxList;
use Yajra\Datatables\Facades\Datatables;

trait BreadAjaxListTrait
{
    //***************************************
    //               ____
    //              |  _ \
    //              | |_) |
    //              |  _ <
    //              | |_) |
    //              |____/
    //
    //      Browse our Data Type (B)READ
    //
    //****************************************

    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $this->getSlug($request);

        // Get model info (and translations)
        $breadAjaxList = new BreadAjaxList($slug);
        $modelInfo = $breadAjaxList->getModelInfo();

        // Check permission
        Voyager::canOrFail('browse_' . $modelInfo->dataType->name);

        $datatableColumnsData = json_encode($modelInfo->datatableColumnsData);

        $datatableOrderSettings = json_encode($modelInfo->datatableOrderSettings);

        $showActions = $breadAjaxList->showActions;

        $view = 'voyager::bread-ajax-list.browse';

        if (view()->exists("voyager::$slug.bread-ajax-list.browse")) {
            $view = "voyager::$slug.bread-ajax-list.browse";
        }

        return view($view, compact('modelInfo', 'datatableColumnsData',
            'datatableOrderSettings', 'showActions', 'breadAjaxList'));
    }



    // action - return AJAX response with json data for 'datatable' js plugin
    public function datatableAjax(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $request->slug;

        $locale = $request->locale;

        // Get all info about model, bread and translations
        $breadAjaxList = new BreadAjaxList($slug, $locale);
        $modelInfo = $breadAjaxList->getModelInfo();

        // Check permission
        Voyager::canOrFail('browse_' . $modelInfo->dataType->name);

        return $breadAjaxList->getAjaxResponse();
    }

}