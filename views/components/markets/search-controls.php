<div class="markets-controls">
    <label class="market-search"><span class="sr-only">Search instruments by symbol or name</span><span aria-hidden="true">⌕</span><input type="search" value="<?= e($search ?? '') ?>" placeholder="Search symbol or instrument" autocomplete="off" data-market-search><button type="button" data-search-clear aria-label="Clear search" hidden>×</button></label>
    <?php if (!empty($category['groups'])): ?><div class="market-filter-group" role="group" aria-label="Filter instruments by group"><button type="button" class="is-selected" data-market-filter="all">All</button><?php foreach ($category['groups'] as $filterKey => $filterLabel): ?><button type="button" data-market-filter="<?= e($filterKey) ?>"><?= e($filterLabel) ?></button><?php endforeach; ?></div><?php endif; ?>
    <p class="markets-result-count" aria-live="polite" data-market-result-count></p>
</div>
