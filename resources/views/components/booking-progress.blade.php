@props(['step' => 1])
@once
    @push('styles') @vite(['resources/css/booking-flow.css', 'resources/js/booking-flow.js']) @endpush
@endonce
<nav class="sz-booking-progress" aria-label="Tiến trình đặt sân">
    <ol>
        @foreach(['Chọn sân', 'Chọn lịch', 'Dịch vụ', 'Thanh toán', 'Hoàn tất'] as $label)
            <li class="{{ $loop->iteration < $step ? 'is-complete' : '' }} {{ $loop->iteration == $step ? 'is-current' : '' }}" @if($loop->iteration == $step) aria-current="step" @endif>
                <span class="sz-progress-number">{{ $loop->iteration }}</span><span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
