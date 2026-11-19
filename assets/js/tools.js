(() => {
    'use strict';
    const money = (value, currency) => {
        try { return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: currency === 'JPY' ? 0 : 2 }).format(value); }
        catch { return `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 })} ${currency}`; }
    };
    document.querySelectorAll('[data-calculator-form]').forEach((form) => {
        const error = form.querySelector('[data-calculator-error]');
        const result = document.querySelector('[data-calculator-result]');
        const selectedInstrument = form.querySelector('[data-instrument-select]');
        const setResult = (label, value, secondary, explanation) => {
            result.querySelector('.calculator-result-placeholder').hidden = true;
            const content = result.querySelector('[data-result-content]');
            content.hidden = false;
            content.querySelector('[data-result-label]').textContent = label;
            content.querySelector('[data-result-value]').textContent = value;
            content.querySelector('[data-result-secondary]').textContent = secondary;
            content.querySelector('[data-result-explanation]').textContent = explanation;
        };
        const clearResult = () => {
            if (!result) return;
            result.querySelector('.calculator-result-placeholder').hidden = false;
            result.querySelector('[data-result-content]').hidden = true;
            error.hidden = true;
            error.textContent = '';
        };
        selectedInstrument?.addEventListener('change', () => {
            const option = selectedInstrument.selectedOptions[0];
            ['price', 'open_price', 'close_price'].forEach((name) => {
                const input = form.elements.namedItem(name);
                if (input) input.value = option.dataset.price || '';
            });
            const margin = form.querySelector('[data-margin-display]');
            if (margin && option.dataset.margin) margin.textContent = `${(Number(option.dataset.margin) * 100).toFixed(2)}%`;
        });
        form.addEventListener('reset', () => window.setTimeout(clearResult));
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            error.hidden = true;
            error.textContent = '';
            if (!form.reportValidity()) return;
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;
            submit.dataset.originalText = submit.textContent;
            submit.textContent = 'Calculating…';
            try {
                const response = await fetch(form.dataset.endpoint, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const payload = await response.json();
                if (!response.ok || !payload.success) throw new Error(payload.message || 'The calculation could not be completed.');
                const data = payload.data;
                switch (form.dataset.kind) {
                    case 'forex-calculator':
                        setResult('Estimated value per pip', money(data.pip_value, data.account_currency), `Estimated trade value: ${money(data.trade_value, data.account_currency)} · pip size ${data.pip_size}`, data.explanation);
                        break;
                    case 'margin-calculator':
                        setResult('Estimated required margin', money(data.margin, data.currency), `Configured demo margin rate: ${(data.margin_rate * 100).toFixed(2)}% · ${data.symbol}`, data.explanation);
                        break;
                    case 'profit-loss-calculator': {
                        const label = data.profit_loss > 0 ? 'Illustrative profit' : data.profit_loss < 0 ? 'Illustrative loss' : 'Illustrative break even';
                        setResult(label, money(data.profit_loss, data.currency), `${data.direction.toUpperCase()} · ${data.symbol} · quote currency ${data.currency}`, data.explanation);
                        break;
                    }
                    case 'currency-converter':
                        setResult('Converted demo amount', `${money(data.converted, data.to)} ${data.to}`, `Demo midpoint rate: 1 ${data.from} = ${Number(data.rate).toLocaleString('en-US', { maximumFractionDigits: 6 })} ${data.to}`, data.explanation);
                        break;
                    default: throw new Error('This calculator is unavailable.');
                }
            } catch (exception) {
                error.textContent = exception instanceof TypeError ? 'The calculator service is not reachable right now. Please try again.' : exception.message;
                error.hidden = false;
            } finally {
                submit.disabled = false;
                submit.textContent = 'Calculate estimate ↗';
            }
        });
    });
})();
