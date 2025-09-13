<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$role = $_GET['role'] ?? 'Employer';
$rows = $rows ?? [];
$csrf = $csrf ?? '';
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Credits — <?= htmlspecialchars($role) ?></h1>
            <div class="btn-group">
                <a class="btn btn-outline-secondary <?= $role === 'Employer' ? 'active' : '' ?>" href="<?= $base ?>/admin/credits?role=Employer">Employers</a>
                <a class="btn btn-outline-secondary <?= $role === 'Recruiter' ? 'active' : '' ?>" href="<?= $base ?>/admin/credits?role=Recruiter">Recruiters</a>
            </div>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No records.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th style="width: 140px;">Credits</th>
                            <th>Updated</th>
                            <th class="text-end" style="width: 320px;">Adjust</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id    = (int)$r['id'];
                            $name  = htmlspecialchars($r['name'] ?? '');
                            $email = htmlspecialchars($r['email'] ?? '');
                            $cr    = (int)($r['credits'] ?? 0);
                            $upd   = !empty($r['updated_at']) ? date('d/m/Y H:i', strtotime((string)$r['updated_at'])) : '';
                            ?>
                            <tr>
                                <td>#<?= $id ?></td>
                                <td class="fw-semibold"><?= $name ?></td>
                                <td class="text-muted small"><?= $email ?></td>
                                <td><span class="badge text-bg-dark"><?= $cr ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars($upd) ?></td>
                                <td class="text-end">
                                    <form class="d-inline-flex gap-2 align-items-center" action="<?= $base ?>/admin/credits/adjust" method="post">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="user_role" value="<?= htmlspecialchars($role) ?>">
                                        <input type="hidden" name="user_id" value="<?= $id ?>">
                                        <input type="number" class="form-control form-control-sm" name="delta" style="width:120px;" placeholder="+10 or -5" required>
                                        <input type="text" class="form-control form-control-sm" name="reason" style="width:200px;" placeholder="Reason (optional)">
                                        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
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