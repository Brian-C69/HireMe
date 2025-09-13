<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$rows = $rows ?? [];
$csrf = htmlspecialchars($csrf ?? '');
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Admins</h1>
            <a class="btn btn-primary" href="<?= $base ?>/admin/admins/create">New Admin</a>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No admins yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width:90px;">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Last Login</th>
                            <th class="text-end" style="width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id = (int)$r['admin_id'];
                            $name = htmlspecialchars($r['full_name'] ?? '');
                            $email = htmlspecialchars($r['email'] ?? '');
                            $phone = htmlspecialchars($r['phone'] ?? '');
                            $last = !empty($r['last_login_at']) ? date('d/m/Y H:i', strtotime((string)$r['last_login_at'])) : '—';
                            ?>
                            <tr>
                                <td>#<?= $id ?></td>
                                <td class="fw-semibold"><?= $name ?: '—' ?></td>
                                <td class="text-muted small"><?= $email ?></td>
                                <td class="text-muted small"><?= $phone ?: '—' ?></td>
                                <td class="text-muted small"><?= $last ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/admin/admins/<?= $id ?>/edit">Edit</a>
                                    <form class="d-inline" action="<?= $base ?>/admin/admins/<?= $id ?>/delete" method="post" onsubmit="return confirm('Delete this admin?');">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>