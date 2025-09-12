<?php
$base  = defined('BASE_URL') ? BASE_URL : '';
$rows  = $rows ?? [];
$csrf  = htmlspecialchars($_SESSION['csrf'] ?? '');
$page  = (int)($_GET['page'] ?? ($page ?? 1));
$pages = (int)($pages ?? 1);
$per   = (int)($_GET['per'] ?? 20);
$q     = (string)($_GET['q'] ?? '');
$type  = (string)($_GET['type'] ?? '');
$qs = function (array $overrides = []) use ($q, $type, $per, $page) {
    $params = array_merge(['q' => $q, 'type' => $type, 'per' => $per, 'page' => $page], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    if (($params['page'] ?? 1) == 1) unset($params['page']);
    return http_build_query($params);
};
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Employers</h1>
            <a class="btn btn-primary" href="<?= $base ?>/admin/employers/create">Add Employer</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/admin/employers">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Company, email, contact, location...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="">All</option>
                            <option value="account" <?= $type === 'account' ? 'selected' : '' ?>>Employer Accounts</option>
                            <option value="client" <?= $type === 'client' ? 'selected' : '' ?>>Client Companies</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <select class="form-select" name="per">
                            <?php foreach ([20, 50, 100] as $n): ?>
                                <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/employers">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No employers found.</div>
        <?php else: ?>
            <!-- Bulk actions -->
            <form id="bulk-form" action="<?= $base ?>/admin/employers/bulk" method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Bulk actions:</div>
                    <select id="bulk-action" name="bulk_action" class="form-select form-select-sm w-auto" disabled>
                        <option value="">Choose…</option>
                        <option value="client_on">Mark as Client Company</option>
                        <option value="client_off">Unmark Client Company</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button id="bulk-apply" type="submit" class="btn btn-sm btn-secondary" disabled>Apply</button>
                    <span id="bulk-count" class="text-muted small ms-2">0 selected</span>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle" id="employers-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input class="form-check-input" type="checkbox" id="select-all"></th>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Industry</th>
                            <th>Location</th>
                            <th>Client?</th>
                            <th>Recruiter</th>
                            <th>Jobs</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id   = (int)$r['employer_id'];
                            $logo = trim((string)($r['company_logo'] ?? ''));
                            $name = htmlspecialchars((string)($r['company_name'] ?? ''));
                            $email = htmlspecialchars((string)($r['email'] ?? ''));
                            $ind  = htmlspecialchars((string)($r['industry'] ?? ''));
                            $loc  = htmlspecialchars((string)($r['location'] ?? ''));
                            $cli  = (int)($r['is_client_company'] ?? 0);
                            $rec  = htmlspecialchars((string)($r['recruiter_name'] ?? ''));
                            $jobs = (int)($r['jobs_count'] ?? 0);
                            $upd  = !empty($r['updated_at']) ? date('d/m/Y', strtotime((string)$r['updated_at'])) : '';
                            ?>
                            <tr>
                                <td><input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $id ?>"></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="border rounded bg-light d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                            <?php if ($logo): ?>
                                                <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                                            <?php else: ?>
                                                <span class="small text-muted">Logo</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="fw-semibold"><?= $name ?></span>
                                    </div>
                                </td>
                                <td><?= $email ?: '—' ?></td>
                                <td><?= $ind ?: '—' ?></td>
                                <td><?= $loc ?: '—' ?></td>
                                <td><?= $cli ? '<span class="badge text-bg-warning">Client</span>' : '<span class="badge text-bg-light border">Account</span>' ?></td>
                                <td><?= $rec ?: '—' ?></td>
                                <td><?= number_format($jobs) ?></td>
                                <td><?= htmlspecialchars($upd) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/admin/employers/<?= $id ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/admin/employers/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete this employer (and related jobs/apps)?');">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
                (function() {
                    const selectAll = document.getElementById('select-all');
                    const checks = Array.from(document.querySelectorAll('.row-check'));
                    const bulkAction = document.getElementById('bulk-action');
                    const bulkApply = document.getElementById('bulk-apply');
                    const bulkCount = document.getElementById('bulk-count');

                    function updateUI() {
                        const selected = checks.filter(c => c.checked);
                        const hasSel = selected.length > 0;
                        bulkAction.disabled = !hasSel;
                        bulkApply.disabled = !hasSel;
                        bulkCount.textContent = hasSel ? (selected.length + ' selected') : '0 selected';
                        selectAll.checked = hasSel && selected.length === checks.length;
                        selectAll.indeterminate = hasSel && selected.length !== checks.length;
                    }

                    selectAll?.addEventListener('change', e => {
                        checks.forEach(c => c.checked = e.target.checked);
                        updateUI();
                    });
                    checks.forEach(c => c.addEventListener('change', updateUI));
                    updateUI();
                })();
            </script>

            <?php if ($pages > 1): ?>
                <nav class="mt-3" aria-label="Pagination">
                    <ul class="pagination justify-content-center">
                        <?php $prev = max(1, $page - 1);
                        $next = min($pages, $page + 1); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/employers?<?= $qs(['page' => 1]) ?>">First</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/employers?<?= $qs(['page' => $prev]) ?>">Prev</a></li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/employers?<?= $qs(['page' => $i]) ?>"><?= $i ?></a></li>
                        <?php endfor;
                        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/employers?<?= $qs(['page' => $next]) ?>">Next</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/employers?<?= $qs(['page' => $pages]) ?>">Last</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>