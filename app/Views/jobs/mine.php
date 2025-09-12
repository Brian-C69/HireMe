<?php
$base     = defined('BASE_URL') ? BASE_URL : '';
$jobs     = $jobs ?? [];
$statuses = $statuses ?? ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];
$badge = function (string $s): string {
    $map = ['Open' => 'success', 'Paused' => 'secondary', 'Suspended' => 'warning', 'Fulfilled' => 'info', 'Closed' => 'dark', 'Deleted' => 'danger'];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">My Jobs</h1>
            <a class="btn btn-primary" href="<?= $base ?>/jobs/create">Post Job</a>
        </div>

        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <?php $sel = $_GET['status'] ?? '';
                    foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $sel === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-8 col-md-9">
                <button class="btn btn-outline-secondary" type="submit">Filter</button>
                <a class="btn btn-link" href="<?= $base ?>/jobs/mine">Reset</a>
            </div>
        </form>

        <?php if (!$jobs): ?>
            <div class="alert alert-light border">No jobs yet.</div>
        <?php else: ?>
            <!-- Bulk actions -->
            <form id="bulk-form" action="<?= $base ?>/jobs/bulk" method="post" class="mb-2">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="fw-semibold">Bulk actions:</div>
                    <select id="bulk-action" name="bulk_action" class="form-select form-select-sm w-auto" disabled>
                        <option value="">Choose…</option>
                        <option value="set_status">Set status…</option>
                        <option value="delete">Mark as Deleted</option>
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
                            <th style="width: 36px;">
                                <input class="form-check-input" type="checkbox" id="select-all">
                            </th>
                            <th>Job</th>
                            <th>Posted</th>
                            <th>Status</th>
                            <th>Applicants</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j): ?>
                            <?php
                            $id = (int)$j['job_posting_id'];
                            $title = htmlspecialchars($j['job_title'] ?? '');
                            $company = htmlspecialchars($j['company_name'] ?? '');
                            $posted = $j['date_posted'] ? date('d/m/Y', strtotime((string)$j['date_posted'])) : '';
                            $status = $j['status'] ?? 'Open';
                            ?>
                            <tr>
                                <td>
                                    <input class="form-check-input row-check" type="checkbox" name="ids[]" form="bulk-form" value="<?= $id ?>">
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= $title ?></div>
                                    <div class="text-muted small"><?= $company ?></div>
                                </td>
                                <td><?= htmlspecialchars($posted) ?></td>
                                <td><span class="<?= $badge($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td><span class="badge text-bg-dark"><?= (int)($j['applicants_count'] ?? 0) ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-dark" href="<?= $base ?>/jobs/<?= $id ?>/applicants">Applicants</a>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/jobs/<?= $id ?>">View</a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= $base ?>/jobs/<?= $id ?>/edit">Edit</a>
                                    <form action="<?= $base ?>/jobs/<?= $id ?>/status" method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                        <select name="status" class="form-select form-select-sm d-inline w-auto me-1">
                                            <?php foreach ($statuses as $s): ?>
                                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Update</button>
                                    </form>
                                    <form action="<?= $base ?>/jobs/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Mark as Deleted?');">
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
                        const selected = checks.filter(c => c.checked);
                        const hasSel = selected.length > 0;
                        bulkAction.disabled = !hasSel;
                        bulkApply.disabled = !hasSel;
                        bulkCount.textContent = hasSel ? (selected.length + ' selected') : '0 selected';
                        // keep header checkbox in sync
                        selectAll.checked = hasSel && selected.length === checks.length;
                        selectAll.indeterminate = hasSel && selected.length !== checks.length;
                        // show/hide status dropdown
                        if (bulkAction.value === 'set_status' && hasSel) {
                            bulkStatus.classList.remove('d-none');
                        } else {
                            bulkStatus.classList.add('d-none');
                        }
                    }

                    selectAll?.addEventListener('change', (e) => {
                        checks.forEach(c => c.checked = e.target.checked);
                        updateUI();
                    });

                    checks.forEach(c => c.addEventListener('change', updateUI));
                    bulkAction.addEventListener('change', updateUI);

                    updateUI();
                })();
            </script>
        <?php endif; ?>
    </div>
</section>