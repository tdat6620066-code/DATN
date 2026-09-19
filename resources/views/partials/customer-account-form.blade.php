<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label" for="avatar">Ảnh đại diện</label><input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="form-control @error('avatar') is-invalid @enderror">@error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">JPG, PNG hoặc WebP, tối đa 2 MB.</div></div>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Họ tên</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="phone">Số điện thoại</label><input id="phone" name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" required autocomplete="tel">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label" for="email">Email</label><input id="email" type="email" class="form-control" value="{{ $user->email }}" readonly></div>
        <div class="col-12"><label class="form-label" for="address">Địa chỉ</label><textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3" maxlength="500">{{ old('address', $user->address) }}</textarea>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div><button type="submit" class="btn btn-primary mt-4">Lưu thay đổi</button>
</form>
