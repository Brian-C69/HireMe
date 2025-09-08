<?php
$base     = defined('BASE_URL') ? BASE_URL : '';
$jobs     = $jobs ?? [];
$statuses = $statuses ?? ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];
$badge = function (string $s): string {
    $map = ['Open' => 'success', 'Paused' => 'secondary', 'Suspended' => 'warning', 'Fulfilled' => 'info', 'Closed' => 'dark', 'Deleted' => 'danger'];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};
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
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Posted</th>
                            <th>Status</th>
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
                                    <div class="fw-semibold"><?= $title ?></div>
                                    <div class="text-muted small"><?= $company ?></div>
                                </td>
                                <td><?= htmlspecialchars($posted) ?></td>
                                <td><span class="<?= $badge($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/jobs/<?= $id ?>">View</a>
                                    <form action="<?= $base ?>/jobs/<?= $id ?>/status" method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                        <select name="status" class="form-select form-select-sm d-inline w-auto me-1">
                                            <?php foreach ($statuses as $s): ?>
                                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Update</button>
                                    </form>
                                    <form action="<?= $base ?>/jobs/<?= $id ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Mark as Deleted?');">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
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