document.addEventListener('DOMContentLoaded', () => {
    const notices = document.querySelector('[data-notifications]');
    notices?.addEventListener('show.bs.dropdown',()=>{
        const bottom=notices.closest('header')?.getBoundingClientRect().bottom||70;
        notices.style.setProperty('--notification-top',`${Math.min(bottom+8,Math.max(80,innerHeight-260))}px`);
    });
    notices?.querySelectorAll('form').forEach(form => form.addEventListener('submit',event=>{
        if(form.getAttribute('aria-busy')==='true'){event.preventDefault();return;}
        form.setAttribute('aria-busy','true');form.querySelector('button').disabled=true;
    }));
    if (notices && window.Echo) {
        window.Echo.private(`users.${notices.dataset.user}`).listen('.customer.notification.created', notification => {
            const badge = notices.querySelector('[data-unread-count]');
            const count = Number(badge.dataset.unreadCount || 0) + 1;
            badge.dataset.unreadCount = String(count); badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = false;
            notices.querySelector('.sz-notification-trigger').setAttribute('aria-label', `Thông báo, ${count} chưa đọc`);
            notices.querySelector('[data-read-all]').disabled = false;
            notices.querySelector('[data-notification-empty]')?.remove();
            const item = document.createElement('article'); item.className = 'sz-notification-item is-unread';
            const content = document.createElement('div'); const link = document.createElement('a');
            link.className = 'sz-notification-title'; link.href = notices.dataset.allUrl; link.textContent = notification.title || 'Thông báo mới';
            const text = document.createElement('p'); text.textContent = notification.content || '';
            const time = document.createElement('time'); time.dateTime = new Date().toISOString(); time.textContent = 'Vừa xong · Chưa đọc';
            content.append(link,text,time); item.append(content); notices.querySelector('[data-notification-list]').prepend(item);
            const items = notices.querySelector('[data-notification-list]'); while (items.children.length > 6) items.lastElementChild.remove();
            document.querySelector('.sz-communication-toast')?.remove();
            const toast = document.createElement('div'); toast.className = 'sz-communication-toast'; toast.setAttribute('role','status');
            const close = document.createElement('button'); close.type='button'; close.className='btn-close float-end ms-2'; close.setAttribute('aria-label','Đóng thông báo'); close.addEventListener('click',()=>toast.remove());
            toast.append(close,link.cloneNode(true),text.cloneNode(true)); document.body.append(toast); setTimeout(()=>toast.remove(),10000);
        });
    }
    const panel = document.getElementById('ai-chat-panel');
    if (!panel) return;
    const launcher = document.getElementById('ai-chat-launcher');
    const input = panel.querySelector('#ai-chat-input'); const form = panel.querySelector('form');
    const messages = panel.querySelector('.ai-chat-messages'); const status = panel.querySelector('.ai-chat-status');
    let busy = false;
    const scroll = () => { messages.scrollTop = messages.scrollHeight; };
    const stamp = row => { const time=document.createElement('time'); const now=new Date();time.dateTime=now.toISOString();time.textContent=now.toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'});row.append(time); };
    stamp(messages.firstElementChild);
    const bubble = (text,role='bot') => { const row=document.createElement('div');row.className=`ai-chat-message ${role}`;const body=document.createElement('div');body.className='ai-chat-bubble';body.textContent=text;row.append(body);stamp(row);messages.append(row);scroll();return {row,body}; };
    const close = () => {panel.hidden=true;launcher.setAttribute('aria-expanded','false');launcher.focus();};
    launcher.addEventListener('click',()=>{if(!panel.hidden){close();return;}panel.hidden=false;launcher.setAttribute('aria-expanded','true');input.focus();scroll();});
    panel.querySelector('.ai-chat-close').addEventListener('click',close);
    panel.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();}});
    const connection = () => {panel.querySelector('[data-ai-connection]').textContent=navigator.onLine?'● Online':'○ Mất kết nối';};
    connection();window.addEventListener('online',connection);window.addEventListener('offline',connection);
    const destinations = {'mở trung tâm thông báo':panel.dataset.notifications,'cài đặt thông báo':panel.dataset.settings,'đặt sân ngay':panel.dataset.booking};
    const suggestions = items => {
        panel.querySelector('.ai-chat-suggestions')?.remove();
        if (!Array.isArray(items)) return;
        const group=document.createElement('div');group.className='ai-chat-suggestions';
        items.filter(item=>typeof item==='string').slice(0,8).forEach(text=>{const target=destinations[text.trim().toLocaleLowerCase('vi-VN')];const button=document.createElement(target?'a':'button');button.className='ai-chat-suggestion';button.textContent=text;if(target){button.href=target;}else{button.type='button';button.dataset.chatQuestion=text;}group.append(button);});messages.append(group);scroll();
    };
    async function ask(text,previous=null) {
        text=(text||'').trim();if(!text||busy)return;
        if(text.length>500){status.textContent='Câu hỏi tối đa 500 ký tự.';return;}
        if(!previous)bubble(text,'user');else previous.remove();
        input.value='';busy=true;form.setAttribute('aria-busy','true');form.querySelector('button').disabled=true;
        panel.querySelectorAll('[data-chat-question],[data-chat-history],.ai-chat-retry').forEach(button=>{button.disabled=true;});
        const answer=bubble('•••');answer.body.classList.add('ai-chat-typing');answer.row.setAttribute('aria-busy','true');status.textContent='Trợ lý đang trả lời…';
        const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),30000);let received='';let finished=false;
        try {
            const response=await fetch(panel.dataset.endpoint,{method:'POST',credentials:'same-origin',signal:controller.signal,headers:{'Content-Type':'application/json','Accept':'application/x-ndjson','X-CSRF-TOKEN':panel.dataset.csrf},body:JSON.stringify({message:text})});
            if(!response.ok)throw new Error(({401:'Vui lòng đăng nhập lại để tiếp tục.',419:'Phiên đã hết hạn. Tải lại trang rồi thử lại.',429:'Bạn gửi quá nhanh. Vui lòng chờ một phút rồi thử lại.'})[response.status]||'Trợ lý tạm thời không phản hồi. Vui lòng thử lại.');
            if(!response.body||!response.headers.get('content-type')?.includes('application/x-ndjson'))throw new Error('Phiên trò chuyện không khả dụng. Vui lòng tải lại trang.');
            const reader=response.body.getReader();const decoder=new TextDecoder();let buffer='';
            const consume=line=>{if(!line.trim())return;const event=JSON.parse(line);if(event.type==='delta' && typeof event.text==='string'){received+=event.text;answer.body.textContent=received;answer.body.classList.remove('ai-chat-typing');scroll();}else if(event.type==='done'){finished=true;suggestions(event.data?.suggestions);}else if(event.type==='error'){throw new Error(event.message||'Không thể trả lời câu hỏi này.');}};
            while(true){const {value,done}=await reader.read();buffer+=decoder.decode(value||new Uint8Array(),{stream:!done});const lines=buffer.split('\n');buffer=lines.pop()||'';lines.forEach(consume);if(done){consume(buffer);break;}}
            if(!finished||!received)throw new Error('Câu trả lời bị gián đoạn. Bạn có thể thử lại.');
            status.textContent='';
        } catch(error) {
            controller.abort();answer.body.classList.add('ai-chat-error');
            answer.body.textContent=(received?received+'\n\n':'')+(error.name==='AbortError'?'Phản hồi quá lâu. Vui lòng thử lại.':error instanceof SyntaxError?'Phản hồi chưa hợp lệ. Vui lòng thử lại.':error.message||'Mất kết nối. Vui lòng thử lại.');
            const retry=document.createElement('button');retry.type='button';retry.className='ai-chat-retry';retry.textContent='Thử lại';retry.addEventListener('click',()=>ask(text,answer.row));answer.row.append(retry);status.textContent='Chưa hoàn tất câu trả lời.';
        } finally {
            clearTimeout(timeout);busy=false;form.removeAttribute('aria-busy');answer.row.removeAttribute('aria-busy');answer.body.classList.remove('ai-chat-typing');form.querySelector('button').disabled=false;
            panel.querySelectorAll('[data-chat-question],[data-chat-history],.ai-chat-retry').forEach(button=>{button.disabled=false;});scroll();
        }
    }
    form.addEventListener('submit',event=>{event.preventDefault();ask(input.value);});
    panel.addEventListener('click',event=>{
        const question=event.target.closest('[data-chat-question]');if(question)ask(question.dataset.chatQuestion);
        const history=event.target.closest('[data-chat-history]');if(history&&!busy){bubble('Đặt như lần trước','user');const answer=bubble('Mở lịch sử booking và chọn “Đặt lại” ở booking phù hợp. Giá và sân trống sẽ được kiểm tra theo ngày mới trước khi bạn xác nhận.');const link=document.createElement('a');link.href=history.dataset.chatHistory;link.textContent='Mở booking của tôi';link.className='ai-chat-suggestion';answer.row.append(link);scroll();}
    });
});
window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
