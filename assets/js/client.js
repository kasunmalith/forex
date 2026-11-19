(() => {
  const watchlistRoot=document.querySelector('[data-watchlist-root]');
  let watchlistController=null;
  document.addEventListener('submit',async(event)=>{
    const form=event.target.closest('[data-watchlist-form]');if(!form)return;event.preventDefault();watchlistController?.abort();watchlistController=new AbortController();
    const button=form.querySelector('button[type="submit"]'),message=form.querySelector('[data-watchlist-message]');button.disabled=true;if(message)message.textContent='Saving…';
    try{
      const response=await fetch(form.dataset.apiEndpoint,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},cache:'no-store',signal:watchlistController.signal});
      const payload=await response.json();if(response.status===401){location.assign(payload.login_url||form.dataset.loginUrl||snapshotRoot?.dataset.clientLoginUrl);return;}if(!response.ok||!payload.success)throw new Error(payload.message||'Watchlist update failed.');if(message)message.textContent=payload.message;
      const id=String(form.elements.instrument_id.value),action=form.elements.action.value;const row=watchlistRoot?.querySelector(`tr[data-instrument-id="${CSS.escape(id)}"]`);if(action==='add'&&watchlistRoot){const title=watchlistRoot.querySelector('.client-section-heading h2'),subtitle=watchlistRoot.querySelector('.client-section-heading p'),footnote=watchlistRoot.querySelector('.client-table-footnote');if(title)title.textContent='Your Saved Instruments';if(subtitle)subtitle.textContent='Saved to your personal profile';if(footnote)footnote.textContent='Watchlist changes are saved to your profile.';}
      if(action==='remove'){row?.remove();if(!row)form.remove();}
      else if(row){form.elements.action.value='remove';button.textContent='Remove';}
      else if(watchlistRoot){const select=watchlistRoot.querySelector('#watchlist-instrument'),option=select?.querySelector(`option[value="${CSS.escape(id)}"]`),parts=(option?.textContent||id).split(' · '),symbol=option?.dataset.symbol||parts[0],display=parts.slice(1).join(' · ');let body=watchlistRoot.querySelector('.client-table tbody');if(!body){const empty=watchlistRoot.querySelector('.client-panel-empty');empty?.remove();const wrap=document.createElement('div');wrap.className='client-table-wrap';const table=document.createElement('table');table.className='client-table';table.innerHTML='<thead><tr><th>Instrument</th><th>Market</th><th class="numeric">Demo Price</th><th>Data</th><th>Action</th></tr></thead><tbody></tbody>';wrap.append(table);watchlistRoot.append(wrap);body=table.querySelector('tbody');}
        const tr=document.createElement('tr');tr.dataset.instrumentId=id;tr.dataset.marketSymbol=symbol;tr.dataset.precision=option?.dataset.precision||'2';const nameCell=document.createElement('td');nameCell.textContent=`${symbol} · ${display}`;const market=document.createElement('td');market.textContent=option?.dataset.category||'Demo market';const price=document.createElement('td');price.className='numeric';price.dataset.marketField='mid';price.textContent='—';const data=document.createElement('td');data.textContent='SIMULATED';const actionCell=document.createElement('td');const remove=document.createElement('form');remove.className='client-row-form';remove.method='post';remove.dataset.watchlistForm='';remove.dataset.apiEndpoint=form.dataset.apiEndpoint;remove.dataset.loginUrl=form.dataset.loginUrl;remove.innerHTML='<input type="hidden" name="_csrf"><input type="hidden" name="action" value="remove"><input type="hidden" name="instrument_id"><button type="submit">Remove</button><span data-watchlist-message role="status"></span>';remove.elements._csrf.value=form.elements._csrf.value;remove.elements.instrument_id.value=id;actionCell.append(remove);const trade=document.createElement('a');trade.href=`${watchlistRoot.dataset.tradeUrl}?symbol=${encodeURIComponent(symbol)}`;trade.textContent='Trade Demo';trade.className='client-text-link';actionCell.append(document.createElement('br'),trade);tr.append(nameCell,market,price,data,actionCell);body.prepend(tr);if(select)form.reset();}
    }catch(error){if(error.name!=='AbortError'&&message)message.textContent=error.message||'Watchlist update failed.';}finally{if(button.isConnected)button.disabled=false;}
  });
  const sidebar = document.querySelector('[data-client-sidebar]');
  const snapshotRoot=document.querySelector('[data-client-snapshot-url]');
  const connectionNotice=document.querySelector('[data-client-connection]');
  let snapshotTimer=null,snapshotController=null,snapshotStopped=false;
  const syncList=(type,records,currency)=>{const panel=document.querySelector(`[data-client-live-list="${type}"]`);if(!panel)return;const dashboard=type==='positions'&&snapshotRoot?.dataset.clientView==='dashboard';let wrap=panel.querySelector('.client-table-wrap'),empty=panel.querySelector('.client-panel-empty');if(!records.length){if(wrap)wrap.hidden=true;if(!empty){empty=document.createElement('div');empty.className='client-panel-empty';empty.textContent=type==='positions'?'No open positions':'No order records';panel.append(empty);}empty.hidden=false;return;}if(empty)empty.hidden=true;if(!wrap){wrap=document.createElement('div');wrap.className='client-table-wrap';const table=document.createElement('table');table.className='client-table';const headers=type==='orders'?['Instrument','Type','Side','Volume','Requested','Execution','Status','Created']:dashboard?['Instrument','Side','Volume','Open Price','Demo Price','P/L']:['Instrument','Side','Volume','Open Price','Demo Price','Demo P/L','Opened'];const thead=document.createElement('thead'),tr=document.createElement('tr');headers.forEach((h)=>{const th=document.createElement('th');th.textContent=h;tr.append(th);});thead.append(tr);const tbody=document.createElement('tbody');table.append(thead,tbody);wrap.append(table);panel.append(wrap);}wrap.hidden=false;const body=wrap.querySelector('tbody');body.replaceChildren();const fmtDate=(v)=>{const d=new Date(String(v||'').replace(' ','T'));return Number.isNaN(d.getTime())?'—':new Intl.DateTimeFormat('en',{dateStyle:'medium',timeStyle:'short'}).format(d);};records.forEach((item)=>{const row=document.createElement('tr');const values=type==='orders'?[`${item.symbol} · ${item.display_name}`,String(item.order_type||item.type||'market').replaceAll('_',' '),String(item.direction||'').toUpperCase(),item.volume,item.requested_price??'—',item.execution_price??'—',item.status==='executed'?'Filled':item.status,fmtDate(item.created_at)]:dashboard?[`${item.symbol} · ${item.display_name}`,String(item.direction||'').toUpperCase(),item.volume,item.open_price,item.current_price??item.mark_price??'—',item.position_pl??item.account_pl??'—']:[`${item.symbol} · ${item.display_name}`,String(item.direction||'').toUpperCase(),item.volume,item.open_price,item.current_price??item.mark_price??'—',item.position_pl??item.account_pl??'—',fmtDate(item.opened_at)];values.forEach((v,index)=>{const td=document.createElement('td');td.textContent=String(v??'—');if([2,3,4,5].includes(index)&&type==='positions'||[3,4,5].includes(index)&&type==='orders')td.className='numeric';row.append(td);});body.append(row);});};
  const makeText=(tag,value,className='')=>{const node=document.createElement(tag);node.textContent=String(value??'—');if(className)node.className=className;return node;};
  const syncDashboardActivity=(data)=>{const section=document.querySelector('[aria-labelledby="trading-stats-title"]');if(section){const stats=section.querySelectorAll('.client-stats-row strong');[data.stats?.total,data.stats?.wins,data.stats?.losses].forEach((v,i)=>{if(stats[i]&&v!=null)stats[i].textContent=String(v);});let list=section.querySelector('.client-recent-list');if(data.orders?.length){section.querySelector('.client-panel-empty')?.remove();if(!list){list=document.createElement('div');list.className='client-recent-list';section.append(list);}list.replaceChildren();data.orders.slice(0,5).forEach((order)=>{const row=document.createElement('div');row.className='client-recent-row';const copy=document.createElement('span'),strong=makeText('strong',order.symbol),small=makeText('small',`${String(order.order_type||'market').replaceAll('_',' ')} · ${String(order.direction).toUpperCase()}`);copy.append(strong,small);const status=makeText('span',order.status==='executed'?'Filled':order.status,`client-status-pill is-${order.status}`);row.append(copy,status);list.append(row);});}};
    const panel=document.querySelector('.client-dashboard-trades');if(panel&&Array.isArray(data.trades)){let wrap=panel.querySelector('.client-table-wrap');if(data.trades.length){panel.querySelector('.client-panel-empty')?.remove();if(!wrap){wrap=document.createElement('div');wrap.className='client-table-wrap';const table=document.createElement('table');table.className='client-table';table.innerHTML='<thead><tr><th>Instrument</th><th>Side</th><th class="numeric">Volume</th><th class="numeric">Entry</th><th class="numeric">Exit</th><th class="numeric">Demo P/L</th><th>Closed</th></tr></thead><tbody></tbody>';wrap.append(table);panel.append(wrap);}const body=wrap.querySelector('tbody');body.replaceChildren();data.trades.forEach((trade)=>{const row=document.createElement('tr');[`${trade.symbol} · ${trade.display_name}`,String(trade.direction).toUpperCase(),trade.volume,trade.open_price,trade.close_price,trade.profit_loss,new Date(String(trade.closed_at).replace(' ','T')).toLocaleString()].forEach((v,i)=>{const td=makeText('td',v,[2,3,4,5].includes(i)?'numeric':'');row.append(td);});body.append(row);});}}};
  const pollSnapshot=async()=>{clearTimeout(snapshotTimer);if(snapshotStopped||document.hidden||!snapshotRoot)return;snapshotController?.abort();snapshotController=new AbortController();try{const response=await fetch(snapshotRoot.dataset.clientSnapshotUrl,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal:snapshotController.signal});const data=await response.json();if(response.status===401){snapshotStopped=true;location.assign(data.login_url||snapshotRoot.dataset.clientLoginUrl);return;}if(!response.ok||!data.success)throw new Error('Account refresh unavailable');connectionNotice.hidden=true;const currency=data.metrics?.currency||'USD';for(const el of document.querySelectorAll('[data-client-metric-value]')){const value=data.metrics?.[el.dataset.clientMetricValue];if(value==null){el.textContent='—';continue;}const numeric=Number(value);el.textContent=Number.isFinite(numeric)?new Intl.NumberFormat('en-US',{style:'currency',currency,minimumFractionDigits:2,maximumFractionDigits:2}).format(numeric):'—';}const count=document.querySelector('#positions-preview-title')?.parentElement?.querySelector('p');if(count&&Number.isFinite(Number(data.metrics?.open_positions)))count.textContent=`${data.metrics.open_positions} currently open`;syncList('positions',data.positions||[],currency);if(snapshotRoot.dataset.clientView!=='dashboard')syncList('orders',data.orders||[],currency);syncDashboardActivity(data);}
    catch(error){if(error.name!=='AbortError'){connectionNotice.hidden=false;connectionNotice.textContent=navigator.onLine?'Account data is temporarily unavailable. Retrying…':'You are offline. Last account values are shown.';}}
    finally{if(!snapshotStopped&&!document.hidden)snapshotTimer=setTimeout(pollSnapshot,5000);}};
  if(snapshotRoot){pollSnapshot();document.addEventListener('visibilitychange',()=>{if(document.hidden){clearTimeout(snapshotTimer);snapshotController?.abort();}else pollSnapshot();});window.addEventListener('online',pollSnapshot);window.addEventListener('offline',()=>{if(connectionNotice){connectionNotice.hidden=false;connectionNotice.textContent='You are offline. Last account values are shown.';}});window.addEventListener('pagehide',()=>{snapshotStopped=true;clearTimeout(snapshotTimer);snapshotController?.abort();},{once:true});}
  const openButton = document.querySelector('[data-client-drawer-open]');
  const closeButtons = document.querySelectorAll('[data-client-drawer-close]');
  const backdrop = document.querySelector('.client-drawer-backdrop');
  if (!sidebar || !openButton || !backdrop) return;

  const isMobile = () => window.matchMedia('(max-width: 980px)').matches;
  const close = (restoreFocus = true) => {
    sidebar.classList.remove('is-open');
    document.body.classList.remove('client-drawer-open');
    openButton.setAttribute('aria-expanded', 'false');
    backdrop.hidden = true;
    if (restoreFocus && isMobile()) openButton.focus();
  };
  const open = () => {
    if (!isMobile()) return;
    sidebar.classList.add('is-open');
    document.body.classList.add('client-drawer-open');
    openButton.setAttribute('aria-expanded', 'true');
    backdrop.hidden = false;
    sidebar.querySelector('[data-client-drawer-close]')?.focus();
  };

  openButton.addEventListener('click', open);
  closeButtons.forEach((button) => button.addEventListener('click', () => close()));
  backdrop.addEventListener('click', () => close());
  document.addEventListener('keydown', (event) => {
    if (!isMobile() || !sidebar.classList.contains('is-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...sidebar.querySelectorAll('a[href],button:not([disabled]),select:not([disabled]),input:not([disabled])')]
      .filter((element) => element.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  window.addEventListener('resize', () => {
    if (!isMobile()) close(false);
  });
})();
