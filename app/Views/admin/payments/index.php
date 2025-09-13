<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$payments = $payments ?? [];
$role    = $_GET['role']    ?? '';
$status  = $_GET['status']  ?? '';
$purpose = $_GET['purpose'] ?? '';
$emailQ  = $_GET['email']   ?? '';
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Payments (Stripe)</h1>
            <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/payments">Reset</a>
        </div>

        <form class="row g-2 align-items-end mb-3" method="get" action="<?= $base ?>/admin/payments">
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <select class="form-select" name="role">
                    <option value="">Any</option>
                    <?php foreach (['Employer', 'Recruiter', 'Candidate'] as $r): ?>
                        <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Any</option>
                    <?php foreach (['created', 'paid', 'failed', 'canceled'] as $st): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Purpose</label>
                <select class="form-select" name="purpose">
                    <option value="">Any</option>
                    <?php foreach (['credits', 'premium'] as $p): ?>
                        <option value="<?= $p ?>" <?= $purpose === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Email contains</label>
                <input class="form-control" name="email" value="<?= htmlspecialchars($emailQ) ?>" placeholder="user@domain.com">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </form>

        <?php if (!$payments): ?>
            <div class="alert alert-light border">No payment records (try changing filters).</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Purpose</th>
                            <th>Credits</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Session</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <?php
                            $id    = (int)$p['id'];
                            $name  = htmlspecialchars($p['user_name']  ?? '');
                            $email = htmlspecialchars($p['user_email'] ?? '');
                            $role  = htmlspecialchars($p['user_role']  ?? '');
                            $pur   = htmlspecialchars($p['purpose']    ?? '');
                            $cred  = $p['credits'] === null ? '—' : (int)$p['credits'];
                            $amt   = 'RM ' . number_format(((int)$p['amount']) / 100, 2);
                            $st    = htmlspecialchars($p['status'] ?? '');
                            $sid   = htmlspecialchars($p['session_id'] ?? '');
                            $created = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string)$p['created_at'])) : '';
                            ?>
                            <tr>
                                <td>#<?= $id ?></td>
                                <td>
                                    <div class="fw-semibold"><?= $name ?: '—' ?></div>
                                    <div class="text-muted small"><?= $email ?: '—' ?></div>
                                </td>
                                <td><?= $role ?></td>
                                <td><?= ucfirst($pur) ?></td>
                                <td><?= htmlspecialchars((string)$cred) ?></td>
                                <td><?= $amt ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $st === 'paid' ? 'success' : ($st === 'created' ? 'secondary' : ($st === 'failed' ? 'danger' : 'dark')) ?>">
                                        <?= $st ?>
                                    </span>
                                </td>
                                <td class="small text-truncate" style="max-width:220px;" title="<?= $sid ?>"><?= $sid ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($created) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>