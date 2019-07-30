<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Facades\Voyager;
use Yajra\Datatables\Facades\Datatables;

// This class generates ajax http response and html-code for displaying 
// bread ajax list by means 'datatables' plugin.
class BreadAjaxList
{
    // language code (for example: 'en', 'ru', 'uk') for
    // multilanguage data representation
    public $locale;

    // object stores all info about model (dataType, tables, columns, details)
    public $modelInfo = null;

    // related models
    // array: key = [column name], value = [model info]
    public $relatedModelsInfo = null;

    // 'datatables' plugin class object
    public $datatables = null;

    // if true - show 'actions' buttons
    public $showActions = true;


    function __construct($slug = null, $locale = null)
    {
        if ($slug == null){
            return;
        }

        if (!$locale){
            $locale = \App::getLocale();
            if (empty($locale)){
                $locale = 'ru';
            }
        }

        $this->locale = $locale;
        $this->modelInfo = $this->loadModelInfo($slug, $locale);
        $this->loadRelatedModelsInfo();
    }



    public function getAjaxResponse()
    {
        $this->configureDatatable();
        return $this->getDatatables()->make(true);
    }



    public function getModelInfo()
    {
        return $this->modelInfo;
    }



    public function loadModelInfo($slug, $locale = null)
    {
        if (!$locale){
            $locale = $this->locale;
        }

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
        if (!$dataType){
            return null;
        }

        // $columnsInfo - all columns info (with translated columns)
        $columnsInfo = $dataType->browseRows;
        $model = app($dataType->model_name);
        if (!$model){
            return null;
        }

        $modelName = $dataType->model_name;

        $tableColumnsNames = [];
        foreach ($dataType->browseRows as $columnInfo) {
            $tableColumnsNames[] = $columnInfo->field;
        }

        $translationModelName = null;
        $translationModel = null;
        $dataTypeTranslation = null;
        $translationTableColumnsNames = null;
        $isModelTranslatable = false;
        $translationForeignKey = null;

        if (isset($model['translatable'])){
            $isModelTranslatable = true;

            $translationModelName = $model->getTranslationModelName();
            if (!$translationModelName){
                return null;
            }

            $translationModel = app($translationModelName);
            if (!$translationModel){
                return null;
            }

            $translationForeignKey = $model->getRelationKey();

            $dataTypeTranslation = Voyager::model('DataType')
                ->where('model_name', '=', $translationModelName)->first();
            if (!$dataTypeTranslation){
                return null;
            }

            $columnsInfo = $dataType->browseRows->merge($dataTypeTranslation->browseRows);

            $translationTableColumnsNames = [];
            foreach ($dataTypeTranslation->browseRows as $columnInfo) {
                $translationTableColumnsNames[] = $columnInfo->field;
            }
        }

        $customViewColumns = $this->getCustomViewColumns($columnsInfo);
        $customViewColumnsNames =
            $customViewColumns ? array_keys($customViewColumns) : null;

        $customDataColumns = $this->getCustomDataColumns($columnsInfo);
        $customDataColumnsNames =
            $customDataColumns ? array_keys($customDataColumns) : null;

        $props = [
            'slug', 'locale', 'isModelTranslatable', 'columnsInfo',
            'modelName', 'translationModelName',
            'model', 'translationModel', 'translationForeignKey',
            'dataType', 'dataTypeTranslation',
            'tableColumnsNames', 'translationTableColumnsNames',
            'customDataColumns', 'customDataColumnsNames', 
            'customViewColumns', 'customViewColumnsNames'
        ];

        $info = new \stdClass;

        foreach ($props as $property) {
            $info->{$property} = $$property;
        }

        $info->table = with($model)->getTable();
        $info->translationTable = isset($model['translatable']) ? with($translationModel)->getTable() : null;
        $info->datatableColumnsData = $this->getDatatableColumnsData($columnsInfo);
        $info->datatableOrderSettings = $this->getDatatableOrderSettings($columnsInfo);

        return $info;
    }



    public function configureDatatable()
    {
        // Build query for 'Datatables' plugin (yajra-datatables-oracle)
        $query = $this->buildDatatableQuery();

        // Init 'Datatables' plugin
        $this->datatables = Datatables::of($query);

        $this->configureDatatableSearch();

        $this->configureCustomViewColumns();
    }



    public function buildDatatableQuery()
    {
        $query = DB::table($this->modelInfo->table);

        if ($this->modelInfo->isModelTranslatable){
            $query = $this->addJoinForTranslationTable($query);
        }

        foreach ($this->modelInfo->columnsInfo as $columnInfo) {
            if ($this->isCustomDataColumn($columnInfo->field)){
                $query = $this->addSelectForCustomDataColumn($query, $columnInfo);
                $query = $this->addJoinForCustomDataColumn($query, $columnInfo);
            } else {
                $query = $this->addSelectForQuery($query, $columnInfo);
            }
        }

        if (\Schema::hasColumn($this->modelInfo->table, 'deleted_at')){
            $query->whereNull("{$this->modelInfo->table}.deleted_at");
        }

        return $query;
    }



    public function configureDatatableSearch()
    {
        $search = isset(request('search')['value'])
            ? trim(request('search')['value']) : '';

        if (strlen($search) < 2){
            $this->datatables->filter(function ($query) use($search){});
            return;
        }

        $this->datatables->filter(
            function ($query) use($search){
                $query->where(function($q) use($search){
                //------------------------------------//
                    $first = true;

                    foreach ($this->modelInfo->columnsInfo as $columnInfo) {
                        if (!$this->isColumnSearchable($columnInfo)){
                            continue;
                        }
                        if ($columnInfo->type == "timestamp" && 
                            !preg_match("#^[0-9\s:-]+$#", $search)
                        ){
                            continue;
                        }
                        $q = $this->addWhereForSearch($columnInfo, $q, $search, $first);
                        if ($first) $first = false;
                    }
                //-----------------------------------//
                });
            });
    }



    public function configureCustomViewColumns()
    {
        foreach ($this->modelInfo->columnsInfo as $columnInfo) {
            if ($this->isCustomViewColumn($columnInfo->field)){
                $this->datatables->editColumn($columnInfo->field,
                    function ($data) use($columnInfo) {
                        return $this->renderCustomViewColumn($data, $columnInfo);
                    });
            }
        }

        $rawColumns = [];

        if ($this->modelInfo->customViewColumnsNames){
            $rawColumns = $this->modelInfo->customViewColumnsNames;
        }

        // configure actions column
        if ($this->showActions){
            $this->datatables->addColumn('actions', function ($data){
                $dataType = $this->modelInfo->dataType;
                $data = app($dataType->model_name)::find($data->id);
                $view = 'voyager::bread-ajax-list.actions';
                if (view()->exists("voyager::{$dataType->slug}.bread-ajax-list.actions")) {
                    $view = "voyager::{$dataType->slug}.bread-ajax-list.actions";
                }
                return view($view, compact('data', 'dataType'));
            });
            $rawColumns[] = 'actions';
        }

        if (!empty($rawColumns)){
            $this->datatables->rawColumns($rawColumns);
        }
    }



    public function getCustomDataColumns($columnsInfo)
    {
        $customDataColumns = [];

        foreach ($columnsInfo as $columnInfo) {
            if ($this->getColumnDetail('bread-ajax-list.data', $columnInfo)){
                $customDataColumns[$columnInfo->field] = $columnInfo;
            }
        }

        if (empty($customDataColumns)){
            return null;
        }

        return $customDataColumns;
    }



    public function getCustomViewColumns($columnsInfo)
    {
        $customViewColumns = [];

        foreach ($columnsInfo as $columnInfo) {
            if ($this->getColumnDetail('bread-ajax-list.view', $columnInfo)){
                $customViewColumns[$columnInfo->field] = $columnInfo;
            }
        }

        if (empty($customViewColumns)){
            return null;
        }

        return $customViewColumns;
    }



    public function getRelatedModelsInfo()
    {
        return $this->relatedModelsInfo;
    }



    public function loadRelatedModelsInfo()
    {
        $info = null;

        if (empty($this->modelInfo->customDataColumns)){
            $this->relatedModelsInfo = null;
            return;
        }

        foreach ($this->modelInfo->customDataColumns as $columnInfo) {

            $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);
            $slug = $this->getColumnDetail('bread-ajax-list.data.slug', $columnInfo);

            if ($type == 'category-full-name'){
               $slug = $slug ? : 'categories';
            }

            if (!$slug){
                $this->relatedModelsInfo = null;
                return;
            }

            $info[ $columnInfo->field ] = $this->loadModelInfo($slug, $this->locale);
        }

        $this->relatedModelsInfo = $info;
    }



    public function getDatatables()
    {
        return $this->datatables;
    }



    public function getDatatableColumnsData($columnsInfo)
    {
        $data = [];

        foreach($columnsInfo as $index => $columnInfo) {

            $options = [ 'data' => $columnInfo->field, 'name' => $columnInfo->field ];

            if (!$this->isColumnOrderable($columnInfo)){
                $options = array_merge( $options,
                    ['orderable' => false, 'className' => 'no-sort no-click'] );
            }

            if (!$this->isColumnSearchable($columnInfo)){
                $options = array_merge( $options, ['searchable' => false]);
            }

            $data[] = $options;
        }

        if ($this->showActions){
            $data[] = ['data' => 'actions', 'name' => 'actions',
                'orderable' => false, 'searchable' => false,
                'className' => 'no-sort no-click'];
        }

        return $data;
    }



    public function getDatatableOrderSettings($columnsInfo)
    {
        $settings = [];

        foreach($columnsInfo as $index => $columnInfo) {
            if ($details = $this->getDataTypeDetails($columnInfo)) {

                if ( $dir = $this->getColumnDetail(
                    'bread-ajax-list.order-default', $columnInfo) ) {
                    if (in_array( strtolower($dir), ['asc', 'desc'])) {
                        $settings[] = [$index, $dir];
                    }
                }

            }
        }

        return $settings;
    }



    public function addSelectForQuery($query, $columnInfo)
    {
        $columnName = $columnInfo->field;

        if ($this->isCustomDataColumn($columnName)){
            return $this->addSelectForCustomDataColumn($query, $columnInfo);
        }

        if ($this->isTranslatableColumn($columnName)){
            $table = $this->modelInfo->translationTable;
        } else {
            $table = $this->modelInfo->table;
        }

        $query->addSelect( "$table.$columnName as $columnName" );

        return $query;
    }



    public function addSelectForCustomDataColumn($query, $columnInfo)
    {
        $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);

        if ($type == 'category-full-name'){
            return $this->addSelectForCategoryColumn($query, $columnInfo);
        }

        if (!$slug = $this->getColumnDetail('bread-ajax-list.data.slug', $columnInfo)){
            return $query;
        }

        if (!$relatedColumnName = $this->getColumnDetail('bread-ajax-list.data.column', $columnInfo)){
            return $query;
        }

        $columnName = $columnInfo->field;
        $related_table_alias = $this->getRelatedTableAlias($columnInfo);

        $query->addSelect( "$related_table_alias.$relatedColumnName AS $columnName" );

        return $query;
    }



    public function addSelectForCategoryColumn($query, $columnInfo)
    {
        $columnName = $columnInfo->field;
        $related_table_alias = 'category_translations_' . $columnName;
        $query->addSelect(DB::raw(
            "CONCAT(`parent_category_translations`.`title`, ' / ', `$related_table_alias`.`title`) as `$columnName`"
        ));

        return $query;
    }



    public function addJoinForTranslationTable($query)
    {
        $table = $this->modelInfo->table;
        $translation_table = $this->modelInfo->translationTable;
        $foreign_key = $this->modelInfo->translationForeignKey;
        $local_key = 'id';

        $query->leftJoin($translation_table,
            function ($join) use($translation_table, $foreign_key, $table, $local_key) {
                $join->on("$translation_table.$foreign_key", '=', "$table.$local_key")
                     ->where("$translation_table.locale", '=', $this->locale);
            });

        return $query;
    }



    public function addJoinForCustomDataColumn($query, $columnInfo)
    {
        $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);

        if ($type == 'category-full-name'){
            return $this->addJoinForCategoryColumn($query, $columnInfo);
        }

        if (!$relatedColumnName = $this->getColumnDetail('bread-ajax-list.data.column', $columnInfo)){
            return $query;
        }

        $columnName = $columnInfo->field;

        $foreign_key = $this->getColumnDetail('bread-ajax-list.data.foreign-key', $columnInfo) ? : 'id';
        $local_key =
            $this->getColumnDetail('bread-ajax-list.data.local-key', $columnInfo) ? : $columnName;

        if (!isset($this->relatedModelsInfo[$columnName])){
            return $query;
        }

        $relatedModelInfo = $this->relatedModelsInfo[$columnName];

        $table = $this->isTranslatableColumn($columnName) ?
            $this->modelInfo->translationTable : $this->modelInfo->table;
        $related_table = $relatedModelInfo->table;
        $related_table_alias = $related_table . '_' . $columnName;

        // join related table
        $query->leftJoin("$related_table as $related_table_alias",
            "$related_table_alias.$foreign_key", '=', "$table.$local_key");

        // Is related model translatable?
        if ($relatedModelInfo->isModelTranslatable){
            $translation_related_table = $relatedModelInfo->translationTable;
            $translation_related_table_alias = $translation_related_table . '_' . $columnName;
            $foreign_key = $relatedModelInfo->translationForeignKey;
            $local_key = 'id';

            //join translation table
            $query->leftJoin("$translation_related_table as $translation_related_table_alias",
                function ($join) use($translation_related_table_alias, $foreign_key, $related_table_alias, $local_key) {
                    $join->on("$translation_related_table_alias.$foreign_key", '=', "$related_table_alias.$local_key")
                        ->where("$translation_related_table_alias.locale", '=', $this->locale);
                });
        }

        return $query;
    }



    public function addJoinForCategoryColumn($query, $columnInfo)
    {
        $table = $this->modelInfo->table;
        $columnName = $columnInfo->field;
        $categories_table_alias = 'categories_' . $columnName;
        $category_translations_table_alias = 'category_translations_' . $columnName;

        $query->leftJoin("categories as $categories_table_alias", "$categories_table_alias.id", '=', "$table.$columnName");

        $query->leftJoin(
            "category_translations as $category_translations_table_alias",
            function ($join) use($category_translations_table_alias, $categories_table_alias) {
                $join->on("$category_translations_table_alias.category_id", '=', "$categories_table_alias.id")
                     ->where("$category_translations_table_alias.locale", '=', $this->locale);
            });

        $query->leftJoin("categories as parent_categories",
            "parent_categories.id", '=', "$categories_table_alias.parent_id");

        $query->leftJoin(
            'category_translations as parent_category_translations',
            function ($join) {
                $join->on('parent_category_translations.category_id', '=', 'parent_categories.id')
                     ->where('parent_category_translations.locale', '=', $this->locale);
            });

        return $query;
    }



    public function addWhereForSearch($columnInfo, $query, $search, $first)
    {
        $columnName = $columnInfo->field;

        if (!$this->isCustomDataColumn($columnName)){
            $table = $this->isTranslatableColumn($columnName) ?
                $this->modelInfo->translationTable : $this->modelInfo->table;
            $sql = "$table.$columnName";
            if ($first){
                $query->where($sql, 'like', "%$search%");
            } else {
                $query->orWhere($sql, 'like', "%$search%");
            }
            return $query;
        }

        return $this->addWhereForSearchInCustomDataColumn($columnInfo, $query, $search, $first);
    }



    public function addWhereForSearchInCustomDataColumn($columnInfo, $query, $search, $first)
    {
        $columnName = $columnInfo->field;
        $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);

        if ($type == 'category-full-name'){
            $related_table_alias = 'category_translations_' . $columnName;
            $sql = "CONCAT(`parent_category_translations`.`title`, ' / ', `$related_table_alias`.`title`) like ?";
            if ($first){
                $query->whereRaw($sql, "%$search%");
            } else {
                $query->orWhereRaw($sql, "%$search%");
            }
            return $query;
        }

        if (!$relatedColumnName = $this->getColumnDetail('bread-ajax-list.data.column', $columnInfo)){
            return $query;
        }

        $related_table_alias = $this->getRelatedTableAlias($columnInfo);
        $sql = "$related_table_alias.$relatedColumnName";

        if ($first){
            $query->where($sql, 'like', "%$search%");
        } else {
            $query->orWhere($sql, 'like', "%$search%");
        }

        return $query;
    }



    public function renderCustomViewColumn($data, $columnInfo)
    {
        $type = $this->getColumnDetail('bread-ajax-list.view.type', $columnInfo);
        $image = $this->getColumnDetail('bread-ajax-list.view.image', $columnInfo);
        $css_style = $this->getColumnDetail('bread-ajax-list.view.css-style', $columnInfo);
        $template = $this->getColumnDetail('bread-ajax-list.view.template', $columnInfo);
        $column_value = $data->{$columnInfo->field};
        $params = [];

        if ($css_style){
            $params['css_style'] = $css_style;
        }

        $params['columnValue'] = $column_value;

        if ($image){
            $model = app($this->modelInfo->dataType->model_name)::find($data->id);
            $params['imageUrl'] = asset('storage/' . $column_value);

            if (isset($image['attribute'])){
                $params['imageUrl'] = $model->{$image['attribute']};
            } elseif ($model && method_exists($model, 'getCroppedPhoto') &&
                isset($image['prefix'], $image['suffix'])){
                $url = $model->getCroppedPhoto($image['prefix'], $image['suffix']);
                if (file_exists( public_path($url) )){
                    $params['imageUrl'] = asset_timed($url);
                }
            }

            return view('voyager::bread-ajax-list.image', $params);
        }

        // template - полный путь к шаблону, например, 'voyager::bread-ajax-list.bold-text'
        if ($template){
            if ( !view()->exists($template) ){
                return $column_value;
            }
            $params['data'] = $data;
            $params['columnInfo'] = $columnInfo;
            $params['modelInfo'] = $this->modelInfo;
            return view($template, $params);
        }

        if ($css_style){
            return "<div style=\"$css_style\">$column_value</div>";
        }

        return $column_value;
    }



    public function getCustomDataColumnRelatedTable($columnInfo)
    {
        $columnName = $columnInfo->field;
        $modelInfo = $this->relatedModelsInfo[$columnName];

        if (!$modelInfo->isModelTranslatable) {
            return $modelInfo->table;
        }

        $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);

        if ($type == 'category-full-name'){
            return 'category_translations';
        }

        if (!$relatedColumnName = $this->getColumnDetail('bread-ajax-list.data.column', $columnInfo)){
            return null;
        }

        return in_array( $relatedColumnName, $modelInfo->translationTableColumnsNames ) ?
            $modelInfo->translationTable : $modelInfo->table;
    }



    public function getRelatedTableAlias($columnInfo)
    {
        $related_table = $this->getCustomDataColumnRelatedTable($columnInfo);
        return $related_table . '_' . $columnInfo->field;
    }



    public function getRelatedColumn($columnInfo)
    {
        $type = $this->getColumnDetail('bread-ajax-list.data.type', $columnInfo);

        if ($type == 'category-full-name'){
            return 'title';
        }

        return $this->getColumnDetail('bread-ajax-list.data.column', $columnInfo);
    }



    public function isCustomDataColumn($columnName)
    {
        if (!$this->modelInfo->customDataColumnsNames) return false;
        return in_array( $columnName, $this->modelInfo->customDataColumnsNames );
    }



    public function isCustomViewColumn($columnName)
    {
        if (!$this->modelInfo->customViewColumnsNames) return false;
        return in_array( $columnName, $this->modelInfo->customViewColumnsNames );
    }



    public function isTranslatableColumn($columnName)
    {
        if (!$this->modelInfo->translationTableColumnsNames) return false;
        return in_array( $columnName, $this->modelInfo->translationTableColumnsNames );
    }


}
