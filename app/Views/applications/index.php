<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$apps   = $applications ?? [];
$f      = $filters ?? [];
$page   = (int)($f['page'] ?? 1);
$per    = (int)($f['per'] ?? 10);
$total  = (int)($f['total'] ?? 0);
$pages  = max(1, (int)ceil($total / max(1, $per)));

$qs = function (array $o = []) use ($f) {
    $q = array_filter(array_merge($f, $o), fn($v) => $v !== '' && $v !== null);
    if (($q['page'] ?? 1) == 1) unset($q['page']);
    return http_build_query($q);
};

$badge = function (string $s): string {
    $map = [
        'Applied'   => 'primary',
        'Reviewed'  => 'secondary',
        'Interview' => 'info',
        'Hired'     => 'success',
        'Rejected'  => 'danger',
        'Withdrawn' => 'warning',
    ];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};

$withdrawable = fn(string $s): bool => in_array($s, ['Applied', 'Reviewed'], true);
$reapplicable = fn(string $s): bool => $s === 'Withdrawn';
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">My Applications</h1>
            <?php if ($total): ?>
                <div class="text-muted small">
                    Showing <?= number_format(min(($page - 1) * $per + 1, $total)) ?>–<?= number_format(min($page * $per, $total)) ?>
                    of <?= number_format($total) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/applications">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <?php $sel = $f['status'] ?? '';
                        $opts = ['', 'Applied', 'Reviewed', 'Interview', 'Hired', 'Rejected', 'Withdrawn']; ?>
                        <select name="status" class="form-select">
                            <?php foreach ($opts as $o): ?>
                                <option value="<?= $o ?>" <?= $sel === $o ? 'selected' : '' ?>><?= $o ?: 'Any' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($f['q'] ?? '') ?>" placeholder="Job title or company">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <?php $perSel = (int)($f['per'] ?? 10); ?>
                        <select class="form-select" name="per">
                            <?php foreach ([10, 20, 30, 50] as $n): ?>
                                <option value="<?= $n ?>" <?= $perSel === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/applications">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$apps): ?>
            <div class="alert alert-light border">No applications found.</div>
        <?php else: ?>
            <div class="vstack gap-3">
                <?php foreach ($apps as $a):
                    $appId = (int)$a['applicant_id'];
                    $jid   = (int)$a['job_posting_id'];
                    $title = htmlspecialchars((string)$a['job_title']);
                    $comp  = htmlspecialchars((string)$a['company_name']);
                    $loc   = htmlspecialchars((string)($a['job_location'] ?? '—'));
                    $etype = htmlspecialchars((string)($a['employment_type'] ?? ''));
                    $logo  = trim((string)($a['company_logo'] ?? ''));
                    $stat  = (string)($a['application_status'] ?? 'Applied');
                    $date  = $a['application_date'] ? date('d/m/Y', strtotime((string)$a['application_date'])) : '';
                ?>
                    <div class="card">
                        <div class="card-body d-flex">
                            <div class="border bg-light d-flex align-items-center justify-content-center rounded me-3"
                                style="width:64px;height:64px;">
                                <?php if ($logo): ?>
                                    <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo"
                                        style="max-width:100%;max-height:100%;object-fit:contain;">
                                <?php else: ?>
                                    <span class="text-muted small">Logo</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="me-3">
                                        <div class="fw-semibold"><?= $title ?></div>
                                        <div class="text-muted small"><?= $comp ?> · <?= $loc ?> · <?= $etype ?></div>
                                    </div>
                                    <div>
                                        <span class="<?= $badge($stat) ?>"><?= htmlspecialchars($stat) ?></span>
                                    </div>
                                </div>
                                <div class="text-muted small mt-1">Applied on <?= htmlspecialchars($date) ?></div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/jobs/<?= $jid ?>">View Job</a>

                            <?php if ($withdrawable($stat)): ?>
                                <form action="<?= $base ?>/applications/<?= $appId ?>/withdraw"
                                    method="post"
                                    onsubmit="return confirm('Withdraw this application? This cannot be undone.');"
                                    class="d-inline">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Withdraw</button>
                                </form>
                            <?php elseif ($reapplicable($stat)): ?>
                                <!-- Jump to job page and open apply modal -->
                                <a class="btn btn-sm btn-primary" href="<?= $base ?>/jobs/<?= $jid ?>?open=apply">Re-Apply</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="mt-3" aria-label="Applications pagination">
                    <ul class="pagination justify-content-center">
                        <?php $prev = max(1, $page - 1);
                        $next = min($pages, $page + 1); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/applications?<?= $qs(['page' => 1]) ?>">First</a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/applications?<?= $qs(['page' => $prev]) ?>">Prev</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $base ?>/applications?<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; ?>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/applications?<?= $qs(['page' => $next]) ?>">Next</a>
                        </li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/applications?<?= $qs(['page' => $pages]) ?>">Last</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>