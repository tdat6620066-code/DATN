@extends('layouts.admin')
@section('page_heading','Cài đặt hệ thống')
@section('content')<x-admin.workspace><x-admin.page-heading>Cài đặt hệ thống</x-admin.page-heading><form class="card card-body" method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
@foreach(['site_name'=>'Tên hệ thống','address'=>'Địa chỉ','phone'=>'Điện thoại liên hệ','email'=>'Email liên hệ'] as $key=>$label)<label class="form-label">{{ $label }}</label><input class="form-control mb-3" name="{{ $key }}" value="{{ old($key,$settings[$key]??($key==='site_name'?'SmashZone':'')) }}" @if($key==='site_name') required @endif>@endforeach
<p>Đơn vị tiền tệ: VNĐ. Múi giờ: {{ config('app.timezone') }}.</p>
@foreach(['vnpay_enabled'=>'Thanh toán VNPay','cash_enabled'=>'Thanh toán tiền mặt tại quầy'] as $key=>$label)<label class="mb-3"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$settings[$key]??'1'))> {{ $label }}</label>@endforeach<button class="btn btn-success align-self-start">Lưu cài đặt</button></form></x-admin.workspace>@endsection
