(() => {
  const root = document.querySelector('[data-trading-terminal]');
  if (!root || !window.TerminalMarketUI) return;
  const quotesUrl = root.dataset.quotesUrl;
  const candlesUrl = root.dataset.candlesUrl;
  const chart = root.querySelector('.terminal-chart-canvas');
  const status = root.querySelector('[data-market-connection]');
  const chartState = root.querySelector('[data-chart-state]');
  const chartFooter = root.querySelector('[data-chart-footer]');
  let polling = false;
  let stopped = false;
  let delay = 1600;
  let quoteController = null;
  let candleController = null;
  let candleSequence = 0;
  let activeCandles = [];
  const timeframeSeconds = { '1m':60, '5m':300, '15m':900, '30m':1800, '1H':3600, '4H':14400, '1D':86400 };

  const selectedSymbol = () => root.querySelector('[data-selected-symbol]')?.textContent.trim() || root.dataset.initialSymbol;
  const timeframe = () => root.querySelector('[data-terminal-timeframe].is-active')?.dataset.terminalTimeframe || '15m';
  const setStatus = (text, state) => {
    if (status) status.textContent = text;
    root.dataset.marketState = state;
  };
  const urlWith = (base, params) => {
    const url = new URL(base, window.location.href);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    return url;
  };

  const drawCandles = (candles) => {
    activeCandles = candles;
    chart.querySelector('[data-candle-plot]')?.remove();
    const empty = chart.querySelector('.terminal-chart-empty');
    if (!candles.length) {
      if (empty) { empty.hidden = false; empty.querySelector('strong').textContent = 'Demo candle history is unavailable'; }
      chartState.textContent = 'Demo history unavailable';
      return;
    }
    if (empty) empty.hidden = true;
    const values = candles.flatMap((c) => [Number(c.high), Number(c.low)]).filter(Number.isFinite);
    const min = Math.min(...values), max = Math.max(...values);
    const range = Math.max(max - min, Math.abs(max) * 0.00001, 0.00000001);
    const y = (value) => 10 + ((max - value) / range) * 265;
    const row = root.querySelector(`[data-terminal-row][data-symbol="${CSS.escape(selectedSymbol())}"]`);
    const precision = Math.max(0, Math.min(10, Number(row?.dataset.precision) || 2));
    const priceFormat = new Intl.NumberFormat('en-US', { minimumFractionDigits: precision, maximumFractionDigits: precision });
    root.querySelectorAll('.terminal-axis-y span').forEach((label, index, labels) => { label.textContent = priceFormat.format(max - ((max - min) * index / Math.max(1, labels.length - 1))); });
    const width = 800, plotWidth = 750, step = plotWidth / candles.length;
    const type = chart.dataset.chartType || 'candles';
    const closes = candles.map((c, i) => `${i ? 'L' : 'M'} ${(i * step + step / 2).toFixed(2)} ${y(Number(c.close)).toFixed(2)}`).join(' ');
    let content = '';
    if (type === 'candles') {
      content = candles.map((c, i) => {
        const x = i * step + step / 2, open = Number(c.open), close = Number(c.close);
        const color = close >= open ? '#42c7a0' : '#f07883';
        const top = Math.min(y(open), y(close)), height = Math.max(1.2, Math.abs(y(open) - y(close))), body = Math.max(1.2, Math.min(8, step * 0.62));
        return `<path d="M${x.toFixed(2)} ${y(Number(c.high)).toFixed(2)}V${y(Number(c.low)).toFixed(2)}" stroke="${color}" stroke-width="1"/><rect x="${(x-body/2).toFixed(2)}" y="${top.toFixed(2)}" width="${body.toFixed(2)}" height="${height.toFixed(2)}" fill="${color}"/>`;
      }).join('');
    } else {
      const area = type === 'area' ? `<path d="${closes} L${((candles.length-1)*step+step/2).toFixed(2)} 290 L${step/2} 290 Z" fill="url(#demoChartFill)"/>` : '';
      content = `${area}<path d="${closes}" fill="none" stroke="#5bc4bd" stroke-width="2" vector-effect="non-scaling-stroke"/>`;
    }
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.dataset.candlePlot = '';
    svg.setAttribute('viewBox', `0 0 ${width} 300`);
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = `<defs><linearGradient id="demoChartFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#5bc4bd" stop-opacity=".22"/><stop offset="1" stop-color="#5bc4bd" stop-opacity="0"/></linearGradient></defs>${content}`;
    chart.append(svg);
    chartState.textContent = `${selectedSymbol()} · ${timeframe()} · DEMO DATA`;
    chartFooter.textContent = `${candles.length} simulated candles`;
  };

  const loadCandles = async () => {
    candleController?.abort();
    candleController = new AbortController();
    const sequence = ++candleSequence;
    const symbol = selectedSymbol(), tf = timeframe();
    chartState.textContent = `Loading ${tf} demo history…`;
    const empty = chart.querySelector('.terminal-chart-empty');
    if (empty) empty.hidden = false;
    try {
      const response = await fetch(urlWith(candlesUrl, { symbol, timeframe: tf, limit: 300 }), { headers: { Accept: 'application/json' }, cache: 'no-store', signal: candleController.signal });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error('Candle data unavailable');
      if (sequence !== candleSequence || symbol !== selectedSymbol() || tf !== timeframe()) return;
      drawCandles(data.candles);
    } catch (error) {
      if (error.name === 'AbortError' || sequence !== candleSequence) return;
      chartState.textContent = 'Reconnecting to demo candle history…';
      chartFooter.textContent = 'Last chart retained while reconnecting';
      if (!chart.querySelector('[data-candle-plot]') && empty) empty.hidden = false;
      window.setTimeout(() => { if (!document.hidden && selectedSymbol() === symbol && timeframe() === tf && sequence === candleSequence) loadCandles(); }, 6000);
    }
  };

  const poll = async () => {
    if (stopped || polling || document.hidden) return schedule(document.hidden ? 10000 : delay);
    polling = true;
    quoteController = new AbortController();
    const symbols = [...root.querySelectorAll('[data-terminal-row]')].map((row) => row.dataset.symbol);
    try {
      const response = await fetch(urlWith(quotesUrl, { symbols: symbols.join(',') }), { headers: { Accept: 'application/json' }, cache: 'no-store', signal: quoteController.signal });
      const data = await response.json();
      if (!response.ok || !data.success || !Array.isArray(data.quotes)) throw new Error('Quote data unavailable');
      data.quotes.forEach((quote) => window.TerminalMarketUI.updateQuote(quote.symbol, quote));
      setStatus('Demo Data Connected', 'connected');
      delay = 1600;
      const active = data.quotes.find((quote) => quote.symbol === selectedSymbol());
      if (active) window.TerminalMarketUI.updateCandle(active);
    } catch (error) {
      if (error.name !== 'AbortError') {
        setStatus(delay >= 12000 ? 'Demo Data Unavailable · Reconnecting' : 'Reconnecting to Demo Market Data…', 'reconnecting');
        delay = Math.min(15000, Math.max(3200, delay * 1.8));
      }
    } finally {
      polling = false;
      if (!stopped) schedule(document.hidden ? 10000 : delay);
    }
  };
  let timer = null;
  const schedule = (ms) => { clearTimeout(timer); if (!stopped && !document.hidden) timer = setTimeout(poll, ms); };

  root.addEventListener('terminal:instrumentchange', loadCandles);
  root.addEventListener('terminal:timeframechange', loadCandles);
  root.addEventListener('terminal:charttypechange', loadCandles);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearTimeout(timer); quoteController?.abort(); }
    else { delay = 0; poll(); loadCandles(); }
  });
  const originalUpdate = window.TerminalMarketUI.updateQuote;
  window.TerminalMarketUI.updateQuote = (symbol, quote) => {
    window.__multiForexLastQuotes ||= {};
    window.__multiForexLastQuotes[symbol] = quote;
    return originalUpdate(symbol, quote);
  };
  window.TerminalMarketUI.updateCandle = (quote) => {
    if (!quote || quote.symbol !== selectedSymbol()) return false;
    if (!chart.querySelector('[data-candle-plot]') || activeCandles.length === 0) return false;
    const seconds = timeframeSeconds[timeframe()] || 60;
    const quoteTime = Number.isFinite(Date.parse(quote.timestamp)) ? Math.floor(Date.parse(quote.timestamp) / 1000) : Math.floor(Date.now() / 1000);
    const timestamp = Math.floor(quoteTime / seconds) * seconds;
    let last = activeCandles[activeCandles.length - 1];
    if (Number(last.time) < timestamp) {
      last = { timeframe: timeframe(), time: timestamp, open_time: new Date(timestamp * 1000).toISOString().slice(0,19).replace('T',' '), open: quote.mid, high: quote.mid, low: quote.mid, close: quote.mid, tick_count: 1, is_closed: 0 };
      activeCandles.push(last);
      if (activeCandles.length > 300) activeCandles.shift();
    } else {
      last.high = Math.max(Number(last.high), Number(quote.mid));
      last.low = Math.min(Number(last.low), Number(quote.mid));
      last.close = quote.mid;
      last.tick_count = Number(last.tick_count || 0) + 1;
    }
    drawCandles(activeCandles);
    return true;
  };

  root.querySelectorAll('[data-terminal-chart-type]').forEach((button) => button.addEventListener('click', () => {
    chart.dataset.chartType = button.dataset.terminalChartType;
    root.dispatchEvent(new CustomEvent('terminal:charttypechange'));
  }));
  loadCandles();
  poll();
  window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); quoteController?.abort(); candleController?.abort(); });
})();
