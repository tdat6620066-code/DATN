<style>
    .ai-chat-launcher {
        position: fixed; right: 24px; bottom: 24px; z-index: 1050;
        width: 58px; height: 58px; border: 0; border-radius: 50%;
        display: grid; place-items: center; color: #fff; background: #0ea36b;
        box-shadow: 0 12px 32px rgba(8, 74, 63, .32); font-size: 25px;
        transition: transform .2s, background .2s;
    }
    .ai-chat-launcher:hover { background: #0b8a5a; transform: translateY(-2px); }
    .ai-chat-panel {
        position: fixed; right: 24px; bottom: 94px; z-index: 1050;
        width: min(430px, calc(100vw - 24px)); height: min(680px, calc(100vh - 108px));
        display: none; flex-direction: column; overflow: hidden;
        background: #fff; border: 1px solid #dce5e3; border-radius: 20px;
        box-shadow: 0 22px 65px rgba(2, 32, 40, .24);
    }
    .ai-chat-panel.is-open { display: flex; }
    .ai-chat-header { display: flex; align-items: center; gap: 11px; padding: 15px 16px; color: #fff; background: linear-gradient(135deg, #082c3e, #0b8a5a); }
    .ai-chat-avatar { width: 40px; height: 40px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 12px; background: rgba(255,255,255,.16); font-size: 20px; }
    .ai-chat-title { flex: 1; line-height: 1.25; }
    .ai-chat-title strong { display: block; font-size: 15px; }
    .ai-chat-title small { color: #c9f8e8; font-size: 12px; }
    .ai-chat-close { border: 0; background: transparent; color: #fff; font-size: 21px; padding: 4px; }
    .ai-chat-messages { flex: 1; overflow-y: auto; padding: 16px; background: #f5f8f7; }
    .ai-chat-message { display: flex; margin-bottom: 12px; }
    .ai-chat-message.user { justify-content: flex-end; }
    .ai-chat-bubble { max-width: 84%; padding: 10px 13px; border-radius: 15px; color: #24343a; background: #fff; box-shadow: 0 2px 8px rgba(8, 44, 62, .06); white-space: pre-wrap; overflow-wrap: anywhere; font-size: 14px; line-height: 1.45; }
    .ai-chat-message.user .ai-chat-bubble { color: #fff; background: #0ea36b; border-bottom-right-radius: 5px; }
    .ai-chat-message.bot .ai-chat-bubble { border-bottom-left-radius: 5px; }
    .ai-chat-suggestions { display: flex; flex-wrap: wrap; gap: 7px; margin: 2px 0 14px; }
    .ai-chat-suggestion { padding: 7px 10px; border: 1px solid #9bd8c3; border-radius: 999px; color: #087653; background: #fff; font-size: 12px; }
    .ai-chat-suggestion:hover { background: #e9faf4; }
    .ai-chat-cards { display: grid; gap: 9px; margin: 0 0 14px; }
    .ai-chat-card { display: block; padding: 12px; border: 1px solid #dce8e4; border-radius: 14px; color: #173b35; background: #fff; text-decoration: none; box-shadow: 0 3px 12px rgba(8,44,62,.06); }
    .ai-chat-card-image { width: calc(100% + 24px); height: 138px; margin: -12px -12px 11px; object-fit: cover; border-radius: 14px 14px 0 0; background: linear-gradient(135deg,#dff5ec,#cde5df); }
    .ai-chat-card-head { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; }
    .ai-chat-rating { flex:0 0 auto; color:#a76b00; font-size:12px; font-weight:700; }
    .ai-chat-match { display:inline-flex; margin-top:7px; padding:4px 8px; border-radius:999px; color:#087653; background:#e8f8f2; font-size:11px; font-weight:800; }
    .ai-chat-slots { display:flex; gap:6px; overflow-x:auto; padding:9px 0 2px; scrollbar-width:thin; }
    .ai-chat-slot { flex:0 0 auto; border:1px solid #b5ded0; border-radius:9px; padding:6px 8px; color:#087653; background:#f7fffc; font-size:11px; font-weight:700; }
    .ai-chat-slot:hover { color:#fff; background:#0ea36b; }
    .ai-chat-card strong, .ai-chat-card span { display: block; }
    .ai-chat-card small { display: block; margin-top: 5px; color: #667b76; }
    .ai-chat-card .price { margin-top: 7px; color: #07845d; font-weight: 700; }
    .ai-chat-feedback { display:flex; gap:5px; margin:-8px 0 12px; }
    .ai-chat-feedback button { border:0; border-radius:8px; padding:4px 8px; background:#eaf1ef; }
    .ai-chat-typing { display: inline-flex; gap: 4px; align-items: center; }
    .ai-chat-typing i { width: 6px; height: 6px; border-radius: 50%; background: #7a918b; animation: ai-chat-pulse 1s infinite alternate; }
    .ai-chat-typing i:nth-child(2) { animation-delay: .2s; }
    .ai-chat-typing i:nth-child(3) { animation-delay: .4s; }
    @keyframes ai-chat-pulse { to { opacity: .25; transform: translateY(-2px); } }
    .ai-chat-form { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e4ebe9; background: #fff; }
    .ai-chat-input { flex: 1; min-width: 0; padding: 10px 13px; border: 1px solid #cfdad7; border-radius: 12px; outline: none; font-size: 14px; }
    .ai-chat-input:focus { border-color: #0ea36b; box-shadow: 0 0 0 3px rgba(14,163,107,.12); }
    .ai-chat-send { width: 42px; height: 42px; border: 0; border-radius: 12px; color: #fff; background: #0ea36b; }
    .ai-chat-send:disabled { opacity: .55; }
    .ai-chat-tools { display:flex; align-items:center; gap:7px; padding:9px 12px 0; border-top:1px solid #e4ebe9; background:#fff; overflow-x:auto; }
    .ai-chat-tool { flex:0 0 auto; border:1px solid #d7e5e0; border-radius:999px; padding:6px 10px; color:#31524a; background:#fff; font-size:12px; }
    .ai-chat-tool:hover { border-color:#0ea36b; color:#087653; }
    .ai-chat-calendar { position:absolute; right:12px; bottom:118px; z-index:4; width:286px; padding:13px; border:1px solid #dce8e4; border-radius:16px; background:#fff; box-shadow:0 16px 40px rgba(2,32,40,.2); }
    .ai-chat-calendar[hidden] { display:none; }
    .ai-chat-calendar-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:9px; }
    .ai-chat-date { width:100%; border:1px solid #cfdad7; border-radius:10px; padding:9px; }
    .ai-chat-date-submit { width:100%; margin-top:8px; border:0; border-radius:10px; padding:9px; color:#fff; background:#0ea36b; font-weight:700; }
    .ai-payment-modal { position:fixed; inset:0; z-index:1080; display:none; place-items:center; padding:18px; background:rgba(2,25,34,.58); backdrop-filter:blur(4px); }
    .ai-payment-modal.is-open { display:grid; }
    .ai-payment-dialog { width:min(420px,100%); border-radius:22px; padding:24px; background:#fff; box-shadow:0 24px 80px rgba(0,0,0,.3); }
    .ai-payment-icon { width:52px; height:52px; display:grid; place-items:center; border-radius:16px; color:#fff; background:#0ea36b; font-size:24px; }
    .ai-payment-actions { display:grid; gap:9px; margin-top:18px; }
    .ai-payment-primary,.ai-payment-secondary { display:block; border-radius:12px; padding:11px 14px; text-align:center; text-decoration:none; font-weight:700; }
    .ai-payment-primary { color:#fff; background:#0ea36b; }
    .ai-payment-secondary { border:1px solid #d7e5e0; color:#31524a; background:#fff; }
    @media (max-width: 520px) {
        .ai-chat-launcher { right: 16px; bottom: 16px; }
        .ai-chat-panel { right: 16px; bottom: 84px; height: calc(100vh - 110px); }
    }
</style>

<button type="button" class="ai-chat-launcher" id="ai-chat-launcher" aria-label="Mở trợ lý AI" aria-expanded="false">
    <i class="bi bi-chat-dots-fill"></i>
</button>

<section class="ai-chat-panel" id="ai-chat-panel" aria-label="Trợ lý AI SmashZone" aria-hidden="true">
    <header class="ai-chat-header">
        <div class="ai-chat-avatar"><i class="bi bi-robot"></i></div>
        <div class="ai-chat-title"><strong>Trợ lý SmashZone</strong><small>Hỗ trợ đặt sân và khuyến mãi</small></div>
        <button type="button" class="ai-chat-close" id="ai-chat-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
    </header>
    <div class="ai-chat-messages" id="ai-chat-messages" aria-live="polite">
        <div class="ai-chat-message bot"><div class="ai-chat-bubble">Xin chào {{ Auth::user()->name }}! Mình có thể giúp bạn tìm sân, xem giá thuê hoặc khuyến mãi.</div></div>
        <div class="ai-chat-suggestions" id="ai-chat-suggestions">
            <button type="button" class="ai-chat-suggestion">Giá thuê sân bao nhiêu?</button>
            <button type="button" class="ai-chat-suggestion">Có khuyến mãi nào?</button>
            <button type="button" class="ai-chat-suggestion">Hướng dẫn đặt sân</button>
        </div>
    </div>
    <div class="ai-chat-tools">
        <button type="button" class="ai-chat-tool" data-quick="Gợi ý Top 5 sân phù hợp với tôi"><i class="bi bi-stars me-1"></i>Dành cho bạn</button>
        <button type="button" class="ai-chat-tool" id="ai-chat-calendar-toggle"><i class="bi bi-calendar3 me-1"></i>Chọn ngày</button>
        <button type="button" class="ai-chat-tool" data-quick="Tối nay còn sân không?"><i class="bi bi-moon-stars me-1"></i>Tối nay</button>
        <button type="button" class="ai-chat-tool" data-quick="5 đơn gần nhất của tôi"><i class="bi bi-receipt me-1"></i>Booking của tôi</button>
    </div>
    <div class="ai-chat-calendar" id="ai-chat-calendar" hidden>
        <div class="ai-chat-calendar-head"><strong>Chọn ngày chơi</strong><button type="button" class="btn-close" id="ai-chat-calendar-close" aria-label="Đóng"></button></div>
        <input class="ai-chat-date" id="ai-chat-date" type="date" min="{{ today()->toDateString() }}" value="{{ today()->addDay()->toDateString() }}">
        <button class="ai-chat-date-submit" id="ai-chat-date-submit" type="button">Tìm sân trống</button>
    </div>
    <form class="ai-chat-form" id="ai-chat-form">
        <input class="ai-chat-input" id="ai-chat-input" maxlength="500" autocomplete="off" placeholder="Nhập câu hỏi..." aria-label="Câu hỏi">
        <button class="ai-chat-send" id="ai-chat-send" type="submit" aria-label="Gửi"><i class="bi bi-send-fill"></i></button>
    </form>
</section>

<div class="ai-payment-modal" id="ai-payment-modal" aria-hidden="true">
    <div class="ai-payment-dialog" role="dialog" aria-modal="true" aria-labelledby="ai-payment-title">
        <div class="ai-payment-icon"><i class="bi bi-check2"></i></div>
        <h2 class="h5 mt-3 mb-1" id="ai-payment-title">Booking đã được tạo</h2>
        <p class="text-muted mb-0" id="ai-payment-copy">Mở chi tiết booking để kiểm tra và chọn phương thức thanh toán an toàn.</p>
        <div class="ai-payment-actions"><a class="ai-payment-primary" id="ai-payment-open" href="#">Xem booking & thanh toán</a><button class="ai-payment-secondary" id="ai-payment-later" type="button">Thanh toán sau</button></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const launcher = document.getElementById('ai-chat-launcher');
    const panel = document.getElementById('ai-chat-panel');
    const close = document.getElementById('ai-chat-close');
    const form = document.getElementById('ai-chat-form');
    const input = document.getElementById('ai-chat-input');
    const send = document.getElementById('ai-chat-send');
    const messages = document.getElementById('ai-chat-messages');
    const calendar = document.getElementById('ai-chat-calendar');
    const paymentModal = document.getElementById('ai-payment-modal');
    const endpoint = @json(route('api.ai.chat'));
    const streamEndpoint = @json(route('api.ai.chat.stream'));
    const feedbackEndpoint = @json(route('api.ai.chat.feedback', ['chatbotLog' => '__ID__']));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    const toggle = (open) => {
        panel.classList.toggle('is-open', open);
        panel.setAttribute('aria-hidden', String(!open));
        launcher.setAttribute('aria-expanded', String(open));
        launcher.innerHTML = open ? '<i class="bi bi-chevron-down"></i>' : '<i class="bi bi-chat-dots-fill"></i>';
        if (open) setTimeout(() => input.focus(), 50);
    };
    const addMessage = (text, type, animate = false) => {
        const row = document.createElement('div');
        row.className = `ai-chat-message ${type}`;
        const bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble';
        if (!animate) bubble.textContent = text;
        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        if (animate) {
            let index = 0;
            const timer = window.setInterval(() => {
                bubble.textContent += text.slice(index, index + 3);
                index += 3;
                messages.scrollTop = messages.scrollHeight;
                if (index >= text.length) window.clearInterval(timer);
            }, 18);
        }
        return row;
    };
    const renderCards = (cards) => {
        if (!Array.isArray(cards) || !cards.length) return;
        const wrap = document.createElement('div'); wrap.className = 'ai-chat-cards';
        cards.forEach((card) => {
            const item = document.createElement('a'); item.className = 'ai-chat-card'; item.href = card.url || '#';
            const price = card.price_from === null ? 'Chưa cập nhật giá' : `Từ ${Number(card.price_from).toLocaleString('vi-VN')}đ`;
            item.innerHTML = `<strong></strong><span></span><small></small><span class="price"></span>`;
            item.children[0].textContent = card.title || '';
            item.children[1].textContent = card.subtitle || '';
            item.children[2].textContent = card.meta || '';
            item.children[3].textContent = price;
            wrap.appendChild(item);
        });
        messages.appendChild(wrap);
    };
    const renderRichCards = (cards) => {
        if (!Array.isArray(cards) || !cards.length) return;
        const wrap = document.createElement('div'); wrap.className = 'ai-chat-cards';
        cards.forEach((card) => {
            const item = document.createElement('article'); item.className = 'ai-chat-card';
            if (card.image_url) {
                const image = document.createElement('img'); image.className = 'ai-chat-card-image'; image.src = card.image_url; image.alt = card.title || 'Sân cầu lông'; image.loading = 'lazy'; item.appendChild(image);
            }
            const head = document.createElement('div'); head.className = 'ai-chat-card-head';
            const title = document.createElement('strong'); title.textContent = card.title || ''; head.appendChild(title);
            if (card.rating) { const rating = document.createElement('span'); rating.className = 'ai-chat-rating'; rating.textContent = `★ ${card.rating}`; head.appendChild(rating); }
            item.appendChild(head);
            const subtitle = document.createElement('small'); subtitle.textContent = card.subtitle || ''; item.appendChild(subtitle);
            const price = document.createElement('span'); price.className = 'price'; price.textContent = card.price_from === null ? 'Chưa cập nhật giá' : `Từ ${Number(card.price_from).toLocaleString('vi-VN')}đ/giờ`; item.appendChild(price);
            if (card.match_percent) { const match = document.createElement('span'); match.className = 'ai-chat-match'; match.textContent = `${card.match_percent}% phù hợp`; item.appendChild(match); }
            if (Array.isArray(card.slots) && card.slots.length) {
                const slots = document.createElement('div'); slots.className = 'ai-chat-slots';
                card.slots.forEach((slot) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'ai-chat-slot ai-chat-suggestion'; button.dataset.action = slot.action; button.dataset.choiceId = slot.id; button.textContent = `${slot.start_time}–${slot.end_time}`; slots.appendChild(button); });
                item.appendChild(slots);
            }
            if (card.url) { const detail = document.createElement('a'); detail.href = card.url; detail.className = 'd-inline-block mt-2 small fw-bold text-success text-decoration-none'; detail.textContent = 'Xem chi tiết sân →'; item.appendChild(detail); }
            wrap.appendChild(item);
        });
        messages.appendChild(wrap); messages.scrollTop = messages.scrollHeight;
    };
    const showPaymentModal = (data) => {
        document.getElementById('ai-payment-open').href = data.booking_url || data.redirect_url;
        document.getElementById('ai-payment-copy').textContent = data.booking_code ? `Booking ${data.booking_code} đang giữ chỗ tạm thời. Vui lòng kiểm tra và thanh toán trong trang chi tiết.` : 'Mở chi tiết booking để kiểm tra và chọn phương thức thanh toán an toàn.';
        paymentModal.classList.add('is-open'); paymentModal.setAttribute('aria-hidden', 'false');
    };
    const renderFeedback = (logId) => {
        if (!logId) return;
        const wrap = document.createElement('div'); wrap.className = 'ai-chat-feedback';
        [['UP','👍'],['DOWN','👎']].forEach(([rating,label]) => {
            const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
            button.addEventListener('click', async () => {
                await fetch(feedbackEndpoint.replace('__ID__', logId), {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({rating})});
                wrap.textContent = 'Cảm ơn bạn đã phản hồi.';
            }); wrap.appendChild(button);
        }); messages.appendChild(wrap);
    };
    const renderSuggestions = (items) => {
        document.getElementById('ai-chat-suggestions')?.remove();
        if (!Array.isArray(items) || !items.length) return;
        const wrap = document.createElement('div');
        wrap.id = 'ai-chat-suggestions';
        wrap.className = 'ai-chat-suggestions';
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'ai-chat-suggestion';
            button.textContent = typeof item === 'string' ? item : item.label;
            if (typeof item === 'object') {
                button.dataset.action = item.action;
                if (item.id) button.dataset.choiceId = item.id;
            }
            wrap.appendChild(button);
        });
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    };
    const ask = async (question) => {
        question = question.trim();
        if (!question || send.disabled) return;
        document.getElementById('ai-chat-suggestions')?.remove();
        addMessage(question, 'user'); input.value = ''; send.disabled = true;
        const typing = addMessage('', 'bot');
        typing.querySelector('.ai-chat-bubble').innerHTML = '<span class="ai-chat-typing"><i></i><i></i><i></i></span>';
        try {
            const response = await fetch(streamEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({message: question})
            });
            if (!response.ok && !response.headers.get('content-type')?.includes('application/x-ndjson')) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || 'Không thể kết nối trợ lý AI.');
            }
            typing.remove();
            const row = addMessage('', 'bot');
            const bubble = row.querySelector('.ai-chat-bubble');
            const reader = response.body.getReader(); const decoder = new TextDecoder(); let buffer = ''; let finalData = {};
            while (true) {
                const {value, done} = await reader.read(); if (done) break;
                buffer += decoder.decode(value, {stream:true});
                const lines = buffer.split('\n'); buffer = lines.pop();
                lines.filter(Boolean).forEach((line) => {
                    const event = JSON.parse(line);
                    if (event.type === 'delta') bubble.textContent += event.text;
                    if (event.type === 'done') finalData = event.data || {};
                    if (event.type === 'error') throw new Error(event.message);
                    messages.scrollTop = messages.scrollHeight;
                });
            }
            if (!response.ok) throw new Error('Không thể kết nối trợ lý AI.');
            renderRichCards(finalData.cards);
            renderFeedback(finalData.feedback_id);
            renderSuggestions(finalData.buttons?.length ? finalData.buttons : (finalData.choices?.length ? finalData.choices : finalData.suggestions));
        } catch (error) {
            typing.remove();
            addMessage(error.message || 'Trợ lý đang bận. Vui lòng thử lại sau.', 'bot');
        } finally { send.disabled = false; input.focus(); }
    };
    const performAction = async (button) => {
        if (send.disabled) return;
        document.getElementById('ai-chat-suggestions')?.remove();
        addMessage(button.textContent, 'user'); send.disabled = true;
        const typing = addMessage('', 'bot');
        typing.querySelector('.ai-chat-bubble').innerHTML = '<span class="ai-chat-typing"><i></i><i></i><i></i></span>';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({action: button.dataset.action, choice_id: button.dataset.choiceId || undefined})
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Không thể chọn khung giờ.');
            typing.remove();
            addMessage(payload.data.answer, 'bot', true);
            renderRichCards(payload.data.cards);
            renderFeedback(payload.data.feedback_id);
            if (payload.data.redirect_url) {
                showPaymentModal(payload.data);
                return;
            }
            renderSuggestions(payload.data.buttons?.length ? payload.data.buttons : (payload.data.choices?.length ? payload.data.choices : payload.data.suggestions));
        } catch (error) {
            typing.remove(); addMessage(error.message || 'Lựa chọn không còn hợp lệ.', 'bot');
        } finally { send.disabled = false; input.focus(); }
    };

    launcher.addEventListener('click', () => toggle(!panel.classList.contains('is-open')));
    close.addEventListener('click', () => toggle(false));
    document.getElementById('ai-chat-calendar-toggle').addEventListener('click', () => { calendar.hidden = !calendar.hidden; });
    document.getElementById('ai-chat-calendar-close').addEventListener('click', () => { calendar.hidden = true; });
    document.getElementById('ai-chat-date-submit').addEventListener('click', () => {
        const value = document.getElementById('ai-chat-date').value;
        if (!value) return;
        const [year, month, day] = value.split('-'); calendar.hidden = true; ask(`Tìm sân trống ngày ${day}/${month}/${year}`);
    });
    document.querySelectorAll('[data-quick]').forEach((button) => button.addEventListener('click', () => ask(button.dataset.quick)));
    document.getElementById('ai-payment-later').addEventListener('click', () => { paymentModal.classList.remove('is-open'); paymentModal.setAttribute('aria-hidden', 'true'); });
    paymentModal.addEventListener('click', (event) => { if (event.target === paymentModal) document.getElementById('ai-payment-later').click(); });
    form.addEventListener('submit', (event) => { event.preventDefault(); ask(input.value); });
    messages.addEventListener('click', (event) => {
        const button = event.target.closest('.ai-chat-suggestion');
        if (!button || button.tagName === 'A') return;
        if (button.dataset.action) performAction(button);
        else ask(button.textContent);
    });
});
</script>
