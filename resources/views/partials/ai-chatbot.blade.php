<style>
.ai-chat-launcher{position:fixed;right:24px;bottom:92px;z-index:1050;width:58px;height:58px;border:0;border-radius:50%;display:grid;place-items:center;color:#fff;background:#0ea36b;box-shadow:0 12px 32px rgba(8,74,63,.32);font-size:25px}.ai-chat-panel{position:fixed;right:24px;bottom:162px;z-index:1050;width:min(430px,calc(100vw - 24px));height:min(680px,calc(100vh - 180px));display:none;flex-direction:column;overflow:hidden;background:#fff;border:1px solid #dce5e3;border-radius:20px;box-shadow:0 22px 65px rgba(2,32,40,.24)}.ai-chat-panel.is-open{display:flex}.ai-chat-header{display:flex;align-items:center;gap:11px;padding:15px 16px;color:#fff;background:linear-gradient(135deg,#082c3e,#0b8a5a)}.ai-chat-avatar{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:rgba(255,255,255,.16);font-size:20px}.ai-chat-title{flex:1}.ai-chat-title strong,.ai-chat-title small{display:block}.ai-chat-title small{color:#c9f8e8}.ai-chat-close{border:0;background:transparent;color:#fff;font-size:21px}.ai-chat-messages{flex:1;overflow-y:auto;padding:16px;background:#f5f8f7}.ai-chat-message{display:flex;margin-bottom:12px}.ai-chat-message.user{justify-content:flex-end}.ai-chat-bubble{max-width:84%;padding:10px 13px;border-radius:15px;color:#24343a;background:#fff;box-shadow:0 2px 8px rgba(8,44,62,.06);white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.45}.ai-chat-message.user .ai-chat-bubble{color:#fff;background:#0ea36b}.ai-chat-suggestions{display:flex;flex-wrap:wrap;gap:7px;margin:2px 0 14px}.ai-chat-suggestion{padding:7px 10px;border:1px solid #9bd8c3;border-radius:999px;color:#087653;background:#fff;font-size:12px}.ai-chat-tools{display:flex;gap:7px;padding:9px 12px 0;border-top:1px solid #e4ebe9;overflow-x:auto}.ai-chat-tool{flex:0 0 auto;border:1px solid #d7e5e0;border-radius:999px;padding:6px 10px;color:#31524a;background:#fff;font-size:12px}.ai-chat-form{display:flex;gap:8px;padding:12px;border-top:1px solid #e4ebe9}.ai-chat-input{flex:1;min-width:0;padding:10px 13px;border:1px solid #cfdad7;border-radius:12px;outline:none}.ai-chat-send{width:42px;height:42px;border:0;border-radius:12px;color:#fff;background:#0ea36b}.ai-chat-send:disabled{opacity:.55}@media(max-width:520px){.ai-chat-launcher{right:16px;bottom:84px}.ai-chat-panel{right:16px;bottom:152px;height:calc(100vh - 176px)}}
</style>

<button type="button" class="ai-chat-launcher" id="ai-chat-launcher" aria-label="Mở trợ lý AI"><i class="bi bi-chat-dots-fill"></i></button>
<section class="ai-chat-panel" id="ai-chat-panel" aria-hidden="true">
 <header class="ai-chat-header"><div class="ai-chat-avatar"><i class="bi bi-robot"></i></div><div class="ai-chat-title"><strong>Trợ lý SmashZone</strong><small>Hỗ trợ đặt sân và khuyến mãi</small></div><button type="button" class="ai-chat-close" id="ai-chat-close"><i class="bi bi-x-lg"></i></button></header>
 <div class="ai-chat-messages" id="ai-chat-messages"><div class="ai-chat-message bot"><div class="ai-chat-bubble">Xin chào {{ Auth::user()->name }}! Mình có thể giúp bạn tìm sân, xem giá thuê hoặc khuyến mãi.</div></div><div class="ai-chat-suggestions" id="ai-chat-suggestions"><button class="ai-chat-suggestion">Giá thuê sân bao nhiêu?</button><button class="ai-chat-suggestion">Có khuyến mãi nào?</button><button class="ai-chat-suggestion">Tôi muốn đặt sân</button></div></div>
 <div class="ai-chat-tools"><button class="ai-chat-tool" data-quick="Thông báo của tôi">🔔 Thông báo</button><button class="ai-chat-tool" data-quick="Gợi ý Top 5 sân phù hợp với tôi">✨ Dành cho bạn</button><button class="ai-chat-tool" data-quick="Ngày mai còn sân không?">📅 Ngày mai</button><button class="ai-chat-tool" data-quick="Tối nay còn sân không?">🌙 Tối nay</button></div>
 <form class="ai-chat-form" id="ai-chat-form"><input class="ai-chat-input" id="ai-chat-input" maxlength="500" placeholder="Nhập câu hỏi..." autocomplete="off"><button class="ai-chat-send" id="ai-chat-send"><i class="bi bi-send-fill"></i></button></form>
</section>

<script>
(()=>{const launcher=document.getElementById('ai-chat-launcher'),panel=document.getElementById('ai-chat-panel'),close=document.getElementById('ai-chat-close'),form=document.getElementById('ai-chat-form'),input=document.getElementById('ai-chat-input'),send=document.getElementById('ai-chat-send'),messages=document.getElementById('ai-chat-messages');if(!launcher||launcher.dataset.ready)return;launcher.dataset.ready='1';const endpoint=@json(route('api.ai.chat.stream'));const csrf=document.querySelector('meta[name="csrf-token"]')?.content||@json(csrf_token());const scroll=()=>messages.scrollTop=messages.scrollHeight;const bubble=(text,role='bot')=>{const row=document.createElement('div');row.className=`ai-chat-message ${role}`;const el=document.createElement('div');el.className='ai-chat-bubble';el.textContent=text;row.appendChild(el);messages.appendChild(row);scroll();return el};const suggestions=(items=[])=>{document.getElementById('ai-chat-suggestions')?.remove();if(!items.length)return;const box=document.createElement('div');box.className='ai-chat-suggestions';box.id='ai-chat-suggestions';items.forEach(text=>{const btn=document.createElement('button');btn.type='button';btn.className='ai-chat-suggestion';btn.textContent=text;btn.onclick=()=>ask(text);box.appendChild(btn)});messages.appendChild(box);scroll()};async function ask(text){text=(text||'').trim();if(!text||send.disabled)return;bubble(text,'user');input.value='';send.disabled=true;const answer=bubble('Đang trả lời...');let received='';try{const response=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/x-ndjson','X-CSRF-TOKEN':csrf},body:JSON.stringify({message:text})});if(!response.ok&&!response.body)throw new Error();const reader=response.body.getReader(),decoder=new TextDecoder();let buffer='';while(true){const {done,value}=await reader.read();buffer+=decoder.decode(value||new Uint8Array(),{stream:!done});const lines=buffer.split('\n');buffer=lines.pop()||'';for(const line of lines){if(!line.trim())continue;const event=JSON.parse(line);if(event.type==='delta'){received+=event.text;answer.textContent=received}else if(event.type==='done'){suggestions(event.data?.suggestions||[])}else if(event.type==='error'){throw new Error(event.message)}}if(done)break}if(!received)answer.textContent='Trợ lý chưa có câu trả lời.'}catch(error){answer.textContent=error.message||'Trợ lý tạm thời không phản hồi. Vui lòng thử lại sau.'}finally{send.disabled=false;input.focus();scroll()}}launcher.onclick=()=>{panel.classList.add('is-open');panel.setAttribute('aria-hidden','false');input.focus()};close.onclick=()=>{panel.classList.remove('is-open');panel.setAttribute('aria-hidden','true')};form.onsubmit=e=>{e.preventDefault();ask(input.value)};document.addEventListener('click',e=>{const btn=e.target.closest('.ai-chat-suggestion,[data-quick]');if(btn)ask(btn.dataset.quick||btn.textContent)})})();
</script>
<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('.ai-chat-suggestion');
    if (!button) return;
    const text = button.textContent.trim().toLocaleLowerCase('vi-VN');
    const routes = {
        'mở trung tâm thông báo': @json(route('notifications.index')),
        'cài đặt thông báo': @json(route('notification-settings.edit')),
    };
    if (routes[text]) {
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.href = routes[text];
    }
}, true);
</script>
<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('.ai-chat-suggestion');
    if (button && button.textContent.trim().toLocaleLowerCase('vi-VN') === 'đặt sân ngay') {
        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.href = @json(route('bookings.create'));
    }
}, true);
</script>
