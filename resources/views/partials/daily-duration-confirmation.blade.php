@error('daily_duration_confirmed')
    <section class="sz-duration-confirmation" aria-labelledby="daily-duration-title" id="daily-duration-warning">
      <div class="sz-duration-confirmation__icon" aria-hidden="true"><i class="bi bi-clock-history"></i></div>
      <div class="sz-duration-confirmation__body">
        <span class="sz-duration-confirmation__eyebrow">CHƠI HẾT MÌNH · NGHỈ ĐÚNG LÚC</span>
        <h2 id="daily-duration-title">Xác nhận thời lượng chơi của bạn</h2>
        <p id="daily-duration-description">{{ $message }}</p>
        <label class="sz-duration-confirmation__choice" for="daily-duration-confirmed">
            <input class="form-check-input" type="checkbox" name="daily_duration_confirmed" value="1" id="daily-duration-confirmed" form="bookingForm" aria-describedby="daily-duration-description daily-duration-help">
            <span>Tôi đã cân nhắc thời lượng, thời gian nghỉ và muốn tiếp tục đặt sân.</span>
        </label>
        <p class="sz-duration-confirmation__help" id="daily-duration-help">Tích xác nhận rồi tiếp tục đặt sân bên dưới. Bạn cũng có thể giảm số khung giờ; booking chưa được tạo ở bước này.</p>
      </div>
    </section>
@enderror
