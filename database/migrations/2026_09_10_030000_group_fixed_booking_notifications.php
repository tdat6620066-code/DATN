<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', fn (Blueprint $table) => $table->unsignedBigInteger('grouped_into_id')->nullable()->index());
        // Keep historical rows for rollback/audit, but show only one notification per group event.
        DB::table('fixed_bookings')->orderBy('id')->chunkById(100, function ($groups) {
            foreach ($groups as $group) {
                $bookings = DB::table('bookings')->where('fixed_booking_id', $group->id)->get(['id', 'total_amount']);
                foreach (['CREATED'=>'booking-created:', 'PAID'=>'payment:PAID:', 'FAILED'=>'payment:FAILED:', 'EXPIRED'=>'booking-status:EXPIRED:'] as $event => $prefix) {
                    // Legacy payments were independent and must keep their individual payment notices.
                    if ($group->status === 'LEGACY' && $event !== 'CREATED') continue;
                    $key = 'fixed-booking:'.$event.':'.$group->id;
                    $keys = $bookings->map(fn ($b) => $prefix.$b->id)->push($key);
                    $rows = DB::table('user_notifications')->where('user_id', $group->user_id)->whereIn('unique_key', $keys)->orderByDesc('id')->get();
                    if ($rows->isEmpty()) continue;
                    $keep = $rows->firstWhere('unique_key', $key) ?? $rows->first();
                    $label = ['CREATED'=>'Đã tạo lịch cố định', 'PAID'=>'Lịch cố định đã thanh toán', 'FAILED'=>'Thanh toán lịch cố định chưa thành công', 'EXPIRED'=>'Lịch cố định hết hạn giữ chỗ'][$event];
                    DB::table('user_notifications')->where('id', $keep->id)->update([
                        'unique_key' => $key, 'title' => $label,
                        'content' => 'Đơn '.$group->code.' · '.$bookings->count().' buổi · Tổng '.number_format($group->total_price ?? $bookings->sum('total_amount'), 0, ',', '.').'đ. '.$label.'.',
                        'action_url' => url('/booking/fixed/'.$group->id),
                        'is_read' => $rows->every(fn ($row) => (bool) $row->is_read),
                        'read_at' => $rows->every(fn ($row) => (bool) $row->is_read) ? $keep->read_at : null,
                    ]);
                    DB::table('user_notifications')->whereIn('id', $rows->pluck('id')->reject(fn ($id) => $id === $keep->id))->update(['grouped_into_id' => $keep->id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['grouped_into_id']);
            $table->dropColumn('grouped_into_id');
        });
    }
};
