(() => {
    'use strict';
    document.querySelectorAll('.markets-page').forEach((page) => {
        const searches = [...page.querySelectorAll('[data-market-search]')];
        const rows = [...page.querySelectorAll('[data-instrument-row]')];
        const cards = [...page.querySelectorAll('[data-category-card]')];
        const resultLabels = [...page.querySelectorAll('[data-market-result-count]')];
        const clearButtons = [...page.querySelectorAll('[data-search-clear]')];
        const filters = [...page.querySelectorAll('[data-market-filter]')];
        const categoryFilters = [...page.querySelectorAll('[data-category-filter]')];
        let group = 'all';
        let category = 'all';
        let query = (searches[0]?.value || '').trim().toLowerCase();

        const update = () => {
            let visibleRows = 0;
            rows.forEach((row) => {
                const matchesQuery = row.dataset.search.includes(query);
                const matchesGroup = group === 'all' || row.dataset.region === group || row.dataset.pairGroup === group;
                row.hidden = !(matchesQuery && matchesGroup);
                if (!row.hidden) visibleRows += 1;
            });
            let visibleCards = 0;
            cards.forEach((card) => {
                const matchesQuery = card.dataset.search.includes(query);
                const matchesCategory = category === 'all' || card.dataset.categorySlug === category;
                card.hidden = !(matchesQuery && matchesCategory);
                if (!card.hidden) visibleCards += 1;
            });
            resultLabels.forEach((label) => {
                const total = rows.length ? visibleRows : visibleCards;
                label.textContent = `${total} ${rows.length ? 'instrument' : 'market'}${total === 1 ? '' : 's'} shown`;
            });
            clearButtons.forEach((button) => { button.hidden = !query; });
            page.querySelectorAll('[data-market-table]').forEach((table) => {
                let empty = table.querySelector('[data-search-empty]');
                const visible = table.querySelectorAll('[data-instrument-row]:not([hidden])').length;
                if (!visible && rows.length) {
                    if (!empty) {
                        empty = document.createElement('p');
                        empty.dataset.searchEmpty = '';
                        empty.className = 'markets-search-empty';
                        empty.textContent = 'No instruments match this search or filter. Try another symbol or name.';
                        table.after(empty);
                    }
                    empty.hidden = false;
                } else if (empty) empty.hidden = true;
            });
        };
        searches.forEach((input) => input.addEventListener('input', () => {
            query = input.value.trim().toLowerCase();
            searches.forEach((other) => { if (other !== input) other.value = input.value; });
            update();
        }));
        clearButtons.forEach((button) => button.addEventListener('click', () => {
            query = '';
            searches.forEach((input) => { input.value = ''; });
            update();
            searches[0]?.focus();
        }));
        filters.forEach((button) => button.addEventListener('click', () => {
            group = button.dataset.marketFilter;
            filters.forEach((other) => other.classList.toggle('is-selected', other === button));
            update();
        }));
        categoryFilters.forEach((button) => button.addEventListener('click', () => {
            category = button.dataset.categoryFilter;
            categoryFilters.forEach((other) => other.classList.toggle('is-selected', other === button));
            update();
        }));
        page.querySelectorAll('[data-sort]').forEach((button) => {
            let ascending = true;
            button.addEventListener('click', () => {
                const tbody = button.closest('table')?.querySelector('tbody');
                if (!tbody) return;
                const key = button.dataset.sort;
                const sorted = [...tbody.querySelectorAll('[data-instrument-row]')].sort((a, b) => {
                    const left = key === 'spread' ? Number(a.dataset.spread) : a.dataset.symbol;
                    const right = key === 'spread' ? Number(b.dataset.spread) : b.dataset.symbol;
                    return (left > right ? 1 : left < right ? -1 : 0) * (ascending ? 1 : -1);
                });
                sorted.forEach((row) => tbody.append(row));
                ascending = !ascending;
                button.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
            });
        });
        update();
    });
})();
