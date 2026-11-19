(() => {
  const script = document.currentScript;
  const endpoint = script?.dataset.quotesUrl;
  if (!endpoint || (!document.querySelector('[data-market-symbol]') && !document.querySelector('[data-watchlist-root]'))) return;
  let timer, busy = false, controller = null;
  const format = (value, digits) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(n);
  };
  const render = (quote) => {
    [...document.querySelectorAll('[data-market-symbol]')].filter((row) => row.dataset.marketSymbol === quote.symbol).forEach((row) => {
      const digits = Math.max(0, Math.min(10, Number(row.dataset.precision) || Number(quote.precision) || 2));
      row.querySelectorAll('[data-market-field]').forEach((target) => {
        const field = target.dataset.marketField;
        if (field === 'change_percent') {
          target.textContent = `${Number(quote.change_percent) >= 0 ? '+' : ''}${Number(quote.change_percent).toFixed(2)}%`;
          target.classList.toggle('change-positive', Number(quote.change_percent) >= 0);
          target.classList.toggle('change-negative', Number(quote.change_percent) < 0);
        } else if (field === 'change') {
          target.textContent = `${Number(quote.change) >= 0 ? '+' : ''}${format(quote.change, digits)}`;
          target.classList.toggle('change-positive', Number(quote.change) >= 0);
          target.classList.toggle('change-negative', Number(quote.change) < 0);
        } else if (field === 'spread') {
          const note = target.querySelector('.unit-note');
          target.textContent = format(quote.spread, digits);
          if (note) { note.textContent = 'price difference'; target.append(note); }
        } else {
          target.textContent = format(quote[field], digits);
        }
      });
    });
  };
  const schedule = (ms) => { clearTimeout(timer); if (!document.hidden) timer = setTimeout(poll, ms); };
  const poll = async () => {
    if (busy || document.hidden) return;
    busy = true; controller = new AbortController();
    try {
      const symbols = [...new Set([...document.querySelectorAll('[data-market-symbol]')].map((row) => row.dataset.marketSymbol).filter(Boolean))];
      if (!symbols.length || symbols.length > 40) { schedule(15000); return; }
      const url = new URL(endpoint, location.href); url.searchParams.set('symbols', symbols.join(','));
      const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error('Demo quotes unavailable');
      data.quotes.forEach(render);
      schedule(8000);
    } catch (error) { if (error.name !== 'AbortError') schedule(15000); }
    finally { busy = false; }
  };
  document.addEventListener('visibilitychange', () => { if (document.hidden) { clearTimeout(timer); controller?.abort(); } else poll(); });
  poll();
})();
