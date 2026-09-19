@error('daily_duration_confirmed')
    <div class="alert alert-warning" role="alert" tabindex="-1" id="daily-duration-warning">
        <h3 class="h6">Xác nhận thời lượng chơi trong ngày</h3>
        <p id="daily-duration-description">{{ $message }}</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="daily_duration_confirmed" value="1" id="daily-duration-confirmed" aria-describedby="daily-duration-description">
            <label class="form-check-label" for="daily-duration-confirmed">Tôi đã cân nhắc thời lượng, thời gian nghỉ và muốn tiếp tục đặt sân.</label>
        </div>
        <p class="small mb-0 mt-2">Bạn có thể giảm số khung giờ rồi gửi lại. Booking chưa được tạo ở bước nhắc nhở này.</p>
    </div>
@enderror
