<br>
@php
    $checked = $columnValue ? true : false;
    $id = $data->id;
    $options = json_decode($columnInfo->details);
    $columnName = $columnInfo->field;
@endphp

@if(isset($options->on) && isset($options->off))
    <input type="checkbox" data-id="{{ $id }}" name="{{ $columnName }}" class="toggleswitch"
           data-on="{{ $options->on }}" @if($checked) checked="checked" @endif
           data-off="{{ $options->off }}">
@else
    <input type="checkbox" data-id="{{ $id }}" name="{{ $columnName }}" class="toggleswitch"
           @if($checked) checked="checked" @endif
    >
@endif