<div class="admin-label">DANH MỤC & NỘI DUNG</div>
<nav class="admin-nav">
    @foreach(\App\Http\Controllers\AdminContentController::TYPES as $kind => $definition)
        <a class="{{ request()->route('kind') === $kind ? 'active' : '' }}" href="{{ route('admin.content.index', $kind) }}">{{ $definition[1] }}</a>
    @endforeach
    <a href="{{ route('admin.slots.index') }}">Quản lý khung giờ</a>
    <a href="{{ route('admin.users.index') }}">Quản lý người dùng</a>
    <a href="{{ route('admin.roles.index') }}">Quản lý vai trò</a>
    <a href="{{ route('contacts.index') }}">Liên hệ & hỗ trợ khách hàng</a>
    <a href="{{ route('admin.settings.edit') }}">Cài đặt hệ thống</a>
</nav>
