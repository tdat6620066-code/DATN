<!DOCTYPE html>
<html lang="vi" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã xác thực tài khoản SmashZone</title>
</head>
<body style="margin:0;padding:0;background:#eef4f1;font-family:'Segoe UI',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f1;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(8,38,53,.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#082635 0%,#086052 100%);padding:32px 36px;text-align:center;">
                            <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="SmashZone" width="170" style="display:block;margin:0 auto 14px;border-radius:10px;">
                            <div style="color:#5eead4;font-size:12px;font-weight:800;letter-spacing:1.5px;">SMASHZONE · BADMINTON BOOKING</div>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:36px;">
                            <h2 style="margin:0 0 10px;color:#153342;font-size:24px;font-weight:800;letter-spacing:-.5px;">Xin chào {{ $user->name }},</h2>
                            <p style="margin:0 0 22px;color:#71818a;font-size:14px;line-height:1.7;">
                                Cảm ơn bạn đã đăng ký tài khoản SmashZone. Vui lòng sử dụng mã bên dưới để kích hoạt tài khoản của bạn.
                            </p>

                            <!-- Verification code -->
                            <div style="background:#f0f9f4;border:1px dashed #bfe8d4;border-radius:14px;padding:24px;text-align:center;margin-bottom:24px;">
                                <div style="color:#00a963;font-size:11px;font-weight:900;letter-spacing:1.2px;margin-bottom:10px;">MÃ XÁC THỰC CỦA BẠN</div>
                                <div style="font-size:40px;font-weight:900;letter-spacing:14px;color:#08b96b;font-family:'Consolas',Monaco,monospace;">{{ $code }}</div>
                            </div>

                            <p style="margin:0 0 8px;color:#71818a;font-size:13px;line-height:1.7;">Mã này có hiệu lực trong <strong>10 phút</strong>.</p>
                            <p style="margin:0 0 26px;color:#9aa8ad;font-size:12px;line-height:1.6;">Nếu bạn không thực hiện yêu cầu đăng ký này, vui lòng bỏ qua email này.</p>

                            <!-- CTA -->
                            <a href="{{ route('home') }}" style="display:inline-block;background:#08c473;color:#063623;text-decoration:none;font-weight:800;font-size:13px;padding:13px 26px;border-radius:10px;">Khám phá SmashZone →</a>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background:#f6faf8;padding:22px 36px;text-align:center;color:#9aa8ad;font-size:11px;line-height:1.7;">
                            © {{ now()->year }} SmashZone · Nền tảng đặt sân cầu lông<br>
                            Cần hỗ trợ? Liên hệ <a href="mailto:hello@smashzone.vn" style="color:#00a963;text-decoration:none;">hello@smashzone.vn</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>