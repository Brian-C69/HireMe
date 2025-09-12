<?php $base = defined('BASE_URL') ? BASE_URL : ''; ?>
<section class="py-4">
    <div class="container">
        <h1 class="h4 mb-3">Tables</h1>
        <ul class="list-group">
            <?php foreach ($tables as $t): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?= htmlspecialchars($t) ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/admin/t/<?= htmlspecialchars($t) ?>">Open</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>