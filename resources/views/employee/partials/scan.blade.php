<div class="staff-card p-3 mb-3"><h2 class="h5">Tra cứu / quét mã booking</h2><form method="POST" action="{{ route('employee.scan') }}" id="scan-form" class="d-flex gap-2">@csrf<input id="booking-scan-code" name="code" class="form-control" placeholder="Nhập mã booking hoặc dùng máy quét QR" aria-label="Mã booking" required><button class="staff-button">Tra cứu</button></form><button type="button" id="camera-start" class="staff-button mt-2">Quét bằng camera</button><button type="button" id="camera-stop" class="staff-button mt-2" hidden>Dừng camera</button><p id="camera-message" role="status" class="mt-2 mb-0"></p><video id="scan-video" muted playsinline class="w-100 mt-2" style="max-height:280px" hidden></video></div>
@push('scripts')<script>
(() => {
    const video = document.getElementById('scan-video'), message = document.getElementById('camera-message');
    const stopButton = document.getElementById('camera-stop'); let stream, running = false;
    function stop() { running = false; stream?.getTracks().forEach(track => track.stop()); video.hidden = true; stopButton.hidden = true; }
    stopButton.addEventListener('click', stop); window.addEventListener('pagehide', stop);
    document.getElementById('camera-start').addEventListener('click', async () => {
        if (running) return;
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) { message.textContent = 'Trình duyệt chưa hỗ trợ quét camera. Dùng máy quét QR hoặc nhập mã booking.'; return; }
        try {
            const detector = new BarcodeDetector({formats: ['qr_code']});
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
            video.srcObject = stream; video.hidden = false; stopButton.hidden = false; await video.play(); running = true;
            message.textContent = 'Đưa mã QR vào camera. Kiểm tra thông tin booking trước khi check-in.';
            async function scan() {
                if (!running) return;
                try { const codes = await detector.detect(video); if (codes.length) { document.getElementById('booking-scan-code').value = codes[0].rawValue; stop(); document.getElementById('scan-form').requestSubmit(); return; } }
                catch { stop(); message.textContent = 'Không đọc được camera. Hãy nhập mã booking.'; return; }
                setTimeout(scan, 250);
            }
            scan();
        } catch { stop(); message.textContent = 'Không mở được camera. Kiểm tra quyền camera hoặc nhập mã booking.'; }
    });
})();
</script>@endpush
