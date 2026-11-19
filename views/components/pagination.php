<?php
$currentPage = max(1, (int) ($current ?? 1));
$totalPages = max(1, (int) ($total ?? 1));
$baseUrl = (string) ($url ?? site_url());
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($currentPage > 1): ?><a href="<?= e($baseUrl . '?page=' . ($currentPage - 1)) ?>" rel="prev" aria-label="Previous page">‹</a><?php else: ?><span class="is-disabled" aria-disabled="true" aria-label="Previous page">‹</span><?php endif; ?>
    <?php for ($number = 1; $number <= $totalPages; $number++): ?>
        <?php if ($number === $currentPage): ?><span class="is-current" aria-current="page"><?= $number ?></span><?php else: ?><a href="<?= e($baseUrl . '?page=' . $number) ?>" aria-label="Page <?= $number ?>"><?= $number ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?><a href="<?= e($baseUrl . '?page=' . ($currentPage + 1)) ?>" rel="next" aria-label="Next page">›</a><?php else: ?><span class="is-disabled" aria-disabled="true" aria-label="Next page">›</span><?php endif; ?>
</nav>
