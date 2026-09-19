@if($booking->status === 'COMPLETED' && auth()->id() === $booking->user_id && auth()->user()->role === 'CUSTOMER')
    @php($bookingReviews = \App\Models\Review::where('booking_id', $booking->id)->where('user_id', auth()->id())->get()->keyBy('court_id'))
    <section class="container my-4" aria-labelledby="booking-reviews-title">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5" id="booking-reviews-title">Đánh giá trải nghiệm của bạn</h2>
            <p class="text-muted">Chấm điểm và chia sẻ trải nghiệm thực tế. Mỗi sân trong booking được đánh giá một lần; nội dung được duyệt trước khi công khai.</p>
            @error('review')<p class="alert alert-danger" role="alert">{{ $message }}</p>@enderror
            @foreach($booking->bookingDetails->where('status', '!=', 'CANCELLED')->unique('court_id') as $reviewDetail)
                @php($submittedReview = $bookingReviews->get($reviewDetail->court_id))
                <div class="border rounded p-3 mb-3">
                    <h3 class="h6">{{ $reviewDetail->court?->name ?? 'Sân cầu lông' }}</h3>
                    @if($submittedReview)
                        <p>{{ $submittedReview->rating }}/5 sao · {{ match($submittedReview->status) {'APPROVED' => 'Đã duyệt', 'REJECTED' => 'Không được duyệt', default => 'Chờ duyệt'} }}</p>
                        <p class="text-break mb-0">{{ $submittedReview->content }}</p>
                    @else
                        <form method="POST" action="{{ route('bookings.reviews.store', $booking) }}">
                            @csrf
                            <input type="hidden" name="court_id" value="{{ $reviewDetail->court_id }}">
                            <label class="form-label" for="review-rating-{{ $reviewDetail->court_id }}">Điểm đánh giá</label>
                            <select class="form-select mb-3" id="review-rating-{{ $reviewDetail->court_id }}" name="rating" required>
                                <option value="">Chọn số sao</option>
                                @foreach([5 => 'Rất hài lòng', 4 => 'Hài lòng', 3 => 'Bình thường', 2 => 'Chưa hài lòng', 1 => 'Không hài lòng'] as $stars => $label)
                                    <option value="{{ $stars }}" @selected(old('court_id') == $reviewDetail->court_id && old('rating') == $stars)>{{ $stars }} sao — {{ $label }}</option>
                                @endforeach
                            </select>
                            @if(old('court_id') == $reviewDetail->court_id) @error('rating')<p class="text-danger" role="alert">{{ $message }}</p>@enderror @endif
                            <label class="form-label" for="review-content-{{ $reviewDetail->court_id }}">Bình luận</label>
                            <textarea class="form-control mb-3" id="review-content-{{ $reviewDetail->court_id }}" name="content" rows="4" maxlength="2000" required placeholder="Chia sẻ về chất lượng sân, tiện ích và phục vụ...">{{ old('court_id') == $reviewDetail->court_id ? old('content') : '' }}</textarea>
                            @if(old('court_id') == $reviewDetail->court_id) @error('content')<p class="text-danger" role="alert">{{ $message }}</p>@enderror @endif
                            <button class="btn btn-primary" type="submit">Gửi đánh giá</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div></div>
    </section>
@endif
