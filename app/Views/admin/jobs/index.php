<?php
$base      = defined('BASE_URL') ? BASE_URL : '';
$rows      = $rows ?? [];
$companies = $companies ?? [];
$recruiters = $recruiters ?? [];
$statuses  = $statuses ?? ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];

$csrf  = htmlspecialchars($_SESSION['csrf'] ?? '');
$q     = (string)($_GET['q'] ?? '');
$stSel = (string)($_GET['status'] ?? '');
$tpSel = (string)($_GET['type'] ?? '');
$cSel  = (string)($_GET['company_id'] ?? '');
$rSel  = (string)($_GET['recruiter_id'] ?? '');
$per   = (int)($_GET['per'] ?? 20);
$page  = (int)($_GET['page'] ?? ($page ?? 1));
$pages = (int)($pages ?? 1);

$qs = function (array $overrides = []) use ($q, $stSel, $tpSel, $cSel, $rSel, $per, $page) {
    $params = array_merge(compact('q', 'per'), [
        'status' => $stSel,
        'type' => $tpSel,
        'company_id' => $cSel,
        'recruiter_id' => $rSel,
        'page' => $page
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    if (($params['page'] ?? 1) == 1) unset($params['page']);
    return http_build_query($params);
};
$badge = function (string $s): string {
    $map = ['Open' => 'success', 'Paused' => 'secondary', 'Suspended' => 'warning', 'Fulfilled' => 'info', 'Closed' => 'dark', 'Deleted' => 'danger'];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Jobs</h1>
            <a class="btn btn-primary" href="<?= $base ?>/admin/jobs/create">Create Job</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/admin/jobs">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Title, description, company">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">Any</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $stSel === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <?php foreach (['' => 'Any', 'Full-time' => 'Full-time', 'Part-time' => 'Part-time', 'Contract' => 'Contract', 'Internship' => 'Internship'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $tpSel === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company</label>
                        <select class="form-select" name="company_id">
                            <option value="">Any</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= (int)$c['employer_id'] ?>" <?= $cSel == (string)$c['employer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Recruiter</label>
                        <select class="form-select" name="recruiter_id">
                            <option value="">Any</option>
                            <?php foreach ($recruiters as $r): ?>
                                <option value="<?= (int)$r['recruiter_id'] ?>" <?= $rSel == (string)$r['recruiter_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
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
                    <div class="col-md-10 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/jobs">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$rows): ?>
            <div class="alert alert-light border">No jobs found.</div>
        <?php else: ?>
            <!-- Bulk -->
            <form id="bulk-form" action="<?= $base ?>/admin/jobs/bulk" method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Bulk actions:</div>
                    <select id="bulk-action" name="bulk_action" class="form-select form-select-sm w-auto" disabled>
                        <option value="">Choose…</option>
                        <option value="set_status">Set Status…</option>
                        <option value="delete">Delete</option>
                    </select>
                    <select id="bulk-status" name="new_status" class="form-select form-select-sm w-auto d-none">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="bulk-apply" type="submit" class="btn btn-sm btn-secondary" disabled>Apply</button>
                    <span id="bulk-count" class="text-muted small ms-2">0 selected</span>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle" id="jobs-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input class="form-check-input" type="checkbox" id="select-all"></th>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Recruiter</th>
                            <th>Posted</th>
                            <th>Applicants</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $j): ?>
                            <?php
                            $id     = (int)$j['job_posting_id'];
                            $title  = htmlspecialchars((string)$j['job_title']);
                            $cname  = htmlspecialchars((string)$j['company_name']);
                            $rname  = htmlspecialchars((string)($j['recruiter_name'] ?? '—'));
                            $posted = !empty($j['date_posted']) ? date('d/m/Y', strtotime((string)$j['date_posted'])) : '';
                            $status = (string)($j['status'] ?? 'Open');
                            $apps   = (int)($j['applicants_count'] ?? 0);
                            ?>
                            <tr>
                                <td><input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $id ?>"></td>
                                <td class="fw-semibold"><?= $title ?></td>
                                <td><?= $cname ?></td>
                                <td><?= $rname ?: '—' ?></td>
                                <td><?= htmlspecialchars($posted) ?></td>
                                <td><?= number_format($apps) ?></td>
                                <td><span class="<?= $badge($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/admin/jobs/<?= $id ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/admin/jobs/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete this job and related data?');">
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
                    const bulkStatus = document.getElementById('bulk-status');
                    const bulkApply = document.getElementById('bulk-apply');
                    const bulkCount = document.getElementById('bulk-count');

                    function updateUI() {
                        const sel = checks.filter(c => c.checked);
                        const has = sel.length > 0;
                        bulkAction.disabled = !has;
                        bulkApply.disabled = !has;
                        bulkCount.textContent = has ? (sel.length + ' selected') : '0 selected';
                        selectAll.checked = has && sel.length === checks.length;
                        selectAll.indeterminate = has && sel.length !== checks.length;
                        if (bulkAction.value === 'set_status' && has) bulkStatus.classList.remove('d-none');
                        else bulkStatus.classList.add('d-none');
                    }
                    selectAll?.addEventListener('change', e => {
                        checks.forEach(c => c.checked = e.target.checked);
                        updateUI();
                    });
                    checks.forEach(c => c.addEventListener('change', updateUI));
                    bulkAction.addEventListener('change', updateUI);
                    updateUI();
                })();
            </script>

            <?php if ($pages > 1): ?>
                <nav class="mt-3" aria-label="Pagination">
                    <ul class="pagination justify-content-center">
                        <?php $prev = max(1, $page - 1);
                        $next = min($pages, $page + 1); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/jobs?<?= $qs(['page' => 1]) ?>">First</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/jobs?<?= $qs(['page' => $prev]) ?>">Prev</a></li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($pages, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/jobs?<?= $qs(['page' => $i]) ?>"><?= $i ?></a></li>
                        <?php endfor;
                        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/jobs?<?= $qs(['page' => $next]) ?>">Next</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $base ?>/admin/jobs?<?= $qs(['page' => $pages]) ?>">Last</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>