<?php

namespace Database\Seeders;

use App\Models\ChatbotKnowledge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ChatbotKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['chao_hoi', ['xin chao', 'chao ban', 'hello', 'hi', 'chatbot', 'ban la ai'], 'Xin chào! Tôi là trợ lý SmashZone. Tôi có thể hướng dẫn đặt sân, thanh toán, hủy lịch và kiểm tra thông tin sân.', 10],
            ['huong_dan_dat_san', ['dat san', 'cach dat san', 'huong dan dat', 'muon dat san', 'dat lich'], 'Bạn vào mục Sân cầu lông, chọn sân, ngày chơi, khung giờ rồi nhấn Đặt sân ngay.', 10],
            ['tim_san', ['tim san', 'danh sach san', 'co san nao', 'xem san', 'san cau long'], 'Bạn vào mục Sân cầu lông để xem danh sách sân và sử dụng bộ lọc theo địa điểm, mức giá hoặc loại sân.', 8],
            ['san_trong', ['san trong', 'gio trong', 'con san khong', 'con trong', 'khung gio trong', 'lich trong'], 'Bạn hãy chọn sân và ngày muốn chơi. Hệ thống sẽ hiển thị những khung giờ còn trống.', 10],
            ['gia_san', ['gia san', 'bao nhieu tien', 'bang gia', 'chi phi', 'gia thue'], 'Giá sân phụ thuộc vào sân, ngày và khung giờ. Bạn chọn sân và thời gian để xem giá chính xác.', 9],
            ['thanh_toan', ['thanh toan', 'tra tien', 'chuyen khoan', 'phuong thuc thanh toan'], 'Sau khi chọn sân và khung giờ, bạn tiếp tục đến bước thanh toán và chọn phương thức được hệ thống hỗ trợ.', 9],
            ['thanh_toan_that_bai', ['thanh toan loi', 'thanh toan bi loi', 'thanh toan that bai', 'tru tien', 'khong thanh toan duoc'], 'Bạn hãy kiểm tra kết nối và trạng thái giao dịch trước khi thử lại. Nếu đã bị trừ tiền nhưng đơn chưa xác nhận, hãy liên hệ hỗ trợ và cung cấp mã giao dịch.', 10],
            ['xac_nhan_dat_san', ['xac nhan dat san', 'dat thanh cong', 'da dat chua', 'kiem tra don', 'chua nhan duoc xac nhan'], 'Bạn có thể vào Lịch sử đặt sân để kiểm tra trạng thái. Đơn thành công sẽ hiển thị trạng thái đã xác nhận.', 9],
            ['lich_su_dat_san', ['lich su dat san', 'don cua toi', 'san da dat', 'xem booking'], 'Bạn đăng nhập, mở tài khoản cá nhân và chọn Lịch sử đặt sân để xem các đơn đã đặt.', 9],
            ['huy_san', ['huy san', 'huy don dat san', 'huy lich', 'huy booking', 'khong choi nua'], 'Bạn mở đơn trong Lịch sử đặt sân và chọn Hủy nếu đơn vẫn còn trong thời gian được phép hủy.', 11],
            ['hoan_tien', ['hoan tien', 'lay lai tien', 'refund', 'bao lau hoan tien'], 'Sau khi yêu cầu hủy được chấp nhận, yêu cầu hoàn tiền sẽ được xử lý theo chính sách SmashZone và phương thức thanh toán đã dùng.', 9],
            ['doi_lich', ['doi lich', 'doi gio', 'doi san', 'doi ngay choi'], 'Bạn kiểm tra đơn đặt sân để xem có chức năng đổi lịch hay không. Nếu không có, hãy liên hệ hỗ trợ hoặc hủy và đặt lại theo chính sách.', 9],
            ['ma_giam_gia', ['ma giam gia', 'khuyen mai', 'voucher', 'coupon', 'giam gia'], 'Bạn nhập mã khuyến mãi tại bước thanh toán. Hệ thống sẽ kiểm tra điều kiện và tự động tính lại tổng tiền.', 8],
            ['ma_khong_hop_le', ['ma khong dung', 'ma giam gia khong dung', 'giam gia khong dung duoc', 'ma het han', 'voucher loi', 'khong ap dung duoc'], 'Mã có thể đã hết hạn, hết lượt sử dụng hoặc không phù hợp với đơn hàng. Bạn hãy kiểm tra điều kiện chương trình.', 11],
            ['dang_ky', ['dang ky', 'tao tai khoan', 'mo tai khoan', 'register'], 'Bạn chọn Đăng nhập, sau đó chọn Đăng ký và nhập đầy đủ thông tin tài khoản.', 7],
            ['dang_nhap', ['dang nhap', 'login', 'vao tai khoan', 'khong dang nhap duoc'], 'Bạn sử dụng email hoặc số điện thoại và mật khẩu đã đăng ký. Nếu không nhớ mật khẩu, hãy dùng chức năng Quên mật khẩu.', 7],
            ['quen_mat_khau', ['quen mat khau', 'lay lai mat khau', 'reset password'], 'Bạn chọn Quên mật khẩu tại trang đăng nhập, nhập email và làm theo hướng dẫn đặt lại mật khẩu.', 10],
            ['doi_mat_khau', ['doi mat khau', 'thay mat khau'], 'Bạn đăng nhập, mở Thông tin cá nhân và chọn chức năng đổi mật khẩu. Nhập mật khẩu hiện tại cùng mật khẩu mới rồi lưu thay đổi.', 10],
            ['cap_nhat_tai_khoan', ['sua thong tin', 'doi so dien thoai', 'doi ten', 'cap nhat tai khoan'], 'Bạn đăng nhập, vào trang Thông tin cá nhân, cập nhật thông tin rồi nhấn Lưu.', 7],
            ['yeu_thich', ['yeu thich', 'luu san', 'favorite', 'san da luu'], 'Bạn nhấn biểu tượng trái tim tại sân muốn lưu. Danh sách được hiển thị trong mục Sân yêu thích.', 6],
            ['danh_gia', ['danh gia', 'muon danh gia san', 'review', 'binh luan', 'cham sao'], 'Sau khi hoàn thành lượt chơi, bạn có thể vào đơn đặt sân để chấm sao và viết đánh giá.', 11],
            ['dia_chi_san', ['dia chi', 'san o dau', 'vi tri', 'duong di'], 'Bạn mở trang chi tiết sân để xem địa chỉ và thông tin vị trí của sân.', 8],
            ['gio_hoat_dong', ['gio mo cua', 'gio dong cua', 'hoat dong may gio', 'thoi gian mo cua'], 'Giờ hoạt động có thể khác nhau giữa các sân. Bạn hãy kiểm tra tại trang chi tiết sân muốn đặt.', 8],
            ['tien_ich_san', ['tien ich', 'cho de xe', 'phong thay do', 'wifi', 'nuoc uong'], 'Các tiện ích được hiển thị tại trang chi tiết sân. Bạn nên kiểm tra thông tin từng sân trước khi đặt.', 7],
            ['thue_dung_cu', ['thue vot', 'thue cau', 'dung cu', 'co vot khong'], 'Thông tin cho thuê dụng cụ tùy thuộc từng sân. Bạn hãy xem phần tiện ích hoặc liên hệ trực tiếp với sân.', 8],
            ['hoa_don', ['hoa don', 'bien lai', 'invoice', 'xuat hoa don'], 'Bạn mở chi tiết đơn đặt sân để xem thông tin thanh toán và hóa đơn nếu hệ thống hỗ trợ.', 7],
            ['lien_he', ['lien he', 'ho tro', 'hotline', 'bao loi', 'gap nhan vien'], 'Bạn vào mục Liên hệ và gửi nội dung cần hỗ trợ. Hãy cung cấp mã đơn nếu vấn đề liên quan đến đặt sân hoặc thanh toán.', 10],
            ['phan_hoi', ['toi muon phan hoi', 'gui phan hoi', 'gop y'], 'Bạn vào mục Liên hệ để gửi phản hồi hoặc góp ý cho SmashZone. Nếu liên quan đến một lượt đặt sân, hãy gửi kèm mã đơn.', 10],
            ['cam_on', ['cam on', 'thanks', 'thank you', 'tot qua'], 'Cảm ơn bạn đã sử dụng SmashZone. Bạn cần hỗ trợ thêm về đặt sân hay thanh toán không?', 5],
            ['thoi_luong_dat_san', ['dat toi thieu bao lau', 'thoi luong dat san', 'dat may tieng', 'choi bao lau'], 'Thời lượng đặt sân phụ thuộc vào quy định của từng sân. Bạn chọn khung giờ để xem thời lượng được phép đặt.', 8],
            ['dat_nhieu_khung_gio', ['dat nhieu gio', 'dat lien tiep', 'dat nhieu khung gio', 'choi nhieu tieng'], 'Bạn có thể chọn nhiều khung giờ liên tiếp nếu các khung giờ đó vẫn còn trống.', 9],
            ['dat_nhieu_san', ['dat nhieu san', 'dat hai san', 'dat cho nhom', 'dat san so luong lon'], 'Bạn có thể tạo nhiều lượt đặt sân. Với số lượng lớn, bạn nên kiểm tra từng sân và khung giờ trước khi thanh toán.', 8],
            ['dat_ho_nguoi_khac', ['dat ho', 'dat cho ban', 'dat cho nguoi khac', 'nguoi khac den choi'], 'Bạn có thể đặt sân cho người khác, nhưng cần nhập đúng thông tin người chơi để sân có thể xác nhận khi đến.', 7],
            ['dat_san_trong_ngay', ['dat hom nay', 'dat trong ngay', 'dat ngay bay gio', 'san hom nay'], 'Bạn có thể chọn ngày hôm nay để kiểm tra những khung giờ vẫn còn trống và được phép đặt.', 9],
            ['dat_san_truoc', ['dat truoc bao lau', 'dat truoc may ngay', 'dat thang sau', 'dat san truoc'], 'Bạn chọn ngày muốn chơi trên lịch. Hệ thống sẽ hiển thị các ngày và khung giờ đang cho phép đặt.', 8],
            ['khung_gio_cao_diem', ['gio cao diem', 'gio dong nguoi', 'khung gio cao diem', 'gia buoi toi'], 'Giá có thể thay đổi theo khung giờ. Bạn chọn ngày và giờ cụ thể để xem mức giá chính xác.', 8],
            ['san_bi_trung_lich', ['trung lich', 'san vua bi dat', 'khong dat duoc gio nay', 'nguoi khac dat'], 'Khung giờ có thể vừa được người khác đặt. Bạn hãy tải lại lịch và chọn khung giờ còn trống.', 10],
            ['giu_cho', ['giu cho', 'giu san', 'giu lich', 'chua thanh toan'], 'Khung giờ chỉ được xác nhận theo quy trình đặt sân của hệ thống. Bạn nên hoàn thành thanh toán trong thời gian quy định.', 9],
            ['ma_dat_san', ['ma dat san', 'ma booking', 'ma don', 'booking code'], 'Mã đặt sân nằm trong trang chi tiết đơn hoặc thông báo xác nhận sau khi bạn đặt sân thành công.', 9],
            ['check_in', ['check in', 'nhan san', 'den san', 'thu tuc vao san'], 'Khi đến sân, bạn cung cấp mã đặt sân hoặc thông tin tài khoản để nhân viên kiểm tra.', 9],
            ['den_muon', ['den muon', 'tre gio', 'toi muon', 'qua gio dat'], 'Nếu có thể đến muộn, bạn nên liên hệ với sân sớm. Thời gian kết thúc thường vẫn theo khung giờ đã đặt.', 9],
            ['khong_den_choi', ['khong den', 'bo lich', 'vang mat', 'khong the den choi'], 'Nếu không thể đến chơi, bạn nên hủy đơn sớm hoặc liên hệ hỗ trợ. Quyền hoàn tiền phụ thuộc chính sách của đơn.', 9],
            ['cho_xac_nhan', ['cho xac nhan', 'dang xu ly', 'don pending', 'chua xac nhan'], 'Đơn đang chờ xác nhận có thể chưa hoàn tất thanh toán hoặc hệ thống đang xử lý. Bạn hãy kiểm tra lại sau hoặc liên hệ hỗ trợ.', 10],
            ['thanh_toan_tien_mat', ['tien mat', 'tra tai san', 'thanh toan khi den', 'cash'], 'Bạn kiểm tra các phương thức thanh toán hiển thị tại bước đặt sân. Chỉ những phương thức đang hiển thị mới được hỗ trợ.', 8],
            ['thanh_toan_chuyen_khoan', ['chuyen khoan', 'tai khoan ngan hang', 'quet ma qr', 'banking'], 'Nếu hệ thống hỗ trợ chuyển khoản hoặc QR, thông tin thanh toán sẽ xuất hiện sau khi bạn xác nhận đơn.', 8],
            ['thanh_toan_dang_xu_ly', ['thanh toan dang xu ly', 'giao dich pending', 'cho thanh toan', 'chua cap nhat thanh toan'], 'Bạn không nên thanh toán lại ngay. Hãy chờ hệ thống cập nhật và kiểm tra lịch sử giao dịch trước.', 10],
            ['hoan_tien_chua_nhan', ['chua nhan hoan tien', 'hoan tien cham', 'tien chua ve', 'kiem tra hoan tien'], 'Bạn kiểm tra trạng thái yêu cầu hoàn tiền. Nếu đã quá thời gian dự kiến, hãy liên hệ hỗ trợ và cung cấp mã đơn.', 10],
            ['huy_yeu_cau_hoan_tien', ['huy hoan tien', 'khong muon refund', 'rut yeu cau hoan tien'], 'Bạn mở chi tiết yêu cầu hoàn tiền để kiểm tra khả năng hủy. Yêu cầu đã được xử lý có thể không hủy được.', 8],
            ['thong_bao', ['thong bao', 'notification', 'khong nhan thong bao', 'bao lich'], 'Bạn đăng nhập và mở mục Thông báo để xem cập nhật về đặt sân, thanh toán và khuyến mãi.', 7],
            ['email_xac_nhan', ['email xac nhan', 'khong nhan email', 'thu xac nhan', 'mail dat san'], 'Bạn kiểm tra hộp thư đến và thư rác. Đồng thời kiểm tra email trong tài khoản đã được nhập chính xác hay chưa.', 8],
            ['tai_khoan_bi_khoa', ['tai khoan bi khoa', 'khong vao duoc tai khoan', 'account locked', 'bi chan dang nhap'], 'Bạn hãy sử dụng chức năng Quên mật khẩu. Nếu vẫn không đăng nhập được, hãy liên hệ bộ phận hỗ trợ.', 9],
            ['xoa_tai_khoan', ['xoa tai khoan', 'huy tai khoan', 'delete account', 'khong dung nua'], 'Bạn vào phần cài đặt tài khoản để kiểm tra chức năng xóa tài khoản hoặc gửi yêu cầu cho bộ phận hỗ trợ.', 8],
            ['bao_mat', ['bao mat', 'lo mat khau', 'tai khoan bi hack', 'nguoi khac dang nhap'], 'Bạn nên đổi mật khẩu ngay, đăng xuất khỏi các thiết bị khác và liên hệ hỗ trợ nếu phát hiện hoạt động bất thường.', 10],
            ['quyen_rieng_tu', ['quyen rieng tu', 'du lieu ca nhan', 'bao mat thong tin', 'privacy'], 'Thông tin cá nhân được sử dụng để quản lý tài khoản và đơn đặt sân. Bạn có thể xem chính sách bảo mật của SmashZone để biết thêm chi tiết.', 7],
            ['san_trong_nha', ['san trong nha', 'san ngoai troi', 'indoor', 'outdoor'], 'Loại sân được hiển thị trong trang chi tiết. Bạn có thể sử dụng bộ lọc để tìm loại sân phù hợp.', 7],
            ['loai_mat_san', ['mat san', 'san tham', 'san go', 'loai san'], 'Thông tin về loại mặt sân được hiển thị trong phần mô tả và tiện ích của từng sân.', 7],
            ['anh_san', ['anh san', 'hinh anh san', 'xem san', 'san co dep khong'], 'Bạn mở trang chi tiết sân để xem hình ảnh, mô tả và những tiện ích hiện có.', 6],
            ['do_that_lac', ['mat do', 'quen do', 'that lac', 'de quen vot'], 'Bạn nên liên hệ trực tiếp với sân và cung cấp thời gian, mã đặt sân cùng mô tả đồ vật bị thất lạc.', 9],
            ['su_co_tai_san', ['su co', 'san bi loi', 'mat dien', 'khong choi duoc'], 'Bạn hãy thông báo ngay cho nhân viên sân. Nếu sự cố ảnh hưởng đến lượt chơi, hãy lưu mã đơn để được hỗ trợ.', 10],
            ['tin_tuc', ['tin tuc', 'bai viet', 'news', 'thong tin cau long'], 'Bạn vào mục Tin tức để xem bài viết, sự kiện và thông báo mới từ SmashZone.', 5],
            ['giai_dau', ['giai dau', 'su kien', 'thi dau', 'dang ky giai'], 'Thông tin giải đấu và sự kiện sẽ được đăng trong mục Tin tức hoặc Khuyến mãi khi có chương trình.', 7],
            ['bao_cao_danh_gia', ['bao cao danh gia', 'binh luan xau', 'review vi pham', 'report comment'], 'Bạn có thể sử dụng chức năng báo cáo tại đánh giá hoặc gửi nội dung cho quản trị viên kiểm tra.', 8],
            ['chatbot_khong_hieu', ['khong dung', 'tra loi sai', 'khong hieu', 'chatbot bi loi'], 'Xin lỗi vì câu trả lời chưa phù hợp. Bạn hãy mô tả rõ hơn vấn đề hoặc chọn liên hệ nhân viên hỗ trợ.', 9],
            ['tam_biet', ['tam biet', 'bye', 'hen gap lai', 'ket thuc'], 'Tạm biệt! Chúc bạn có những trận cầu thật vui cùng SmashZone.', 5],
        ];

        foreach ($data as [$intent, $keywords, $answer, $priority]) {
            ChatbotKnowledge::updateOrCreate(['intent' => $intent], compact('keywords', 'answer', 'priority') + ['active' => true]);
        }

        Cache::forget('chatbot.knowledge.active');
    }
}
