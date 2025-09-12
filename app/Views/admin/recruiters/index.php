<?php
$base  = defined('BASE_URL') ? BASE_URL : '';
$rows  = $rows ?? [];
$csrf  = htmlspecialchars($_SESSION['csrf'] ?? '');
$page  = (int)($_GET['page'] ?? ($page ?? 1));
$pages = (int)($pages ?? 1);
$per   = (int)($_GET['per'] ?? 20);
$q     = (string)($_GET['q'] ?? '');
$qs = function (array $overrides = []) use ($q, $per, $page) {
    $params = array_merge(['q' => $q, 'per' => $per, 'page' => $page], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    if (($params['page'] ?? 1) == 1) unset($params['page']);
    return http_build_query($params);
};
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Recruiters</h1>
            <a class="btn btn-primary" href="<?= $base ?>/admin/recruiters/create">Add Recruiter</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/admin/recruiters">
                    <div class="col-md-5">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, email, agency, phone, location">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <select class="form-select" name="per">
                            <?php foreach ([20, 50, 100] as $n): ?>
                                <option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/recruiters">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No recruiters found.</div>
        <?php else: ?>
            <!-- Bulk actions -->
            <form id="bulk-form" action="<?= $base ?>/admin/recruiters/bulk" method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Bulk actions:</div>
                    <select id="bulk-action" name="bulk_action" class="form-select form-select-sm w-auto" disabled>
                        <option value="">Choose…</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button id="bulk-apply" type="submit" class="btn btn-sm btn-secondary" disabled>Apply</button>
                    <span id="bulk-count" class="text-muted small ms-2">0 selected</span>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle" id="recruiters-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input class="form-check-input" type="checkbox" id="select-all"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Agency</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Client Companies</th>
                            <th>Jobs</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id   = (int)$r['recruiter_id'];
                            $name = htmlspecialchars((string)($r['full_name'] ?? ''));
                            $email = htmlspecialchars((string)($r['email'] ?? ''));
                            $agency = htmlspecialchars((string)($r['agency_name'] ?? ''));
                            $phone = htmlspecialchars((string)($r['contact_number'] ?? ''));
                            $loc   = htmlspecialchars((string)($r['location'] ?? ''));
                            $clients = (int)($r['client_companies'] ?? 0);
                            $jobs    = (int)($r['jobs_count'] ?? 0);
                            $upd  = !empty($r['updated_at']) ? date('d/m/Y', strtotime((string)$r['updated_at'])) : '';
                            ?>
                            <tr>
                                <td><input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $id ?>"></td>
                                <td class="fw-semibold"><?= $name ?></td>
                                <td><?= $email ?></td>
                                <td><?= $agency ?: '—' ?></td>
                                <td><?= $phone ?: '—' ?></td>
                                <td><?= $loc ?: '—' ?></td>
                                <td><?= number_format($clients) ?></td>
                                <td><?= number_format($jobs) ?></td>
                                <td><?= htmlspecialchars($upd) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/admin/recruiters/<?= $id ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/admin/recruiters/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete this recruiter and related data?');">
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
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/recruiters?<?= $qs(['page' => 1]) ?>">First</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/recruiters?<?= $qs(['page' => $prev]) ?>">Prev</a></li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/recruiters?<?= $qs(['page' => $i]) ?>"><?= $i ?></a></li>
                        <?php endfor;
                        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/recruiters?<?= $qs(['page' => $next]) ?>">Next</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/recruiters?<?= $qs(['page' => $pages]) ?>">Last</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>