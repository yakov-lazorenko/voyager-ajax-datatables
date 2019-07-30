@php
    $primaryKey = isset($data->primaryKey) ? $data->primaryKey : $data->id;
    $dataTypeDetails = json_decode($dataType->details);
@endphp
@if (Voyager::can('delete_'.$dataType->name))
    <a
        href="javascript:;"
        title="{{ __('voyager.generic.delete') }}"
        class="btn btn-sm btn-danger pull-right delete"
        data-id="{{ $primaryKey }}"
        id="delete-{{ $primaryKey }}"
    >
        <i class="voyager-trash"></i>
        @if(config('voyager.views.browse.display_text_on_service_buttons'))
            <span class="hidden-xs hidden-sm">{{ __('voyager.generic.delete')}}</span>
        @endif
    </a>
@endif
@if (Voyager::can('edit_'.$dataType->name))
    <a href="{{ route('voyager.'.$dataType->slug.'.edit', $primaryKey) }}" title="{{ __('voyager.generic.edit') }}" class="btn btn-sm btn-primary pull-right edit">
        <i class="voyager-edit"></i>
        @if(config('voyager.views.browse.display_text_on_service_buttons'))
            <span class="hidden-xs hidden-sm">{{ __('voyager.generic.edit')}}</span>
        @endif
    </a>
@endif

@if (Voyager::can('read_'.$dataType->name))
    @if(isset($dataTypeDetails->buttons->read))
    <a href="{{ isset($dataTypeDetails->buttons->read->attribute) && $data->{$dataTypeDetails->buttons->read->attribute} ? $data->{$dataTypeDetails->buttons->read->attribute} : route('voyager.'.$dataType->slug.'.show', $primaryKey) }}" title="{{ __('voyager.generic.view') }}" target="_blank" class="btn btn-sm btn-warning pull-right">
        <i class="voyager-eye"></i>
        @if(config('voyager.views.browse.display_text_on_service_buttons'))
            <span class="hidden-xs hidden-sm">{{ __('voyager.generic.view')}}</span>
        @endif
    </a>
    @else
    <a href="{{ route('voyager.'.$dataType->slug.'.show', $primaryKey) }}" title="{{ __('voyager.generic.view') }}" class="btn btn-sm btn-warning pull-right">
        <i class="voyager-eye"></i>
        @if(config('voyager.views.browse.display_text_on_service_buttons'))
            <span class="hidden-xs hidden-sm">{{ __('voyager.generic.view')}}</span>
        @endif
    </a>
    @endif
@endif

@if (Voyager::can('read_'.$dataType->name))
    @if(isset($dataTypeDetails->buttons->shortcode))
        <a data-shortcode='[{{ $dataTypeDetails->buttons->shortcode->name  }} id="{{$data->id}}"]'
           data-title="{{ $dataTypeDetails->buttons->shortcode->title }}"
           title="{{ __('Скопировать код для вставки блока "' . $dataTypeDetails->buttons->shortcode->title . '"') }}"
           class="btn btn-sm btn-dark pull-right copy">
            <i class="voyager-documentation"></i>
            @if(config('voyager.views.browse.display_text_on_service_buttons'))
                <span class="hidden-xs hidden-sm">{{ __('voyager.generic.short_code')}}</span>
            @endif
        </a>
    @endif
@endif