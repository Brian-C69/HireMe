<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$table = htmlspecialchars($params['table'] ?? '');
$mode  = $mode ?? 'create';
$pk    = \App\Controllers\AdminController::TABLES[$params['table']];
?>
<section class="py-4">
    <div class="container" style="max-width: 860px;">
        <h1 class="h5 mb-3"><?= $mode === 'create' ? 'Create in ' : 'Edit ' ?><?= $table ?></h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $base ?>/admin/t/<?= $table ?><?= $mode === 'edit' ? '/' . urlencode((string)$row[$pk]) . '/edit' : '' ?>" method="post">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <?php
                    $pdo = \App\Core\DB::conn();
                    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($cols as $c):
                        $name = $c['Field'];
                        $ai   = strpos((string)$c['Extra'], 'auto_increment') !== false;
                        $val  = $mode === 'edit' ? (string)($row[$name] ?? '') : '';
                        $type = strtolower((string)$c['Type']);
                    ?>
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars($name) ?> <?= $ai ? '(auto)' : '' ?></label>
                            <?php if ($ai): ?>
                                <input class="form-control" value="<?= htmlspecialchars($val) ?>" disabled>
                            <?php else: ?>
                                <?php if (strpos($type, 'text') !== false || strpos($type, 'blob') !== false): ?>
                                    <textarea class="form-control" name="<?= htmlspecialchars($name) ?>" rows="4"><?= htmlspecialchars($val) ?></textarea>
                                <?php else: ?>
                                    <input class="form-control" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($val) ?>">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><?= $mode === 'create' ? 'Create' : 'Save' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/t/<?= $table ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>