@error('daily_duration_confirmed')
    <button type="button" class="btn btn-outline-success mb-3" data-bs-toggle="modal" data-bs-target="#daily-duration-modal">Xác nhận thời lượng chơi</button>
    <input type="hidden" name="daily_duration_confirmed" value="1" id="daily-duration-confirmed" form="bookingForm" disabled>
    <div class="modal fade" id="daily-duration-modal" tabindex="-1" aria-labelledby="daily-duration-title" aria-describedby="daily-duration-description">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 px-4 pt-4">
                    <h2 class="modal-title fs-5" id="daily-duration-title"><i class="bi bi-clock-history text-warning me-2" aria-hidden="true"></i>Xác nhận thời lượng chơi</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body px-4">
                    <p id="daily-duration-description">{{ $message }}</p>
                    <div class="bg-light rounded-3 p-3 text-muted small">Hãy dành thời gian nghỉ và cân nhắc thể trạng của bạn. Booking chưa được tạo; bạn có thể quay lại để giảm số khung giờ.</div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại chọn giờ</button>
                    <button type="button" class="btn btn-success" id="daily-duration-continue">Xác nhận tiếp tục</button>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script defer src="{{ asset('js/daily-duration-confirmation.js') }}?v={{ filemtime(public_path('js/daily-duration-confirmation.js')) }}"></script>
    @endpush
@enderror
