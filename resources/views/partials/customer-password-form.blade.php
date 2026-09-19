<form method="POST" action="{{ route('password.change.update') }}">
    @csrf @method('PUT')
    @foreach(['current_password' => 'Mật khẩu hiện tại', 'password' => 'Mật khẩu mới', 'password_confirmation' => 'Xác nhận mật khẩu mới'] as $field => $label)
        <div class="mb-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" type="password" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    @endforeach
    <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
</form>
