<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCourtTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_court_type(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)->post(route('admin.court-types.store'), [
            'name' => 'Sân thi đấu', 'description' => 'Tiêu chuẩn giải đấu.',
        ])->assertRedirect(route('admin.court-types.index'));

        $type = CourtType::where('name', 'Sân thi đấu')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.court-types.update', $type), [
            'name' => 'Sân thi đấu VIP', 'description' => 'Đã cập nhật.',
        ])->assertRedirect(route('admin.court-types.index'));
        $this->assertDatabaseHas('court_types', ['id' => $type->id, 'name' => 'Sân thi đấu VIP']);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        CourtType::create(['name' => 'Sân tiêu chuẩn']);

        $this->actingAs($admin)->post(route('admin.court-types.store'), ['name' => 'Sân tiêu chuẩn'])
            ->assertSessionHasErrors('name');
    }

    public function test_type_used_by_court_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Đang sử dụng']);
        Court::create(['code' => 'TYPE-USED', 'name' => 'Sân đang dùng loại', 'court_type_id' => $type->id]);

        $this->actingAs($admin)->deleteJson(route('admin.court-types.destroy', $type))->assertUnprocessable();
        $this->assertDatabaseHas('court_types', ['id' => $type->id]);
    }

    public function test_unused_type_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Không sử dụng']);

        $this->actingAs($admin)->delete(route('admin.court-types.destroy', $type))
            ->assertRedirect(route('admin.court-types.index'));
        $this->assertDatabaseMissing('court_types', ['id' => $type->id]);
    }
}
