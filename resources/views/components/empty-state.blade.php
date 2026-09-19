@props(['title' => 'Chưa có dữ liệu', 'description' => 'Thông tin sẽ xuất hiện tại đây khi có dữ liệu.', 'icon' => 'bi-inbox'])
<div {{ $attributes->class(['sz-empty']) }}>
    <i class="bi {{ $icon }}" aria-hidden="true"></i>
    <h2>{{ $title }}</h2>
    <p>{{ $description }}</p>
    @if($slot->isNotEmpty())<div>{{ $slot }}</div>@endif
</div>
