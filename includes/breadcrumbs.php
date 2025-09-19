<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/navbar.php'; // ya define $BASE y URLs útiles

function render_breadcrumb(array $items): void
{
    if (empty($items)) return;
    echo '<nav aria-label="breadcrumb" class="bg-body-tertiary">';
    echo '<ol class="breadcrumb container py-2 my-2">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        $label = htmlspecialchars($it['label'] ?? '');
        $href  = $it['href']  ?? null;
        if ($i === $last || empty($href)) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . htmlspecialchars($href) . '">' . $label . '</a></li>';
        }
    }
    echo '</ol></nav>';
}
