(() => {
  const root = document.querySelector('[data-trading-terminal]');
  if (!root) return;

  const rows = [...root.querySelectorAll('[data-terminal-row]')];
  const chart = root.querySelector('.terminal-chart-canvas');
  const watchlist = root.querySelector('[data-terminal-watchlist]');
  const ticket = root.querySelector('[data-terminal-ticket]');
  const backdrop = root.querySelector('[data-terminal-backdrop]');
  const search = root.querySelector('[data-terminal-search]');
  const ticketMessage = root.querySelector('[data-ticket-message]');
  const infoDialog = root.querySelector('[data-terminal-info-dialog]');
  const orderDialog = root.querySelector('[data-terminal-order-dialog]');
  const orderButton = root.querySelector('[data-submit-order]');
  const orderToast = root.querySelector('[data-order-toast]');
  const instruments = new Map(rows.map((row) => [row.dataset.symbol, row.dataset]));
  let selected = root.dataset.initialSymbol || '';
  let activeCategory = 'all';
  let activeTab = 'positions';
  let activePanel = null;
  let returnFocus = null;
  let updateOrderAvailability = () => {};

  const formatPrice = (value, precision) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) return '—';
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: precision,
      maximumFractionDigits: precision,
      useGrouping: true,
    }).format(parsed);
  };

  const updateContextLabels = (instrument, timeframe = null) => {
    const period = timeframe || root.querySelector('[data-terminal-timeframe].is-active')?.dataset.terminalTimeframe || '15m';
    root.querySelector('[data-chart-context]').textContent = `${instrument.symbol} · ${period}`;
    root.querySelector('[data-chart-empty-context]').textContent = `${instrument.symbol} · ${period} · DEMO DATA`;
    chart.dataset.chartSymbol = instrument.symbol;
    chart.setAttribute('aria-label', `Simulated demo chart for ${instrument.symbol}`);
  };

  const setInstrument = (symbol, updateUrl = true) => {
    const instrument = instruments.get(symbol);
    if (!instrument) return false;
    selected = symbol;
    rows.forEach((row) => {
      const isSelected = row.dataset.symbol === symbol;
      row.classList.toggle('is-selected', isSelected);
      row.querySelector('[data-terminal-instrument]')?.setAttribute('aria-pressed', String(isSelected));
    });

    root.querySelector('[data-selected-symbol]').textContent = symbol;
    root.querySelector('[data-selected-name]').textContent = instrument.displayName;
    root.querySelector('[data-selected-category]').textContent = `${instrument.categoryName} · Instrument enabled`;
    root.querySelector('[data-ticket-symbol]').textContent = symbol;
    root.querySelector('[data-ticket-name]').textContent = instrument.displayName;
    updateOrderAvailability();

    const precision = Math.max(0, Math.min(10, Number.parseInt(instrument.precision, 10) || 0));
    const bid = formatPrice(instrument.bid, precision);
    const ask = formatPrice(instrument.ask, precision);
    const spread = formatPrice(instrument.spread, precision);
    root.querySelector('[data-price-role="bid"]').textContent = bid;
    root.querySelector('[data-price-role="ask"]').textContent = ask;
    root.querySelector('[data-price-role="spread"]').textContent = spread;
    root.querySelectorAll('[data-direction-quote]').forEach((element, index) => {
      element.textContent = index === 0 ? ask : bid;
    });
    root.querySelector('[data-ticket-spread]').textContent = spread;
    updateContextLabels(instrument);

    if (infoDialog.open) updateInstrumentDetails(instrument);
    if (updateUrl) {
      const url = new URL(window.location.href);
      url.searchParams.set('symbol', symbol);
      window.history.replaceState({}, '', url);
    }
    root.dispatchEvent(new CustomEvent('terminal:instrumentchange', { detail: { symbol, instrument } }));
    return true;
  };

  const updateInstrumentDetails = (instrument) => {
    const precision = Math.max(0, Math.min(10, Number.parseInt(instrument.precision, 10) || 0));
    root.querySelector('[data-info-symbol]').textContent = instrument.symbol;
    root.querySelector('[data-info-name]').textContent = instrument.displayName;
    root.querySelector('[data-info-category]').textContent = instrument.categoryName;
    root.querySelector('[data-info-currency]').textContent = instrument.quoteCurrency || '—';
    root.querySelector('[data-info-contract]').textContent = instrument.contractSize || '—';
    root.querySelector('[data-info-spread]').textContent = formatPrice(instrument.spread, precision);
    root.querySelector('[data-info-precision]').textContent = `${precision} digits`;
    const rate = Number(instrument.marginRate);
    root.querySelector('[data-info-margin]').textContent = Number.isFinite(rate) ? `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(rate * 100)}%` : '—';
  };

  const applyFilters = () => {
    const term = (search?.value || '').trim().toLocaleLowerCase();
    let visible = 0;
    rows.forEach((row) => {
      const matchesSearch = !term || row.dataset.symbol.toLocaleLowerCase().includes(term) || row.dataset.name.includes(term);
      const matchesCategory = activeCategory === 'all' || (activeCategory === 'favorites' ? row.dataset.favorite === 'true' : row.dataset.category === activeCategory);
      const show = matchesSearch && matchesCategory;
      row.hidden = !show;
      if (show) visible += 1;
    });
    const empty = root.querySelector('[data-terminal-search-empty]');
    empty.hidden = visible !== 0;
  };

  const setDirection = (direction) => {
    root.querySelectorAll('[data-terminal-direction]').forEach((button) => {
      const active = button.dataset.terminalDirection === direction;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    root.querySelectorAll('[data-price-side]').forEach((button) => {
      button.classList.toggle('is-selected', button.dataset.priceSide === direction);
    });
    root.querySelector('[data-terminal-place-order]').textContent = `Place Demo ${direction === 'buy' ? 'Buy' : 'Sell'}`;
    root.querySelector('[data-preview-direction]').textContent = direction.toUpperCase();
  };

  const setTab = (name, focus = false) => {
    if (!['positions', 'orders', 'history'].includes(name)) return;
    activeTab = name;
    root.querySelectorAll('[data-terminal-tab]').forEach((tab) => {
      const active = tab.dataset.terminalTab === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
      if (focus && active) tab.focus();
    });
    root.querySelectorAll('[data-terminal-pane]').forEach((pane) => {
      const active = pane.dataset.terminalPane === name;
      pane.classList.toggle('is-active', active);
      pane.hidden = !active;
    });
    try { window.localStorage.setItem('multiforex-terminal-tab', name); } catch { /* Storage is optional. */ }
  };

  const closePanel = (restore = true) => {
    if (!activePanel) return;
    activePanel.classList.remove('is-open');
    activePanel.removeAttribute('role');
    activePanel.removeAttribute('aria-modal');
    returnFocus?.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-mobile-panel-open');
    document.body.classList.remove('terminal-panel-open');
    backdrop.hidden = true;
    activePanel = null;
    if (restore && returnFocus?.isConnected) returnFocus.focus();
    returnFocus = null;
  };

  const openPanel = (panel, trigger) => {
    if (!panel) return;
    if (activePanel === panel) {
      closePanel();
      return;
    }
    closePanel(false);
    activePanel = panel;
    returnFocus = trigger || document.activeElement;
    returnFocus?.setAttribute('aria-expanded', 'true');
    panel.classList.add('is-open');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    root.classList.add('is-mobile-panel-open');
    document.body.classList.add('terminal-panel-open');
    backdrop.hidden = false;
    (panel.querySelector('button:not([disabled]),input:not([disabled]),select:not([disabled])') || panel).focus?.();
  };

  root.addEventListener('click', (event) => {
    const instrumentButton = event.target.closest('[data-terminal-instrument]');
    if (instrumentButton) {
      const row = instrumentButton.closest('[data-terminal-row]');
      if (row && setInstrument(row.dataset.symbol)) closePanel(false);
      return;
    }

    const categoryButton = event.target.closest('[data-terminal-category]');
    if (categoryButton) {
      activeCategory = categoryButton.dataset.terminalCategory;
      root.querySelectorAll('[data-terminal-category]').forEach((button) => {
        const active = button === categoryButton;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      applyFilters();
      return;
    }

    const timeframe = event.target.closest('[data-terminal-timeframe]');
    if (timeframe) {
      root.querySelectorAll('[data-terminal-timeframe]').forEach((button) => {
        const active = button === timeframe;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      const instrument = instruments.get(selected);
      if (instrument) updateContextLabels(instrument, timeframe.dataset.terminalTimeframe);
      try { window.localStorage.setItem('multiforex-terminal-timeframe', timeframe.dataset.terminalTimeframe); } catch { /* Storage is optional. */ }
      root.dispatchEvent(new CustomEvent('terminal:timeframechange', { detail: { timeframe: timeframe.dataset.terminalTimeframe } }));
      return;
    }

    const chartType = event.target.closest('[data-terminal-chart-type]');
    if (chartType) {
      root.querySelectorAll('[data-terminal-chart-type]').forEach((button) => {
        const active = button === chartType;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      chart.dataset.chartType = chartType.dataset.terminalChartType;
      return;
    }

    const direction = event.target.closest('[data-terminal-direction],[data-price-side]');
    if (direction) {
      setDirection(direction.dataset.terminalDirection || direction.dataset.priceSide);
      return;
    }

    const tab = event.target.closest('[data-terminal-tab]');
    if (tab) {
      setTab(tab.dataset.terminalTab);
      return;
    }

    const mobileTab = event.target.closest('[data-terminal-mobile-tab]');
    if (mobileTab) {
      setTab(mobileTab.dataset.terminalMobileTab);
      return;
    }

    const mobilePanel = event.target.closest('[data-terminal-mobile-panel]');
    if (mobilePanel) {
      const panel = mobilePanel.dataset.terminalMobilePanel === 'watchlist' ? watchlist : ticket;
      if (window.matchMedia('(max-width: 720px)').matches) openPanel(panel, mobilePanel);
      else if (panel === watchlist) openPanel(panel, mobilePanel);
      else panel?.querySelector('[data-ticket-volume]')?.focus();
      return;
    }

    if (event.target.closest('[data-terminal-close-panel]')) {
      closePanel();
      return;
    }

    if (event.target === backdrop) {
      closePanel();
      return;
    }

    const stepper = event.target.closest('[data-volume-step]');
    if (stepper) {
      const input = root.querySelector('[data-ticket-volume]');
      const current = Number(input.value);
      const cents = Math.round((Number.isFinite(current) ? current : 0.01) * 100);
      const next = Math.max(1, Math.min(10000, cents + (stepper.dataset.volumeStep === 'up' ? 1 : -1)));
      input.value = (next / 100).toFixed(2);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }

    if (event.target.closest('[data-terminal-info-open]')) {
      const instrument = instruments.get(selected);
      if (instrument) updateInstrumentDetails(instrument);
      infoDialog.showModal();
      return;
    }
    if (event.target.closest('[data-terminal-info-close]')) {
      infoDialog.close();
      return;
    }
    if (event.target.closest('[data-order-dialog-close],[data-order-preview-close]')) {
      orderDialog.close();
      return;
    }
    if (event.target.closest('[data-terminal-chart-reset]')) {
      const defaultTimeframe = root.querySelector('[data-terminal-timeframe="15m"]');
      if (defaultTimeframe) defaultTimeframe.click();
      root.querySelector('[data-terminal-chart-type="candles"]')?.click();
      chart.classList.remove('is-expanded');
      if (document.fullscreenElement === root.querySelector('[data-terminal-chart-panel]')) document.exitFullscreen?.();
      return;
    }
    if (event.target.closest('[data-terminal-fullscreen]')) {
      const panel = root.querySelector('[data-terminal-chart-panel]');
      if (document.fullscreenElement === panel) document.exitFullscreen?.();
      else if (panel.requestFullscreen) panel.requestFullscreen().catch(() => panel.classList.toggle('is-expanded'));
      else panel.classList.toggle('is-expanded');
      return;
    }
  });

  root.addEventListener('change', (event) => {
    const protectionToggle = event.target.closest('[data-protection-toggle]');
    if (!protectionToggle) return;
    const field = root.querySelector(`[data-protection-field="${protectionToggle.dataset.protectionToggle}"]`);
    field.hidden = !protectionToggle.checked;
    const input = field.querySelector('input');
    input.required = protectionToggle.checked;
    if (!protectionToggle.checked) input.value = '';
  });

  const showOrderPreview = () => {
    const volume = root.querySelector('[data-ticket-volume]');
    const orderType = root.querySelector('[data-ticket-type]');
    const direction = root.querySelector('[data-terminal-direction].is-active')?.dataset.terminalDirection || 'buy';
    const optionalPrices = [
      { toggle: 'sl', input: '[data-ticket-sl]', label: 'Stop Loss' },
      { toggle: 'tp', input: '[data-ticket-tp]', label: 'Take Profit' },
    ];
    ticketMessage.hidden = true;
    volume.setCustomValidity('');
    const volumeValue = Number(volume.value);
    if (!Number.isFinite(volumeValue) || volumeValue < 0.01 || volumeValue > 100) {
      volume.setCustomValidity('Enter a volume from 0.01 to 100 lots.');
      ticketMessage.textContent = 'Enter a volume from 0.01 to 100 lots.';
      ticketMessage.hidden = false;
      volume.reportValidity();
      volume.focus();
      return;
    }
    if (orderType.value !== 'market') {
      ticketMessage.textContent = 'Limit and Stop demo orders are coming soon.';
      ticketMessage.hidden = false;
      return;
    }
    for (const item of optionalPrices) {
      const toggle = root.querySelector(`[data-protection-toggle="${item.toggle}"]`);
      const input = root.querySelector(item.input);
      input.setCustomValidity('');
      if (toggle.checked && (!Number.isFinite(Number(input.value)) || Number(input.value) <= 0)) {
        ticketMessage.textContent = `Enter a positive ${item.label} price or turn the option off.`;
        ticketMessage.hidden = false;
        input.focus();
        return;
      }
    }
    root.querySelector('[data-preview-symbol]').textContent = selected;
    root.querySelector('[data-preview-volume]').textContent = `${volumeValue.toFixed(2)} lots`;
    root.querySelector('[data-preview-type]').textContent = orderType.options[orderType.selectedIndex].text;
    root.querySelector('[data-preview-direction]').textContent = direction.toUpperCase();
    const instrument = instruments.get(selected);
    const precision = Math.max(0, Math.min(10, Number.parseInt(instrument?.precision, 10) || 0));
    root.querySelector('[data-preview-bid]').textContent = formatPrice(instrument?.bid, precision);
    root.querySelector('[data-preview-ask]').textContent = formatPrice(instrument?.ask, precision);
    const slToggle = root.querySelector('[data-protection-toggle="sl"]');
    const tpToggle = root.querySelector('[data-protection-toggle="tp"]');
    root.querySelector('[data-preview-sl]').textContent = slToggle.checked ? root.querySelector('[data-ticket-sl]').value : '—';
    root.querySelector('[data-preview-tp]').textContent = tpToggle.checked ? root.querySelector('[data-ticket-tp]').value : '—';
    orderButton.textContent = `Place Demo ${direction === 'buy' ? 'Buy' : 'Sell'}`;
    orderDialog.showModal();
  };

  root.querySelector('[data-terminal-place-order]')?.addEventListener('click', showOrderPreview);

  const showTicketMessage = (message) => {
    ticketMessage.textContent = message;
    ticketMessage.hidden = false;
  };

  const refreshOrdersAndPositions = async () => {
    const [positionsResponse, ordersResponse] = await Promise.all([
      fetch(root.dataset.positionsUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' }),
      fetch(root.dataset.ordersUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' }),
    ]);
    if (!positionsResponse.ok || !ordersResponse.ok) return;
    const [positionsData, ordersData] = await Promise.all([positionsResponse.json(), ordersResponse.json()]);
    if (positionsData.success) renderPositions(positionsData.positions || []);
    if (ordersData.success) renderOrders(ordersData.orders || []);
  };

  const cell = (row, value, className = '') => {
    const element = document.createElement('td');
    if (className) element.className = className;
    element.textContent = value == null || value === '' ? '—' : String(value);
    row.append(element);
    return element;
  };
  const instrumentCell = (row, symbol, name) => {
    const td = document.createElement('td');
    const strong = document.createElement('strong'); strong.textContent = symbol;
    const small = document.createElement('small'); small.textContent = name;
    td.append(strong, small); row.append(td);
  };
  const sideCell = (row, side) => {
    const td = document.createElement('td'); const tag = document.createElement('span');
    tag.className = `terminal-side-tag is-${side}`; tag.textContent = String(side).toUpperCase(); td.append(tag); row.append(td);
  };
  const formatDate = (value) => {
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value || '—') : new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  };
  const renderPositions = (positions) => {
    const body = root.querySelector('[data-positions-body]'); body.replaceChildren();
    positions.forEach((position) => {
      const row = document.createElement('tr'); const precision = Number(position.precision_digits || position.precision) || 5; row.dataset.positionId = String(position.id); row.dataset.positionPrecision = String(precision);
      instrumentCell(row, position.symbol, position.display_name); sideCell(row, position.direction);
      cell(row, Number(position.volume).toFixed(2), 'numeric'); cell(row, formatPrice(position.open_price, precision), 'numeric');
      cell(row, position.current_price == null ? '—' : formatPrice(position.current_price, precision), 'numeric');
      cell(row, position.stop_loss == null ? '—' : formatPrice(position.stop_loss, precision), 'numeric');
      cell(row, position.take_profit == null ? '—' : formatPrice(position.take_profit, precision), 'numeric');
      cell(row, position.position_pl == null ? '—' : Number(position.position_pl).toFixed(2), 'numeric'); cell(row, formatDate(position.opened_at));
      const actions = document.createElement('td');
      row.dataset.positionSymbol=String(position.symbol||''); row.dataset.positionSide=String(position.direction||''); row.dataset.positionVolume=String(position.volume||''); row.dataset.positionOpen=String(position.open_price||''); row.dataset.positionSl=position.stop_loss==null?'':String(position.stop_loss); row.dataset.positionTp=position.take_profit==null?'':String(position.take_profit); row.dataset.positionPl=position.account_pl==null?'':String(position.account_pl); row.dataset.positionMark=position.mark_price==null?'':String(position.mark_price);
      for (const [label,action] of [['Close','close'],['Modify','modify']]) { const button = document.createElement('button'); button.type = 'button'; button.dataset.positionAction=action; button.textContent = label; actions.append(button, document.createTextNode(' ')); }
      row.append(actions); body.append(row);
    });
    root.querySelector('[data-positions-empty]').hidden = positions.length > 0;
    root.querySelector('[data-positions-table]').hidden = positions.length === 0;
    const tab = root.querySelector('[data-terminal-tab="positions"]'); tab.querySelector('span').textContent = String(positions.length);
    root.querySelector('[data-terminal-mobile-tab="positions"]')?.setAttribute('aria-label', `Positions, ${positions.length}`);
  };
  const renderOrders = (orders) => {
    const body = root.querySelector('[data-orders-body]'); body.replaceChildren();
    orders.forEach((order) => {
      const row = document.createElement('tr'); const precision = Number(order.precision) || 5;
      instrumentCell(row, order.symbol, order.display_name); cell(row, String(order.type || 'market').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())); sideCell(row, order.direction);
      cell(row, Number(order.volume).toFixed(2), 'numeric'); cell(row, order.execution_price == null ? '—' : formatPrice(order.execution_price, precision), 'numeric');
      cell(row, order.stop_loss == null ? '—' : formatPrice(order.stop_loss, precision), 'numeric'); cell(row, order.take_profit == null ? '—' : formatPrice(order.take_profit, precision), 'numeric');
      cell(row, order.status === 'executed' ? 'Filled' : order.status); cell(row, formatDate(order.created_at)); cell(row, '—'); body.append(row);
    });
    root.querySelector('[data-orders-empty]').hidden = orders.length > 0;
    root.querySelector('[data-orders-table]').hidden = orders.length === 0;
    const tab = root.querySelector('[data-terminal-tab="orders"]'); tab.querySelector('span').textContent = String(orders.length);
  };

  const renderHistory = (trades) => {
    const body=root.querySelector('[data-history-body]'); if(!body)return; body.replaceChildren();
    trades.forEach((trade)=>{const row=document.createElement('tr'),precision=Number(trade.precision_digits)||5;instrumentCell(row,trade.symbol,trade.display_name);sideCell(row,trade.direction);cell(row,Number(trade.volume).toFixed(2),'numeric');cell(row,formatPrice(trade.open_price,precision),'numeric');cell(row,formatPrice(trade.close_price,precision),'numeric');cell(row,formatMoney(trade.profit_loss,root.querySelector('[data-account-metric="balance"]')?.textContent.match(/[A-Z]{3}/)?.[0]||'USD',true),'numeric');cell(row,String(trade.close_reason||'manual').replaceAll('_',' '));cell(row,formatDate(trade.opened_at));cell(row,formatDate(trade.closed_at));body.append(row);});
    root.querySelector('[data-history-empty]').hidden=trades.length>0;root.querySelector('[data-history-table]').hidden=trades.length===0;const tab=root.querySelector('[data-terminal-tab="history"]');if(tab)tab.querySelector('span').textContent=String(trades.length);
  };

  const formatMoney = (value, currency, signed = false) => {
    if (value == null || !Number.isFinite(Number(value))) return '—';
    try { return new Intl.NumberFormat('en-US', { style: 'currency', currency, signDisplay: signed ? 'exceptZero' : 'auto' }).format(Number(value)); }
    catch { return `${currency} ${Number(value).toFixed(2)}`; }
  };
  const applyAccountState = (data) => {
    const metrics = data.metrics || {}, currency = metrics.currency || data.account?.currency || 'USD';
    ['balance','equity','used_margin','free_margin'].forEach((key) => {
      const element = root.querySelector(`[data-account-metric="${key}"]`); if (element) element.textContent = formatMoney(metrics[key], currency);
    });
    const pl = root.querySelector('[data-account-metric="floating_pl"]'); if (pl) pl.textContent = formatMoney(metrics.floating_pl, currency, true);
    const level = root.querySelector('[data-account-metric="margin_level"]'); if (level) level.textContent = metrics.margin_level == null ? '—' : `${Number(metrics.margin_level).toLocaleString('en-US',{maximumFractionDigits:2})}%`;
    const connection = root.querySelector('.terminal-account-connected');
    if (connection) {
      const marginNotice = metrics.margin_state === 'critical' ? ' · Margin level critical' : metrics.margin_state === 'warning' ? ' · Margin level warning' : '';
      connection.textContent = (metrics.valuation_status === 'degraded' ? 'Account valuation incomplete · Demo data reconnecting' : 'Account data connected · DEMO') + marginNotice;
    }
    if (data.trading_controls) updateOrderAvailability(data.trading_controls,data.selected_instrument);
    const positions = Array.isArray(data.positions) ? data.positions : [];
    if(Array.isArray(data.orders))renderOrders(data.orders);
    if(Array.isArray(data.trades))renderHistory(data.trades);
    const body = root.querySelector('[data-positions-body]');
    const existing = [...(body?.querySelectorAll('[data-position-id]') || [])];
    if (existing.length !== positions.length || existing.some((row,index) => row.dataset.positionId !== String(positions[index]?.id))) {
      renderPositions(positions); return;
    }
    positions.forEach((position) => {
      const row = existing.find((candidate) => candidate.dataset.positionId === String(position.id));
      if (!row) return;
      const precision = Number(position.precision_digits || row.dataset.positionPrecision) || 5;
      row.dataset.positionSymbol=String(position.symbol||''); row.dataset.positionSide=String(position.direction||''); row.dataset.positionVolume=String(position.volume||''); row.dataset.positionOpen=String(position.open_price||''); row.dataset.positionSl=position.stop_loss==null?'':String(position.stop_loss); row.dataset.positionTp=position.take_profit==null?'':String(position.take_profit); row.dataset.positionPl=position.account_pl==null?'':String(position.account_pl); row.dataset.positionMark=position.mark_price==null?'':String(position.mark_price);
      const mark = row.querySelector('[data-position-mark]'); if (mark) mark.textContent = position.mark_price == null ? '—' : formatPrice(position.mark_price, precision);
      const pnl = row.querySelector('[data-position-pl]'); if (pnl) pnl.textContent = formatMoney(position.account_pl, currency, true);
    });
  };
  let stateBusy = false, stateStopped = false, stateTimer = null, stateController = null;
  const pollAccountState = async () => {
    clearTimeout(stateTimer);
    if (stateStopped || document.hidden || stateBusy) { if (!stateStopped && !document.hidden) stateTimer=setTimeout(pollAccountState,5000); return; }
    stateBusy = true; stateController = new AbortController();
    try {
      const stateUrl=new URL(root.dataset.stateUrl,window.location.href);stateUrl.searchParams.set('symbol',selected);
      const response = await fetch(stateUrl,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal:stateController.signal});
      const data = await response.json();
      if (response.status===401 && data.login_url) { stateStopped=true; window.location.assign(data.login_url); return; }
      if (!response.ok || !data.success) throw new Error('Account snapshot unavailable');
      applyAccountState(data);
    } catch (error) { if (error.name!=='AbortError') { const connection=root.querySelector('.terminal-account-connected'); if (connection) connection.textContent='Last account values retained · Reconnecting'; } }
    finally { stateBusy=false; if (!stateStopped && !document.hidden) stateTimer=setTimeout(pollAccountState,5000); }
  };
  document.addEventListener('visibilitychange', () => { if (document.hidden) { clearTimeout(stateTimer); stateController?.abort(); } else pollAccountState(); });
  window.addEventListener('pagehide', () => { stateStopped=true; clearTimeout(stateTimer); stateController?.abort(); });
  window.addEventListener('offline',()=>{const connection=root.querySelector('.terminal-account-connected');if(connection)connection.textContent='Offline · Last account values retained';});
  window.addEventListener('online',()=>{if(!stateStopped)pollAccountState();});
  const controlMessage=root.querySelector('[data-trading-control-message]');
  updateOrderAvailability=(controls=null,instrument=null)=>{
    const flag=(value)=>value===true||value===1||value==='1';
    const row=instruments.get(selected)||{};
    const globalTrading=controls?flag(controls.demo_trading_enabled):root.dataset.globalTrading==='1';
    const globalOrders=controls?flag(controls.market_orders_enabled):root.dataset.globalMarketOrders==='1';
    const globalData=controls?flag(controls.demo_market_data_enabled):root.dataset.globalMarketData==='1';
    const status=instrument?.status||'active';
    const categoryEnabled=!instrument||instrument.category_status==='active';
    const instrumentTrading=instrument?flag(instrument.trading_enabled):row.instrumentTrading==='1';
    const instrumentData=instrument?flag(instrument.market_data_enabled):row.instrumentMarketData==='1';
    const enabled=globalTrading&&globalOrders&&globalData&&status==='active'&&categoryEnabled&&instrumentTrading&&instrumentData;
    root.querySelectorAll('[data-terminal-place-order],[data-submit-order]').forEach((button)=>{button.disabled=!enabled;button.setAttribute('aria-disabled',String(!enabled));});
    const reason=!globalTrading?'Demo Trading Temporarily Disabled · Existing positions remain accessible for management and closure.':!globalOrders?'Demo market orders are temporarily disabled.':!globalData?'Demo Market Data Maintenance · Prices are temporarily not updating.':status!=='active'?'This instrument is disabled.':!categoryEnabled?'This instrument category is disabled.':!instrumentTrading?'Demo Trading is disabled for this instrument.':!instrumentData?'Simulated market data is disabled for this instrument.':'';
    if(controlMessage){controlMessage.textContent=reason;controlMessage.hidden=reason==='';}
    const volume=root.querySelector('[data-ticket-volume]');
    if(volume){const min=Number(row.volumeMin)||Number(root.dataset.volumeMin)||0.01,instMax=Number(row.volumeMax)||Number(root.dataset.volumeMax)||100,globalMax=controls?Number(controls.max_order_volume):Number(root.dataset.globalMaxVolume),max=Math.min(instMax,globalMax||instMax),step=Number(row.volumeStep)||Number(root.dataset.volumeStep)||0.01;volume.min=String(min);volume.max=String(max);volume.step=String(step);if(Number(volume.value)<min)volume.value=String(min);if(Number(volume.value)>max)volume.value=String(max);const text=root.querySelector('[data-volume-limits]');if(text)text.textContent=`Demo volume: ${min}–${max} lots · ${step} step`;}
  };
  updateOrderAvailability();
  if (root.dataset.stateUrl) pollAccountState();

  const positionDialog=root.querySelector('[data-position-dialog]');
  let managedPosition=null, managementMode='close';
  const refreshPositionState=async()=>{try{const stateUrl=new URL(root.dataset.stateUrl,window.location.href);stateUrl.searchParams.set('symbol',selected);const response=await fetch(stateUrl,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});const data=await response.json();if(response.ok&&data.success)applyAccountState(data);}catch{}}
  const updateClosePreview=()=>{if(!managedPosition)return;const volume=Number(root.querySelector('[data-position-close-volume]').value)||0;const total=Number(managedPosition.dataset.positionVolume)||0;const fullPl=Number(managedPosition.dataset.positionPl);root.querySelector('[data-position-estimate]').textContent=Number.isFinite(fullPl)&&total>0?formatMoney(fullPl*volume/total,root.querySelector('[data-account-metric="balance"]')?.textContent.match(/[A-Z]{3}/)?.[0]||'USD',true):'—';};
  root.addEventListener('click',(event)=>{const button=event.target.closest('[data-position-action]');if(!button)return;managedPosition=button.closest('[data-position-id]');if(!managedPosition)return;managementMode=button.dataset.positionAction;const closeFields=root.querySelector('[data-position-close-fields]'),modifyFields=root.querySelector('[data-position-modify-fields]');closeFields.hidden=managementMode!=='close';modifyFields.hidden=managementMode!=='modify';root.querySelector('[data-position-dialog-title]').textContent=managementMode==='close'?'Close demo position':'Modify protection levels';root.querySelector('[data-position-dialog-summary]').textContent=`${managedPosition.dataset.positionSymbol} ${managedPosition.dataset.positionSide.toUpperCase()} · ${managedPosition.dataset.positionVolume} lots · opened at ${managedPosition.dataset.positionOpen}`;root.querySelector('[data-position-submit]').textContent=managementMode==='close'?'Close Demo Position':'Save Protection Levels';root.querySelector('[data-position-dialog-error]').hidden=true;if(managementMode==='close'){const volumeInput=root.querySelector('[data-position-close-volume]');volumeInput.min=root.dataset.volumeMin||'0.01';volumeInput.step=root.dataset.volumeStep||'0.01';volumeInput.value=managedPosition.dataset.positionVolume;root.querySelector('[data-position-indicative]').textContent=managedPosition.dataset.positionMark||'—';updateClosePreview();}else{root.querySelector('[data-position-sl-input]').value=managedPosition.dataset.positionSl;root.querySelector('[data-position-tp-input]').value=managedPosition.dataset.positionTp;}positionDialog.showModal();});
  root.querySelector('[data-position-cancel]')?.addEventListener('click',()=>positionDialog.close());
  root.querySelector('[data-position-close-volume]')?.addEventListener('input',updateClosePreview);
  root.querySelector('[data-position-submit]')?.addEventListener('click',async(event)=>{if(!managedPosition)return;const submit=event.currentTarget,error=root.querySelector('[data-position-dialog-error]');submit.disabled=true;error.hidden=true;const body={_csrf:root.dataset.csrf,position_id:Number(managedPosition.dataset.positionId)};let url=root.dataset.closeUrl;if(managementMode==='close'){body.volume=root.querySelector('[data-position-close-volume]').value;body.idempotency_key=crypto.randomUUID().replaceAll('-','');}else{url=root.dataset.modifyUrl;body.stop_loss=root.querySelector('[data-position-sl-input]').value;body.take_profit=root.querySelector('[data-position-tp-input]').value;}try{const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'The position could not be updated.');positionDialog.close();const toast=root.querySelector('[data-order-toast]');if(toast){toast.textContent=managementMode==='close'?`${data.message} ${data.position?.symbol||''} · ${formatMoney(data.position?.realized_pnl,root.querySelector('[data-account-metric="balance"]')?.textContent.match(/[A-Z]{3}/)?.[0]||'USD',true)} at ${data.position?.close_price||''}`:data.message;toast.hidden=false;setTimeout(()=>{toast.hidden=true;},5000);}await refreshPositionState();}catch(err){error.textContent=err.message;error.hidden=false;}finally{submit.disabled=false;}});

  orderButton?.addEventListener('click', async () => {
    const direction = root.querySelector('[data-terminal-direction].is-active')?.dataset.terminalDirection || 'buy';
    const volume = root.querySelector('[data-ticket-volume]').value;
    const slEnabled = root.querySelector('[data-protection-toggle="sl"]').checked;
    const tpEnabled = root.querySelector('[data-protection-toggle="tp"]').checked;
    const originalLabel = `Place Demo ${direction === 'buy' ? 'Buy' : 'Sell'}`;
    orderButton.disabled = true; orderButton.setAttribute('aria-busy', 'true'); orderButton.textContent = 'Submitting demo order…';
    showTicketMessage('Submitting your demo market order…');
    try {
      const response = await fetch(root.dataset.orderUrl, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          _csrf: root.dataset.csrf,
          client_order_id: root.dataset.clientOrderId,
          symbol: selected,
          side: direction,
          order_type: 'market',
          volume,
          stop_loss: slEnabled ? root.querySelector('[data-ticket-sl]').value : null,
          take_profit: tpEnabled ? root.querySelector('[data-ticket-tp]').value : null,
        }),
      });
      const result = await response.json();
      if (response.status === 401 && result.login_url) { window.location.assign(result.login_url); return; }
      if (!response.ok || !result.success) throw new Error(result.message || 'The demo order could not be completed.');
      orderDialog.close();
      root.dataset.clientOrderId = window.crypto?.randomUUID?.().replaceAll('-', '') || Array.from(window.crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
      root.querySelector('[data-protection-toggle="sl"]').checked = false;
      root.querySelector('[data-protection-toggle="tp"]').checked = false;
      root.querySelector('[data-protection-field="sl"]').hidden = true;
      root.querySelector('[data-protection-field="tp"]').hidden = true;
      root.querySelector('[data-ticket-sl]').value = '';
      root.querySelector('[data-ticket-tp]').value = '';
      const price = formatPrice(result.order.execution_price, Number(instruments.get(selected)?.precision) || 5);
      orderToast.textContent = `Demo ${result.order.side.toUpperCase()} executed · ${result.order.symbol} · ${result.order.volume} lots · ${price}`;
      orderToast.hidden = false;
      window.setTimeout(() => { orderToast.hidden = true; }, 6500);
      ticketMessage.hidden = true;
      try { await refreshOrdersAndPositions(); await pollAccountState(); } catch { /* Persisted order remains available on next refresh. */ }
    } catch (error) {
      showTicketMessage(error instanceof Error ? error.message : 'The demo order could not be completed. Please try again.');
      if (error instanceof TypeError) orderToast.textContent = 'The demo order response could not be reached. Check Orders before retrying.';
    } finally {
      orderButton.disabled = false; orderButton.removeAttribute('aria-busy'); orderButton.textContent = originalLabel;
    }
  });

  root.querySelector('[data-ticket-type]')?.addEventListener('change', (event) => {
    root.querySelector('[data-preview-type]').textContent = event.currentTarget.options[event.currentTarget.selectedIndex].text;
  });
  search?.addEventListener('input', applyFilters);

  root.querySelectorAll('[data-terminal-tab]').forEach((tab) => {
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const tabs = [...root.querySelectorAll('[data-terminal-tab]')];
      const index = tabs.indexOf(tab);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
      setTab(tabs[next].dataset.terminalTab, true);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
      event.preventDefault();
      if (search) search.focus();
    }
    if (event.key === 'Escape') {
      if (activePanel) closePanel();
      else if (document.fullscreenElement === root.querySelector('[data-terminal-chart-panel]')) document.exitFullscreen?.();
      else if (root.querySelector('[data-terminal-chart-panel]')?.classList.contains('is-expanded')) root.querySelector('[data-terminal-chart-panel]').classList.remove('is-expanded');
    }
    if (activePanel && event.key === 'Tab') {
      const focusable = [...activePanel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled])')].filter((element) => element.offsetParent !== null);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 980 && activePanel === watchlist) closePanel(false);
    if (window.innerWidth > 720 && activePanel === ticket) closePanel(false);
  });

  let savedTimeframe = '15m';
  let savedTab = 'positions';
  try {
    savedTimeframe = window.localStorage.getItem('multiforex-terminal-timeframe') || savedTimeframe;
    savedTab = window.localStorage.getItem('multiforex-terminal-tab') || savedTab;
  } catch { /* Local preferences are optional. */ }
  const timeframeButton = root.querySelector(`[data-terminal-timeframe="${CSS.escape(savedTimeframe)}"]`);
  if (timeframeButton) timeframeButton.click();
  setTab(savedTab);
  setDirection('buy');
  const params = new URLSearchParams(window.location.search);
  const requestedSymbol = params.get('symbol')?.toUpperCase();
  if (!setInstrument(requestedSymbol || selected, false)) setInstrument(selected, true);

  // Phase 10 can connect its provider to these display-only hooks. This module creates no prices.
  window.TerminalMarketUI = {
    setInstrument(symbol) { return setInstrument(String(symbol).toUpperCase()); },
    updateQuote(symbol, quote) {
      const instrument = instruments.get(String(symbol).toUpperCase());
      if (!instrument || !quote || !Number.isFinite(Number(quote.bid)) || !Number.isFinite(Number(quote.ask))) return false;
      instrument.bid = String(quote.bid);
      instrument.ask = String(quote.ask);
      if (Number.isFinite(Number(quote.spread))) instrument.spread = String(quote.spread);
      const precision = Math.max(0, Math.min(10, Number.parseInt(instrument.precision, 10) || 0));
      const row = rows.find((item) => item.dataset.symbol === instrument.symbol);
      if (row) {
        row.querySelector('.terminal-quote-copy strong').textContent = formatPrice(instrument.bid, precision);
        row.querySelector('.terminal-quote-copy small').textContent = formatPrice(instrument.ask, precision);
        const change = row.querySelector('[data-watchlist-change]');
        if (change) change.textContent = `${Number(quote.change) >= 0 ? '+' : ''}${formatPrice(quote.change, precision)} · ${Number(quote.change_percent) >= 0 ? '+' : ''}${Number(quote.change_percent).toFixed(2)}%`;
      }
      if (selected === instrument.symbol) {
        setInstrument(selected, false);
        for (const key of ['change','change_percent','high','low']) {
          const target = root.querySelector(`[data-market-stat="${key}"]`);
          if (!target || !Number.isFinite(Number(quote[key]))) continue;
          target.textContent = key === 'change_percent' ? `${Number(quote[key]) >= 0 ? '+' : ''}${Number(quote[key]).toFixed(2)}%` : `${key === 'change' && Number(quote[key]) >= 0 ? '+' : ''}${formatPrice(quote[key], precision)}`;
        }
        const updated = root.querySelector('[data-market-stat="timestamp"]');
        if (updated && quote.timestamp) updated.textContent = new Intl.DateTimeFormat('en', { hour:'2-digit', minute:'2-digit', second:'2-digit' }).format(new Date(quote.timestamp));
        const direction = Number(quote.mid) > Number(instrument.lastMid) ? 'up' : (Number(quote.mid) < Number(instrument.lastMid) ? 'down' : 'same');
        instrument.lastMid = String(quote.mid);
        root.querySelectorAll('[data-price-role="bid"],[data-price-role="ask"]').forEach((el) => {
          el.dataset.priceDirection = direction;
          if (direction !== 'same') { el.classList.remove('is-tick-up','is-tick-down'); void el.offsetWidth; el.classList.add(direction === 'up' ? 'is-tick-up' : 'is-tick-down'); }
        });
      }
      root.dispatchEvent(new CustomEvent('terminal:quoteupdate', { detail: { symbol: instrument.symbol } }));
      return true;
    },
    setChartData() { return false; },
    updateCandle() { return false; },
  };
})();
