@extends('layouts.app')
@section('title', 'Đổi mật khẩu — SmashZone')
@section('content')
<x-customer-shell active="password"><header class="sz-dashboard-heading"><div><h1>Đổi mật khẩu</h1><p>Bảo vệ tài khoản SmashZone của bạn.</p></div></header><section class="sz-customer-panel">@include('partials.customer-password-form')</section></x-customer-shell>
@endsection
