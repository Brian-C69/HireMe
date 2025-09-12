<?php
$base    = defined('BASE_URL') ? BASE_URL : '';
$jobs    = $jobs ?? [];
$filters = $filters ?? [];
$page    = (int)($filters['page'] ?? 1);
$per     = (int)($filters['per']  ?? 12);
$pages   = (int)($pages ?? 1);
$total   = $total ?? null;

$appliedIds = $appliedIds ?? []; // array<int> of job_posting_id

$qs = function (array $overrides = []) use ($filters) {
    $q = array_filter(array_merge($filters, $overrides), fn($v) => $v !== '' && $v !== null);
    if (($q['page'] ?? 1) == 1) unset($q['page']);
    return http_build_query($q);
};

$from = $total ? (($page - 1) * $per + 1) : 0;
$to   = $total ? min($from + $per - 1, $total) : 0;
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Job Posts</h1>
            <div class="text-muted small">
                <?php if ($total): ?>Showing <?= number_format($from) ?>–<?= number_format($to) ?> of <?= number_format($total) ?><?php endif; ?>
            </div>
        </div>

        <!-- Filters / Search -->
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="<?= $base ?>/jobs">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input class="form-control" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Title, description, company">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Location</label>
                        <input class="form-control" name="location" value="<?= htmlspecialchars($filters['location'] ?? '') ?>" placeholder="e.g., KL">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Type</label>
                        <?php $t = $filters['type'] ?? ''; ?>
                        <select class="form-select" name="type">
                            <option value="">Any</option>
                            <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $t === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Company</label>
                        <input class="form-control" name="company" value="<?= htmlspecialchars($filters['company'] ?? '') ?>" placeholder="Company">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Min RM</label>
                        <input class="form-control" name="min_salary" type="number" min="0" step="100" value="<?= htmlspecialchars($filters['min_salary'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Languages</label>
                        <input class="form-control" name="languages" value="<?= htmlspecialchars($filters['languages'] ?? '') ?>" placeholder="e.g., Malay">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <?php $perSel = (int)($filters['per'] ?? 12); ?>
                        <select class="form-select" name="per">
                            <?php foreach ([12, 20, 30, 50] as $n): ?>
                                <option value="<?= $n ?>" <?= $perSel === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-10 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Search</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$jobs): ?>
            <div class="alert alert-light border">No jobs match your filters.</div>
        <?php else: ?>
            <div class="row g-3 mb-3">
                <?php foreach ($jobs as $j): ?>
                    <?php
                    $id     = (int)($j['job_posting_id'] ?? 0);
                    $title  = htmlspecialchars((string)($j['job_title'] ?? ''));
                    $cname  = htmlspecialchars((string)($j['company_name'] ?? ''));
                    $loc    = htmlspecialchars((string)($j['job_location'] ?? '—'));
                    $date   = !empty($j['date_posted']) ? date('d/m/Y', strtotime((string)$j['date_posted'])) : '';
                    $langs  = htmlspecialchars((string)($j['job_languages'] ?? '—'));
                    $logo   = trim((string)($j['company_logo'] ?? ''));
                    $salaryMin = $j['salary_range_min'] ?? null;
                    $salaryTxt = ($salaryMin !== null && $salaryMin !== '') ? 'RM ' . number_format((float)$salaryMin, 0) : '—';
                    $etype  = htmlspecialchars((string)($j['employment_type'] ?? ''));
                    $isApplied = in_array($id, $appliedIds, true);
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="card h-100 shadow-sm position-relative">
                            <?php if ($isApplied): ?>
                                <span class="position-absolute top-0 end-0 m-2 badge text-bg-success">Applied</span>
                            <?php endif; ?>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="border bg-light d-flex align-items-center justify-content-center rounded flex-shrink-0" style="width:48px;height:48px;">
                                        <?php if ($logo): ?>
                                            <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                                        <?php else: ?>
                                            <span class="text-muted small">Logo</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-2">
                                        <div class="fw-semibold text-truncate" title="<?= $title ?>"><?= $title ?></div>
                                        <div class="text-muted small text-truncate" title="<?= $cname ?>"><?= $cname ?></div>
                                    </div>
                                </div>
                                <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= $loc ?></div>
                                <div class="small text-muted"><i class="bi bi-calendar me-1"></i><?= htmlspecialchars($date) ?></div>
                                <?php if ($langs !== '—'): ?>
                                    <div class="small text-muted"><i class="bi bi-translate me-1"></i><?= $langs ?></div>
                                <?php endif; ?>
                                <a class="stretched-link" href="<?= $base ?>/jobs/<?= $id ?>" aria-label="View job"></a>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="badge text-bg-light border"><?= htmlspecialchars($salaryTxt) ?></span>
                                <span class="text-muted small"><?= $etype ?></span>
                                <?php if ($isApplied): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/jobs/<?= $id ?>">View</a>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-primary" href="<?= $base ?>/jobs/<?= $id ?>">Apply</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav aria-label="Job pagination">
                <ul class="pagination justify-content-center">
                    <?php $prev = max(1, $page - 1);
                    $next = min($pages, $page + 1); ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base ?>/jobs?<?= $qs(['page' => 1]) ?>">First</a>
                    </li>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base ?>/jobs?<?= $qs(['page' => $prev]) ?>">Prev</a>
                    </li>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($pages, $page + 2);
                    if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $base ?>/jobs?<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;
                    if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base ?>/jobs?<?= $qs(['page' => $next]) ?>">Next</a>
                    </li>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base ?>/jobs?<?= $qs(['page' => $pages]) ?>">Last</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>