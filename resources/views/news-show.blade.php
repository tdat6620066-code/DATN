@extends('layouts.app')
@section('content')<article class="container py-5"><a href="{{ route('information') }}">← Tin tức</a><h1>{{ $news->title }}</h1>@if($news->thumbnail)<img class="img-fluid mb-3" src="{{ asset('storage/'.$news->thumbnail) }}" alt="{{ $news->title }}">@endif<div style="white-space:pre-wrap">{{ $news->content }}</div></article>@endsection
