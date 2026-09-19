@props(['label', 'tone' => 'neutral'])
@php($safeTone = in_array($tone, ['success', 'warning', 'danger', 'info', 'neutral'], true) ? $tone : 'neutral')
<span {{ $attributes->class(['sz-status', 'sz-status--'.$safeTone]) }}>{{ $label }}</span>
