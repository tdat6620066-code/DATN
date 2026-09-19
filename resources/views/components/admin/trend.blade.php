@props(['values','labels','label','money'=>false])
@php
    $series = collect($values)->values();
    $max = max(1, $series->max() ?? 0); $min = min(0, $series->min() ?? 0); $range = $max - $min;
    $points = $series->map(fn($value,$index) => (36 + $index * 628 / max(1,$series->count()-1)).','. (220 - ((float)$value-$min)/$range*184))->join(' ');
@endphp
<svg viewBox="0 0 700 260" class="w-100" role="img" aria-label="{{ $label }}"><title>{{ $label }}</title>
@foreach([0,1,2,3,4] as $tick)<line x1="36" y1="{{ 36+$tick*46 }}" x2="664" y2="{{ 36+$tick*46 }}" stroke="#e2e8f0" />@endforeach
<polyline points="{{ $points }}" fill="none" stroke="#15803d" stroke-width="3" stroke-linejoin="round" />
@foreach($series as $index=>$value)<circle cx="{{ 36+$index*628/max(1,$series->count()-1) }}" cy="{{ 220-((float)$value-$min)/$range*184 }}" r="{{ $series->count()>60 ? 1 : 3 }}" fill="#15803d"><title>{{ $labels[$index] ?? '' }}: {{ number_format($value,0,',','.') }}{{ $money?'đ':'' }}</title></circle>@endforeach
<text x="36" y="246" font-size="12" fill="#64748b">{{ collect($labels)->first() }}</text><text x="664" y="246" text-anchor="end" font-size="12" fill="#64748b">{{ collect($labels)->last() }}</text>
<text x="36" y="22" font-size="12" fill="#64748b">Cao nhất: {{ number_format($series->max() ?? 0,0,',','.') }}{{ $money?'đ':'' }}</text></svg>
<details class="admin-data-summary"><summary>Xem số liệu {{ mb_strtolower($label) }}</summary><div class="table-responsive mt-2"><table class="table"><thead><tr><th>Ngày / chỉ số</th><th>{{ $label }}</th></tr></thead><tbody>@foreach($series as $index=>$value)<tr><td>{{ $labels[$index] ?? '' }}</td><td>{{ number_format($value,0,',','.') }}{{ $money?'đ':'' }}</td></tr>@endforeach</tbody></table></div></details>
