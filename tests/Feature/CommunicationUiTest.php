<?php

namespace Tests\Feature;

use App\Models\{Notification,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_promotions_and_services_together(): void
    {
        \App\Models\Promotion::create(['title' => 'Weekend offer', 'description' => 'Play together', 'status' => 'ACTIVE', 'start_at' => now()->subDay(), 'end_at' => now()->addDay()]);
        \App\Models\ServiceItem::create(['code' => 'HOME-WATER', 'name' => 'Nước uống', 'price' => 15000, 'is_active' => true]);
        foreach ([null, User::factory()->create(['role' => 'CUSTOMER'])] as $user) {
            if ($user) $this->actingAs($user);
            $this->get(route('home'))->assertOk()->assertSee('Weekend offer')->assertSee('Nước uống')
                ->assertSee('15.000')->assertSee('bi-cup-straw')->assertDontSee('@foreach')->assertDontSee('@php');
        }
    }

    public function test_notification_open_rejects_lookalike_origins_and_accepts_internal_links(): void
    {
        $user = User::factory()->create();
        $notice = Notification::create(['user_id' => $user->id, 'title' => 'Notice', 'content' => 'Content', 'type' => 'SYSTEM', 'action_url' => url('/').'.example.org/trap']);
        $this->actingAs($user)->get(route('notifications.open', $notice))->assertRedirect(route('notifications.index'));
        $notice->update(['action_url' => route('profile')]);
        $this->get(route('notifications.open', $notice))->assertRedirect(route('profile'));
    }

    public function test_discount_question_only_returns_current_promotions(): void
    {
        $user = User::factory()->create(['role' => 'CUSTOMER']);
        foreach ([['Current offer', now()->subDay(), now()->addDay()], ['Expired offer', now()->subDays(3), now()->subDay()], ['Future offer', now()->addDay(), now()->addDays(3)]] as [$title, $start, $end]) {
            \App\Models\Promotion::create(['title' => $title, 'start_at' => $start, 'end_at' => $end, 'status' => 'ACTIVE']);
        }
        $response = $this->actingAs($user)->post(route('api.ai.chat.stream'), ['message' => 'Có mã giảm giá không?'])->assertOk();
        $answer = collect(explode("\n", trim($response->streamedContent())))
            ->map(fn ($line) => json_decode($line, true))->pluck('text')->implode('');
        $this->assertStringContainsString('Current offer', $answer);
        $this->assertStringNotContainsString('Expired offer', $answer);
        $this->assertStringNotContainsString('Future offer', $answer);
    }

    public function test_guest_home_has_no_private_notifications_or_authenticated_chat(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-notifications',false)->assertDontSee('id="ai-chat-panel"',false)->assertSee(route('login'),false);
    }

    public function test_dropdown_scopes_notifications_and_preserves_read_forms_for_all_roles(): void
    {
        foreach (['CUSTOMER','EMPLOYEE','ADMIN'] as $role) {
            $user = User::factory()->create(['role'=>$role,'permissions'=>['employee.dashboard']]);
            $notice = Notification::create(['user_id'=>$user->id,'title'=>'Thông báo riêng '.$role,'content'=>'Lịch sân đã cập nhật.','type'=>'BOOKING','is_read'=>false]);
            $other = User::factory()->create();
            Notification::create(['user_id'=>$other->id,'title'=>'Nội dung người khác','content'=>'Riêng tư','type'=>'SYSTEM','is_read'=>false]);
            $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk()
                ->assertSee('Thông báo riêng '.$role)->assertDontSee('Nội dung người khác')
                ->assertSee('data-unread-count="1"',false)->assertSee(route('notifications.read',$notice),false)
                ->assertSee('value="PATCH"',false)->assertSee('name="_token"',false);
            if (getenv('FINAL_UI_PREVIEW')) file_put_contents(storage_path('app/final-'.strtolower($role).'.html'),str_replace(['http://127.0.0.1:8000','http://localhost'],'http://127.0.0.1:8765',$response->getContent()));
        }
    }

    public function test_read_actions_cannot_mutate_another_users_notification(): void
    {
        $user=User::factory()->create();$other=User::factory()->create();
        $own=Notification::create(['user_id'=>$user->id,'title'=>'Của tôi','content'=>'Nội dung','type'=>'SYSTEM','is_read'=>false]);
        $private=Notification::create(['user_id'=>$other->id,'title'=>'Người khác','content'=>'Nội dung','type'=>'SYSTEM','is_read'=>false]);
        $this->actingAs($user)->patch(route('notifications.read',$private))->assertForbidden();
        $this->patch(route('notifications.read',$own))->assertRedirect();
        $this->assertTrue($own->fresh()->is_read);
        $own->update(['is_read'=>false]);
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertTrue($own->fresh()->is_read);$this->assertFalse($private->fresh()->is_read);
    }

    public function test_customer_assistant_is_single_instance_and_stream_contract_is_unchanged(): void
    {
        $user=User::factory()->create(['role'=>'CUSTOMER']);
        $page=$this->actingAs($user)->get(route('home'))->assertOk()->assertSee('SmashZone Assistant')->assertSee('Đặt như lần trước');
        $this->assertSame(1,substr_count($page->getContent(),'id="ai-chat-panel"'));
        $this->get(route('courts.index'))->assertOk()->assertSee('id="ai-chat-panel"',false);
        $stream=$this->post(route('api.ai.chat.stream'),['message'=>'Giá sân hôm nay'])->assertOk();
        $this->assertStringContainsString('"type":"delta"',$stream->streamedContent());
        $this->assertStringContainsString('"type":"done"',$stream->streamedContent());
        $this->postJson(route('api.ai.chat.stream'),['message'=>str_repeat('x',501)])->assertUnprocessable();
    }
}
