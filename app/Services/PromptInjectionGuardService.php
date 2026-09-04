<?php

namespace App\Services;

class PromptInjectionGuardService
{
    /**
     * Detect explicit attempts to override the assistant or extract secrets.
     * Business questions are intentionally not classified here.
     */
    public function inspect(string $message): array
    {
        $normalized = mb_strtolower($this->withoutAccents($message));

        $patterns = [
            'instruction_override' => '/\b(ignore|disregard|forget|bo qua|quen)\b.{0,50}\b(instruction|instructions|rule|rules|system|developer|chi dan|quy tac|prompt)\b/u',
            'secret_extraction' => '/\b(show|reveal|print|display|dump|hien thi|tiet lo|doc|xuat)\b.{0,50}\b(system prompt|developer message|api key|secret|prompt an|chi dan he thong)\b/u',
            'role_override' => '/\b(you are now|act as|switch role|dong vai|hay la|tro thanh)\b.{0,40}\b(system|developer|admin|root|unrestricted|khong gioi han)\b/u',
            'jailbreak' => '/\b(jailbreak|developer mode|do anything now|dan mode|prompt injection)\b/u',
            'cross_user_data' => '/\b(du lieu|booking|thong tin)\b.{0,40}\b(nguoi dung khac|khach khac|tat ca nguoi dung|tat ca khach hang)\b/u',
            'role_markup' => '/(<\/?system>|<\/?developer>|\[inst\]|###\s*(system|developer))/u',
        ];

        foreach ($patterns as $reason => $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return ['blocked' => true, 'reason' => $reason];
            }
        }

        return ['blocked' => false, 'reason' => null];
    }

    public function blockedResponse(string $reason): array
    {
        return [
            'understood' => true,
            'answer' => 'Mình không thể thay đổi quy tắc hệ thống, tiết lộ thông tin bảo mật hoặc dữ liệu của người khác. Bạn có thể hỏi mình về sân, giá, lịch trống hoặc booking của chính bạn.',
            'suggestions' => ['Tìm sân gần tôi', 'Kiểm tra booking của tôi'],
            'intent' => 'FAQ',
            'engine' => 'security-guard',
            'pipeline_stage' => 'security_guard',
            'security_flag' => true,
            'security_reason' => $reason,
        ];
    }

    private function withoutAccents(string $value): string
    {
        return strtr($value, [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a', 'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'đ' => 'd',
        ]);
    }
}
