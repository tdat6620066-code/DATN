<?php

namespace App\Http\Controllers;

use App\Services\AiChatbotService;
use App\Services\ChatbotLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Điểm vào của SmashBot cho giao diện chat.
 *
 * Mọi câu hỏi đều được xử lý bởi AiChatbotService: pipeline gồm security guard,
 * booking copilot, multi-intent planner, agent tool calling (Groq/OpenAI đọc dữ
 * liệu thật qua tool) và cuối cùng là engine luật khi không có API key.
 */
class ChatController extends Controller
{
    /**
     * Các action do nút bấm trong khung chat gửi lên (không phải câu hỏi tự do).
     */
    private const ACTIONS = [
        'select_slot',
        'confirm_booking',
        'find_other_slot',
        'confirm_cancel',
        'abort_cancel',
        'preview_copilot_booking',
        'confirm_copilot_booking',
        'copilot_other_choices',
    ];

    public function __construct(
        private readonly AiChatbotService $chatbot,
        private readonly ChatbotLoggerService $logger,
    ) {}

    /**
     * Trả lời dạng JSON cho API và cho test.
     */
    public function chat(Request $request): JsonResponse
    {
        [$message, $action, $choiceId] = $this->payload($request);

        $started = hrtime(true);
        $result = $this->chatbot->answer($message, $request->user(), $action, $choiceId);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        $this->logger->log($request->user(), $message, $result, $latency);

        return response()->json(['data' => $result]);
    }

    /**
     * Trả lời dạng NDJSON để khung chat hiển thị dần từng cụm ký tự.
     */
    public function stream(Request $request): StreamedResponse
    {
        [$message, $action, $choiceId] = $this->payload($request);

        $started = hrtime(true);
        $result = $this->chatbot->answer($message, $request->user(), $action, $choiceId);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        $this->logger->log($request->user(), $message, $result, $latency);

        $done = collect($result)->only([
            'suggestions', 'cards', 'buttons', 'intent', 'awaiting',
            'redirect_url', 'booking_url', 'booking_code', 'preview', 'plan',
        ])->all();

        return response()->stream(function () use ($result, $done): void {
            foreach (mb_str_split((string) ($result['answer'] ?? ''), 12) as $text) {
                echo json_encode(['type' => 'delta', 'text' => $text], JSON_UNESCAPED_UNICODE)."\n";
            }
            echo json_encode(['type' => 'done', 'data' => $done], JSON_UNESCAPED_UNICODE)."\n";
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Chuẩn hoá payload: câu hỏi tự do HOẶC action + choice_id của nút bấm.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function payload(Request $request): array
    {
        $text = Str::lower(Str::ascii(trim($message)));
        $step = session('chatbot.booking_step');

        if (Str::contains($text, ['danh dau tat ca da doc', 'doc tat ca thong bao'])) {
            Notification::where('user_id', auth()->id())->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
            return $this->result('Mình đã đánh dấu tất cả thông báo của bạn là đã đọc.', ['Mở trung tâm thông báo', 'Cài đặt thông báo']);
        }

        if (Str::contains($text, ['thong bao cua toi', 'thong bao chua doc', 'xem thong bao', 'co thong bao'])) {
            $query = Notification::where('user_id', auth()->id());
            $unread = (clone $query)->where('is_read', false)->count();
            $notifications = $query->latest()->limit(5)->get();
            if ($notifications->isEmpty()) {
                return $this->result('Bạn chưa có thông báo nào.', ['Cài đặt thông báo', 'Tôi muốn đặt sân']);
            }
            $lines = $notifications->map(fn (Notification $notification) => '• '.$notification->title.' — '.Str::limit($notification->content, 90));
            return $this->result("Bạn có {$unread} thông báo chưa đọc. 5 thông báo gần nhất:\n".$lines->join("\n"), ['Mở trung tâm thông báo', 'Đánh dấu tất cả đã đọc', 'Cài đặt thông báo']);
        }

        if (Str::contains($text, ['cai dat thong bao', 'tat khuyen mai', 'tat nhac lich'])) {
            return $this->result('Bạn có thể bật hoặc tắt nhắc lịch, khuyến mãi và email trong trang Cài đặt thông báo. Các thông báo vận hành quan trọng luôn được giữ bật.', ['Cài đặt thông báo', 'Thông báo của tôi']);
        }

        if ($step === 'date') {
            $date = match (true) {
                Str::contains($text, 'ngay mai') => 'Ngày mai',
                Str::contains($text, 'hom nay') => 'Hôm nay',
                Str::contains($text, 'cuoi tuan') => 'Cuối tuần',
                default => trim($message),
            };
            session(['chatbot.booking_date' => $date, 'chatbot.booking_step' => 'hour']);
            return $this->result('Bạn muốn đặt sân lúc mấy giờ?', ['18h', '19h', '20h']);
        }

        if ($step === 'hour') {
            $date = session('chatbot.booking_date', 'ngày đã chọn');
            session()->forget(['chatbot.booking_step', 'chatbot.booking_date']);
            return $this->result("Mình đã ghi nhận {$date}, lúc ".trim($message).'. Bạn bấm “Đặt sân ngay” để xem sân trống và hoàn tất đặt sân.', ['Đặt sân ngay', 'Giá thuê sân bao nhiêu?']);
        }

        if (Str::contains($text, ['dat san', 'muon dat'])) {
            session(['chatbot.booking_step' => 'date']);
            return $this->result('Bạn muốn đặt sân vào ngày nào?', ['Hôm nay', 'Ngày mai', 'Cuối tuần']);
        }

        if (Str::contains($text, ['gia', 'bao nhieu', 'bang gia']) && ! Str::contains($text, ['khuyen mai', 'voucher', 'giam gia', 'uu dai'])) {
            $courts = Court::query()->where('status', 'ACTIVE')->with('prices')->limit(6)->get();
            $lines = $courts->map(function (Court $court): string {
                $prices = $court->prices->pluck('price')->filter();
                return '• '.$court->name.': '.($prices->isEmpty() ? 'liên hệ' : number_format((float) $prices->min()).'đ – '.number_format((float) $prices->max()).'đ');
            });
            return $this->result($lines->isEmpty() ? 'Hiện chưa có bảng giá sân.' : "Bảng giá tham khảo:\n".$lines->join("\n"), ['Tôi muốn đặt sân', 'Có khuyến mãi nào?']);
        }

        if (Str::contains($text, ['khuyen mai', 'voucher', 'giam gia', 'uu dai'])) {
            $promotions = Promotion::query()->where('status', 'ACTIVE')
                ->where('start_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
                ->limit(5)->pluck('title');
            return $this->result($promotions->isEmpty() ? 'Hiện chưa có chương trình khuyến mãi đang áp dụng.' : "Khuyến mãi hiện có:\n• ".$promotions->join("\n• "), ['Tôi muốn đặt sân', 'Giá thuê sân bao nhiêu?']);
        }

        if (Str::contains($text, ['san', 'phu hop', 'goi y'])) {
            $courts = Court::query()->where('status', 'ACTIVE')->orderByDesc('is_featured')->limit(5)->pluck('name');
            return $this->result($courts->isEmpty() ? 'Hiện chưa có sân đang hoạt động.' : "Một số sân dành cho bạn:\n• ".$courts->join("\n• "), ['Tôi muốn đặt sân', 'Giá thuê sân bao nhiêu?']);
        }

        return $this->result('Mình có thể hỗ trợ bạn đặt sân, xem giá thuê, tìm sân hoặc kiểm tra khuyến mãi.', ['Tôi muốn đặt sân', 'Giá thuê sân bao nhiêu?', 'Có khuyến mãi nào?']);
    }

        $message = trim((string) ($data['message'] ?? ''));
        $action = $data['action'] ?? null;
        $choiceId = $data['choice_id'] ?? null;

        abort_if($message === '' && blank($action), 422, 'Cần nội dung câu hỏi hoặc action.');

        return [$message, $action, $choiceId];
    }
}
