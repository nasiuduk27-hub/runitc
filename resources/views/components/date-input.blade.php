@props([
    'name' => null,
    'value' => '',
    'id' => null,
    'min' => null,
    'max' => null,
    'placeholder' => 'dd/mm/yyyy',
])
@php
    // Komponen input tanggal DD/MM/YYYY: tampil dd/mm/yyyy, kirim Y-m-d via hidden.
    $displayId = $id ?: preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) ($name ?? 'date')));
    $hiddenId = $displayId.'_iso';
    $iso = substr(trim((string) $value), 0, 10);
    $display = '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        $display = $m[3].'/'.$m[2].'/'.$m[1];
    } else {
        $iso = '';
    }
@endphp
<input type="text" id="{{ $displayId }}" value="{{ $display }}" placeholder="{{ $placeholder }}" autocomplete="off" inputmode="numeric"
    data-date-display data-date-target="{{ $hiddenId }}"
    @if($min) data-min="{{ $min }}" @endif @if($max) data-max="{{ $max }}" @endif
    {{ $attributes }}>
@if($name) <input type="hidden" id="{{ $hiddenId }}" name="{{ $name }}" value="{{ $iso }}"> @endif
