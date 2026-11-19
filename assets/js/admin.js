(()=>{
    document.querySelectorAll('.admin-instrument-form').forEach(form=>{const seed=form.querySelector('[data-seed-price]'),spread=form.querySelector('[data-instrument-spread]'),precision=form.querySelector('[data-instrument-precision]'),output=form.querySelector('[data-instrument-price-preview]');const update=()=>{const mid=Number(seed?.value),gap=Number(spread?.value),digits=Math.max(0,Math.min(8,Number(precision?.value)||0));if(!output)return;if(!Number.isFinite(mid)||!Number.isFinite(gap)||mid<=0||gap<0||mid<=gap/2){output.textContent='Enter a valid positive seed and non-negative spread';return}output.textContent=`${(mid-gap/2).toFixed(digits)} / ${(mid+gap/2).toFixed(digits)}`};seed?.addEventListener('input',update);spread?.addEventListener('input',update);precision?.addEventListener('input',update);update()});
    document.addEventListener('submit',event=>{const form=event.target.closest('form[data-admin-submit]');if(!form||event.defaultPrevented)return;if(form.dataset.confirm&&!window.confirm(form.dataset.confirm)){event.preventDefault();return}const button=form.querySelector('button[type="submit"]');if(button){button.disabled=true;button.classList.add('is-loading');if(!button.dataset.originalLabel)button.dataset.originalLabel=button.textContent.trim();button.textContent='Saving…'}});
    const body=document.body;
    const toggle=document.querySelector('[data-sidebar-toggle]');
    const closeButtons=document.querySelectorAll('[data-sidebar-close]');
    const sidebar=document.querySelector('#admin-sidebar');
    const isMobile=()=>window.matchMedia('(max-width: 760px)').matches;
    const focusableInSidebar=()=>Array.from(sidebar?.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')||[]).filter(element=>element.getClientRects().length>0);
    const setSidebar=(open,restoreFocus=false)=>{
        body.classList.toggle('admin-sidebar-open',open);
        toggle?.setAttribute('aria-expanded',open?'true':'false');
        if(open&&isMobile()) requestAnimationFrame(()=>focusableInSidebar()[0]?.focus());
        if(!open&&restoreFocus&&isMobile()) toggle?.focus();
    };
    toggle?.addEventListener('click',()=>setSidebar(!body.classList.contains('admin-sidebar-open')));
    closeButtons.forEach(button=>button.addEventListener('click',()=>setSidebar(false,true)));
    sidebar?.addEventListener('keydown',event=>{
        if(event.key!=='Tab'||!isMobile()||!body.classList.contains('admin-sidebar-open'))return;
        const items=focusableInSidebar();
        if(!items.length)return;
        const first=items[0],last=items[items.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    });
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&body.classList.contains('admin-sidebar-open'))setSidebar(false,true)});
    document.querySelector('[data-password-toggle]')?.addEventListener('click',event=>{const button=event.currentTarget;const input=document.getElementById(button.getAttribute('aria-controls'));if(!input)return;const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-pressed',show?'true':'false')});
    document.querySelector('[data-admin-login]')?.addEventListener('submit',event=>{const form=event.currentTarget;if(!form.reportValidity()){event.preventDefault();return}const button=form.querySelector('button[type=submit]');if(button){button.disabled=true;button.classList.add('is-loading');const label=button.querySelector('[data-button-label]');if(label)label.textContent='Signing in…'}});
})();

(()=>{
    const showMessage=(target,message)=>{if(!target)return;target.textContent=message;target.hidden=!message};
    const focusable=(dialog)=>Array.from(dialog.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[href]')).filter(element=>element.getClientRects().length>0);
    document.querySelectorAll('[data-dialog-open]').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById(button.dataset.dialogOpen);if(dialog instanceof HTMLDialogElement&&!dialog.open){dialog.showModal();requestAnimationFrame(()=>dialog.querySelector('input:not([readonly]), select, textarea, button')?.focus())}}));
    document.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
    document.querySelectorAll('[data-user-status-form],[data-account-status-form]').forEach(form=>{
        const status=form.querySelector('select[name="status"]'),reason=form.querySelector('[data-status-reason]');
        const update=()=>{if(reason)reason.required=Boolean(status&&status.value!=='active')};status?.addEventListener('change',update);update();
    });
    const groupDigits=(digits)=>digits.replace(/\B(?=(\d{3})+(?!\d))/g,',');
    const centsFrom=(value)=>{const match=String(value).trim().match(/^(\d{1,16})(?:\.(\d{1,2}))?$/);if(!match)return null;return BigInt(match[1])*100n+BigInt((match[2]||'').padEnd(2,'0'))};
    const showCents=(cents,currency)=>{const negative=cents<0n;const absolute=negative?-cents:cents;return `${negative?'-':''}${currency} ${groupDigits((absolute/100n).toString())}.${(absolute%100n).toString().padStart(2,'0')}`};
    document.querySelectorAll('[data-balance-adjustment]').forEach(form=>{
        const direction=form.querySelector('[data-adjustment-direction]'),amount=form.querySelector('[data-adjustment-amount]'),reason=form.querySelector('[data-adjustment-reason]'),entry=form.querySelector('[data-adjustment-entry]'),confirmation=form.querySelector('[data-adjustment-confirmation]');
        const updateEstimate=()=>{const current=centsFrom(form.dataset.currentBalance||''),delta=centsFrom(amount?.value||'');const output=form.querySelector('[data-adjustment-estimate]');if(!output)return;if(current===null||delta===null){output.textContent='Enter a valid amount';return}const estimated=direction?.value==='debit'?current-delta:current+delta;output.textContent=showCents(estimated,form.dataset.currency||'')};
        amount?.addEventListener('input',updateEstimate);direction?.addEventListener('change',updateEstimate);
        form.querySelector('[data-review-adjustment]')?.addEventListener('click',()=>{
            if(!form.reportValidity())return;const current=centsFrom(form.dataset.currentBalance||''),delta=centsFrom(amount?.value||'');if(current===null||delta===null)return;
            const estimated=direction?.value==='debit'?current-delta:current+delta;const label=direction?.value==='debit'?'Debit Virtual Funds':'Credit Virtual Funds';
            form.querySelector('[data-confirm-direction]').textContent=label;form.querySelector('[data-confirm-amount]').textContent=showCents(delta,form.dataset.currency||'');form.querySelector('[data-confirm-balance]').textContent=showCents(estimated,form.dataset.currency||'');form.querySelector('[data-confirm-reason]').textContent=reason?.value.trim()||'—';
            if(entry)entry.hidden=true;if(confirmation)confirmation.hidden=false;requestAnimationFrame(()=>form.querySelector('[data-edit-adjustment]')?.focus());
        });
        form.querySelector('[data-edit-adjustment]')?.addEventListener('click',()=>{if(confirmation)confirmation.hidden=true;if(entry)entry.hidden=false;requestAnimationFrame(()=>direction?.focus())});
        form.addEventListener('submit',event=>{if(confirmation?.hidden){event.preventDefault();form.querySelector('[data-review-adjustment]')?.click()}});
    });
    document.querySelectorAll('[data-admin-list]').forEach(list=>{
        const form=list.querySelector('[data-admin-list-filters]'),table=list.querySelector('[data-list-table]'),pagination=list.querySelector('[data-list-pagination]'),message=list.querySelector('[data-list-message]');if(!form||!table||!pagination)return;
        let request=null,sequence=0,timer=null;
        const syncForm=(url)=>{const params=new URL(url,location.href).searchParams;for(const key of ['search','status','verification','country','account_status','date','from','to','type','currency','positions','per_page','sort','direction','page']){const field=form.elements.namedItem(key);if(field&&params.has(key))field.value=params.get(key)}};
        const load=async(url,historyMode='push')=>{
            request?.abort();request=new AbortController();const ticket=++sequence;list.classList.add('is-loading');showMessage(message,'Updating results…');
            try{const response=await fetch(url,{credentials:'same-origin',signal:request.signal,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const data=await response.json().catch(()=>null);if(ticket!==sequence)return;
                if(response.status===401){window.location.assign(data?.login_url||form.action);return}
                if(!response.ok||!data?.success||!data.data?.table_html){showMessage(message,data?.message||'The list could not be updated. Please try again.');return}
                table.innerHTML=data.data.table_html;pagination.innerHTML=data.data.pagination_html;showMessage(message,`${data.data.total_records} records · Page ${data.data.current_page}`);syncForm(url);
                if(historyMode==='push')window.history.pushState({},'',url);else if(historyMode==='replace')window.history.replaceState({},'',url);
            }catch(error){if(error.name!=='AbortError')showMessage(message,'The list could not be updated. Please try again.')}
            finally{if(ticket===sequence)list.classList.remove('is-loading')}
        };
        const submit=(historyMode='push')=>{const params=new URLSearchParams(new FormData(form));for(const [key,value] of [...params.entries()])if(value==='')params.delete(key);load(`${form.action}?${params.toString()}`,historyMode)};
        form.addEventListener('submit',event=>{event.preventDefault();if(form.elements.page)form.elements.page.value='1';submit('push')});
        form.addEventListener('input',event=>{if(event.target.matches('[data-list-search]')){clearTimeout(timer);if(form.elements.page)form.elements.page.value='1';timer=setTimeout(()=>submit('replace'),320)}});
        form.addEventListener('change',event=>{if(event.target.matches('select,input[type=date]')){if(form.elements.page)form.elements.page.value='1';submit('push')}});
        list.addEventListener('click',event=>{const link=event.target.closest('[data-list-page]');if(link){event.preventDefault();load(link.href)}});
        window.addEventListener('popstate',()=>{if(new URL(form.action).pathname===location.pathname)load(location.href,'none')});
    });
    document.addEventListener('submit',async event=>{
        const form=event.target.closest('form[data-admin-mutation]');if(!form||event.defaultPrevented)return;event.preventDefault();
        const button=form.querySelector('button[type="submit"]'),localError=Array.from(form.querySelectorAll('[data-form-error]')).find(node=>!node.closest('[hidden]'))||form.querySelector('[data-form-error]'),globalMessage=document.querySelector('[data-mutation-message]');
        if(button){button.disabled=true;button.classList.add('is-loading')}showMessage(localError,'');showMessage(globalMessage,'Saving…');
        try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const data=await response.json().catch(()=>null);
            if(response.status===401){window.location.assign(data?.login_url||form.action);return}
            if(!response.ok||!data?.success){const text=data?.message||'This request could not be completed.';if(localError)showMessage(localError,text);else showMessage(globalMessage,text);return}
            showMessage(globalMessage,data.message||'Saved.');window.location.reload();
        }catch{showMessage(localError,'The request could not be completed. Check your connection and try again.');showMessage(globalMessage,'')}
        finally{if(button?.isConnected){button.disabled=false;button.classList.remove('is-loading')}}
    });
})();
