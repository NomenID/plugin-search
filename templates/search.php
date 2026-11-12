<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file in this repository for the full text.
 * Source: https://github.com/NomenID/plugin-search
 * Project: https://nomenid.org/w/plugin-search
 */

/** @var string $query @var array $result @var int $page @var int $pages @var int $total @var array $items @var string $base */
$hasQuery = $query !== '';
?>
<div class="search-page">
    <form class="search-form" method="get" action="<?= htmlspecialchars($base) ?>" role="search">
        <input
            type="search"
            name="q"
            value="<?= htmlspecialchars($query) ?>"
            placeholder="<?= t('Search pages') ?>"
            aria-label="<?= t('Search') ?>"
            autocomplete="off"
            autofocus
        >
        <button type="submit" class="search-submit"><?= t('Search') ?></button>
    </form>

    <?php if ($hasQuery): ?>
        <p class="search-meta">
            <?php if ($total > 0): ?>
                <?= t('Results for {:query}', ['query' => $query]) ?> ·
                <?= t('Page {:page} of {:pages} · {:total} in total', ['page' => $page, 'pages' => max(1, $pages), 'total' => $total]) ?>
            <?php endif; ?>
        </p>

        <?php if ($total === 0): ?>
            <p class="search-empty"><?= t('No results for {:query}', ['query' => $query]) ?></p>
        <?php else: ?>
            <ol class="search-results">
                <?php foreach ($items as $item): ?>
                    <li class="search-result">
                        <a class="search-result__title" href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                        <?php if (($item['excerpt'] ?? '') !== ''): ?>
                            <p class="search-result__excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($pages > 1): ?>
                <nav class="search-pager">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(\Nomen\Plugin\Search\Search::pageUrl($base, $query, $page - 1)) ?>" rel="prev"><?= t('Previous') ?></a>
                    <?php endif; ?>
                    <span class="search-pager__count"><?= t('Page {:page} of {:pages} · {:total} in total', ['page' => $page, 'pages' => $pages, 'total' => $total]) ?></span>
                    <?php if ($page < $pages): ?>
                        <a href="<?= htmlspecialchars(\Nomen\Plugin\Search\Search::pageUrl($base, $query, $page + 1)) ?>" rel="next"><?= t('Next') ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
