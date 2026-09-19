@extends('layouts.admin')
@section('page_heading', $title)
@section('content')<x-admin.workspace>
<a href="{{ route('admin.content.index',$kind) }}">← Danh sách {{ $title }}</a><x-admin.page-heading>{{ $readOnly ? 'Chi tiết' : ($item->exists ? 'Chỉnh sửa' : 'Thêm mới') }} — {{ $title }}</x-admin.page-heading>
<form class="card card-body" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.content.update',[$kind,$item->id]) : route('admin.content.store',$kind) }}">@csrf @if($item->exists) @method('PUT') @endif
<fieldset @disabled($readOnly)><div class="row">
@foreach($fields as $field=>$caption)
<div class="col-md-6 mb-3"><label class="form-label" for="field-{{ $field }}">{{ $caption }}</label>
@if(in_array($field,['image','thumbnail']))
@if($item->$field)<img class="d-block mb-2" style="max-height:160px;max-width:100%" src="{{ asset('storage/'.$item->$field) }}" alt="{{ $caption }}">@endif<input id="field-{{ $field }}" class="form-control" type="file" name="{{ $field }}" accept="image/jpeg,image/png,image/webp">
@elseif(in_array($field,['content','description','answer']))<textarea id="field-{{ $field }}" class="form-control" rows="6" name="{{ $field }}">{{ old($field,$item->$field) }}</textarea>
@elseif(in_array($field,['status','active','is_active']))<select id="field-{{ $field }}" class="form-select" name="{{ $field }}">@foreach($statuses as $value=>$text)<option value="{{ $value }}" @selected((string)old($field,$item->$field ?? array_key_first($statuses))===(string)$value)>{{ $text }}</option>@endforeach</select>
@elseif($field==='brand_id')<select id="field-{{ $field }}" class="form-select" name="brand_id"><option value="">Không chọn</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(old('brand_id',$item->brand_id)==$brand->id)>{{ $brand->name }}</option>@endforeach</select>
@else
@php($type=in_array($field,['start_at','end_at','published_at'])?'datetime-local':(in_array($field,['price','priority','sort_order'])?'number':(in_array($field,['website','link'])?'url':'text')))
<input id="field-{{ $field }}" class="form-control" type="{{ $type }}" name="{{ $field }}" value="{{ old($field,$type==='datetime-local' ? $item->$field?->format('Y-m-d\TH:i') : ($item->$field ?? ($type==='number'?0:''))) }}" @if($type==='number') min="0" step="{{ $field==='price'?'0.01':'1' }}" @endif>
@endif
@error($field)<div class="text-danger">{{ $message }}</div>@enderror</div>
@endforeach</div></fieldset>@unless($readOnly)<button class="btn btn-success align-self-start">Lưu</button>@endunless</form>
@if($kind==='services' && $item->exists)<section class="card card-body mt-3"><h2 class="h5">Tồn kho: {{ $item->stock ?? 'Không giới hạn' }}</h2>@unless($readOnly)<form method="POST" action="{{ route('admin.services.stock',$item->id) }}" class="row g-2">@csrf<div class="col-md-3"><input class="form-control" type="number" name="quantity" placeholder="Nhập (+) / xuất (-)" required></div><div class="col-md-6"><input class="form-control" name="reason" placeholder="Lý do điều chỉnh" maxlength="1000" required></div><div class="col-md-3"><button class="btn btn-primary">Cập nhật kho</button></div></form>@endunless<table class="table mt-3"><thead><tr><th>Thời gian</th><th>Thay đổi</th><th>Tồn sau</th><th>Lý do</th></tr></thead><tbody>@foreach($movements as $movement)<tr><td>{{ $movement->created_at }}</td><td>{{ $movement->quantity }}</td><td>{{ $movement->balance }}</td><td>{{ $movement->reason }}</td></tr>@endforeach</tbody></table></section>@endif
</x-admin.workspace>@endsection
