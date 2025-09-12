<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$table = htmlspecialchars($params['table'] ?? '');
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Table: <?= $table ?></h1>
            <a class="btn btn-primary" href="<?= $base ?>/admin/t/<?= $table ?>/create">Create</a>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No rows.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <?php foreach ($cols as $c): ?><th><?= htmlspecialchars($c['Field']) ?></th><?php endforeach; ?>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pk = $cols[0]['Field']; // not always first, but we know it in controller; we can find it:
                        foreach ($cols as $c) if ($c['Field'] === \App\Controllers\AdminController::TABLES[$params['table']]) {
                            $pk = $c['Field'];
                            break;
                        }
                        foreach ($rows as $r):
                        ?>
                            <tr>
                                <?php foreach ($cols as $c): $f = $c['Field']; ?>
                                    <td class="small"><?= htmlspecialchars((string)($r[$f] ?? '')) ?></td>
                                <?php endforeach; ?>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/admin/t/<?= $table ?>/<?= urlencode((string)$r[$pk]) ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/admin/t/<?= $table ?>/<?= urlencode((string)$r[$pk]) ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete row?');">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/t/<?= $table ?>?page=1&per=<?= $per ?>">First</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/t/<?= $table ?>?page=<?= max(1, $page - 1) ?>&per=<?= $per ?>">Prev</a></li>
                        <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/t/<?= $table ?>?page=<?= min($pages, $page + 1) ?>&per=<?= $per ?>">Next</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/t/<?= $table ?>?page=<?= $pages ?>&per=<?= $per ?>">Last</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>