@once
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/brand-theme.css') }}?v={{ filemtime(public_path('css/brand-theme.css')) }}">
@vite(['resources/css/smashzone.css'])
<script defer src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('css/communication.css') }}?v={{ filemtime(public_path('css/communication.css')) }}">
@vite(['resources/js/app.js'])
<script defer src="{{ asset('js/communication.js') }}?v={{ filemtime(public_path('js/communication.js')) }}"></script>
@endonce
