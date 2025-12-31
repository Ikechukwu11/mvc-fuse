<?php
/** @var Engine\Support\Paginator $paginator */
?>
<div style="display:flex; align-items:center; gap:8px;">
    <!-- Previous Link -->
    <?php if ($paginator->onFirstPage()): ?>
        <span style="opacity:.5; cursor:not-allowed; padding:6px 10px; border:1px solid #e7e9f3; background:#fff; border-radius:6px;">Prev</span>
    <?php else: ?>
        <button fuse:click="pageGo(<?= $paginator->currentPage() - 1 ?>)" style="padding:6px 10px; border:1px solid #e7e9f3; background:#fff; border-radius:6px; cursor:pointer;">Prev</button>
    <?php endif; ?>

    <!-- Page Links -->
    <?php foreach ($paginator->elements() as $page => $url): ?>
        <?php if ($page == $paginator->currentPage()): ?>
            <span style="padding:6px 10px; border:1px solid #d0d7ff; background:#e9edff; color:#1d2dd9; font-weight:600; border-radius:6px;"><?= $page ?></span>
        <?php else: ?>
            <button fuse:click="pageGo(<?= $page ?>)" style="padding:6px 10px; border:1px solid #e7e9f3; background:#fff; border-radius:6px; cursor:pointer;"><?= $page ?></button>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Next Link -->
    <?php if ($paginator->hasMorePages()): ?>
        <button fuse:click="pageGo(<?= $paginator->currentPage() + 1 ?>)" style="padding:6px 10px; border:1px solid #e7e9f3; background:#fff; border-radius:6px; cursor:pointer;">Next</button>
    <?php else: ?>
        <span style="opacity:.5; cursor:not-allowed; padding:6px 10px; border:1px solid #e7e9f3; background:#fff; border-radius:6px;">Next</span>
    <?php endif; ?>
</div>
