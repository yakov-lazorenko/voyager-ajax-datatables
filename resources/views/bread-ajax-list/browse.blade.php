@extends('voyager::master')

@section('page_title', __('voyager.generic.viewing').' '.$modelInfo->dataType->display_name_plural)

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $modelInfo->dataType->icon }}"></i> {{ $modelInfo->dataType->display_name_plural }}
        @if (Voyager::can('add_'.$modelInfo->dataType->name))
            <a href="{{ route('voyager.'.$modelInfo->dataType->slug.'.create') }}" class="btn btn-success">
                <i class="voyager-plus"></i> {{ __('voyager.generic.add_new') }}
            </a>
        @endif
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body table-responsive">

                        <table id="dataTable" class="table table-hover">

                            <thead>
                                <tr>
                                    @foreach($modelInfo->columnsInfo as $column)
                                       <th>{{ $column->display_name }}</th>
                                    @endforeach
                                    @if ($showActions)
                                        <th class="actions">{{ __('voyager.generic.actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                           
                           <!-- table body inserted by ajax -->

                            <tfoot>
                                <tr>
                                    @foreach($modelInfo->columnsInfo as $column)
                                       <th>{{ $column->display_name }}</th>
                                    @endforeach
                                    @if ($showActions)
                                        <th class="actions">{{ __('voyager.generic.actions') }}</th>
                                    @endif
                                </tr>
                            </tfoot>

                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('voyager.generic.close') }}"><span
                                aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager.generic.delete_question') }} {{ strtolower($modelInfo->dataType->display_name_singular) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.'.$modelInfo->dataType->slug.'.index') }}" id="delete_form" method="POST">
                        {{ method_field("DELETE") }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm"
                                 value="{{ __('voyager.generic.delete_confirm') }} {{ strtolower($modelInfo->dataType->display_name_singular) }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">{{ __('voyager.generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@stop


@section('css')
    @if(config('voyager.dashboard.data_tables.responsive'))
        <link rel="stylesheet" href="{{ voyager_asset('plugins/dataTables/extensions/responsive/responsive.min.css') }}">
    @endif
@stop


@section('javascript')

    @if(config('voyager.dashboard.data_tables.responsive'))
        <script src="{{ voyager_asset('plugins/dataTables/extensions/responsive/responsive.min.js') }}"></script>

    @endif

    @if($modelInfo->isModelTranslatable)
        <script src="{{ voyager_asset('js/multilingual.js') }}"></script>
    @endif

    {{-- DataTables --}}
    <script>
        var currentLocale = "{{ \App::getLocale() }}";

        $(document).ready(function () {

            var list = $('#dataTable').DataTable({
                "order" : {!! $datatableOrderSettings !!},
                processing: true,
                serverSide: true,
                ajax: {
                  url : "{{ route('voyager.bread-ajax-list.datatable') }}",
                  type: 'GET',
                  data : {
                      slug : "{{ $modelInfo->dataType->slug }}",
                      locale : currentLocale
                  }
                },
                columns: {!! $datatableColumnsData !!},
                @if(config('voyager.dashboard.data_tables.responsive'))"responsive": true, @endif
            });


            $('#dataTable').on('draw.dt', function () {
                $('.toggleswitch').bootstrapToggle();
            });


            $('#dataTable').on('change', '.toggleswitch', function (e) {
                var data = {};
                var column = $(this).attr('name');
                var checked = +$(this).prop('checked')
                data[column] = checked;
                $.post('{{ route('voyager.api.update') }}', {
                    table_name: "{{ $modelInfo->table }}",
                    where: ['id', $(this).data('id')],
                    data: data,
                    dataType: 'json',
                    _token: '{{ csrf_token() }}'
                }).done(function() {
                    toastr.success("Данные успешно сохранены");
                }).fail(function(data, type, error) {
                    toastr.error(type, error);
                });
            });


            var deleteFormAction;

            $('#dataTable').on('click', '.delete', function (e) {
                var form = $('#delete_form')[0];

                if (!deleteFormAction) { // Save form action initial value
                    deleteFormAction = form.action;
                }

                form.action = deleteFormAction.match(/\/[0-9]+$/)
                    ? deleteFormAction.replace(/([0-9]+$)/, $(this).data('id'))
                    : deleteFormAction + '/' + $(this).data('id');

                $('#delete_modal').modal('show');
            });

            $('#dataTable').on('click', '.copy', function (e) {
                copyTextToClipboard($(this).data("shortcode"), $(this).data("title"));
            });

            function copyTextToClipboard(text, title) {
                var textCode = document.createElement("textarea");

                textCode.value = text;

                document.body.appendChild(textCode);

                textCode.select();

                try {
                    var successful = document.execCommand('copy');
                    var msg = successful ? 'successful' : 'unsuccessful';

                    if (msg === 'successful') {
                        toastr.success('Код для вставки блока "' + title + '" скопирован!', "Успех!");
                    } else {
                        toastr.error(res.message, "Неудача!");
                    }
                } catch (err) {
                    console.log('Oops, unable to copy');
                }

                document.body.removeChild(textCode);
            }

        });
    </script>

    @php
        $custom_js_view = "voyager::{$modelInfo->dataType->slug}.bread-ajax-list.javascript-custom";
    @endphp

    @if ( view()->exists($custom_js_view) )
        @include($custom_js_view)
    @endif

@stop
