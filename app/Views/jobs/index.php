<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$jobs = $jobs ?? []; // avoid "undefined"
?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Job Posts</h1>
            <?php if (!empty($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'Employer')): ?>
                <a class="btn btn-primary" href="<?= $base ?>/jobs/create">Post a Job</a>
            <?php endif; ?>
        </div>

        <?php if (!$jobs): ?>
            <div class="alert alert-light border">No jobs yet.</div>
        <?php else: ?>
            <div class="vstack gap-3">
                <?php foreach ($jobs as $j): ?>
                    <?php
                    $id     = (int)($j['job_posting_id'] ?? 0);
                    $title  = htmlspecialchars((string)($j['job_title'] ?? ''));
                    $cname  = htmlspecialchars((string)($j['company_name'] ?? ''));
                    $loc    = htmlspecialchars((string)($j['job_location'] ?? '—'));
                    $date   = !empty($j['date_posted']) ? date('d/m/Y', strtotime((string)$j['date_posted'])) : '';
                    $langs  = htmlspecialchars((string)($j['job_languages'] ?? '—'));
                    $logo   = trim((string)($j['company_logo'] ?? ''));               // relative path or ''
                    $salaryMin = $j['salary_range_min'] ?? null;
                    $salaryTxt = ($salaryMin !== null && $salaryMin !== '')
                        ? 'RM ' . number_format((float)$salaryMin, 0)
                        : '—';
                    ?>
                    <a href="<?= $base ?>/jobs/<?= $id ?>" class="text-decoration-none text-reset">
                        <div class="p-3 border rounded bg-white">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <div class="border bg-light d-flex align-items-center justify-content-center"
                                        style="width:80px;height:80px;border-radius:6px;">
                                        <?php if ($logo): ?>
                                            <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo"
                                                style="max-width:100%;max-height:100%;object-fit:contain;">
                                        <?php else: ?>
                                            <span class="text-muted small text-center">Company<br>Logo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md">
                                    <div class="border rounded px-3 py-2 mb-2"><strong><?= $title ?></strong></div>
                                    <div class="border rounded px-3 py-2 mb-2"><?= $cname ?></div>
                                    <div class="border rounded px-3 py-2 mb-2">Location (<?= $loc ?>)</div>
                                    <div class="border rounded px-3 py-2 mb-2">Date (<?= htmlspecialchars($date) ?>)</div>
                                    <div class="border rounded px-3 py-2">Languages (<?= $langs ?>)</div>
                                </div>

                                <div class="col-auto text-center">
                                    <div class="border rounded px-3 py-2 mb-3">
                                        <strong>Salary</strong><br>
                                        <span class="text-muted">(<?= htmlspecialchars($salaryTxt) ?>)</span>
                                    </div>
                                    <a class="btn btn-outline-primary" href="<?= $base ?>/jobs/<?= $id ?>">Apply Job</a>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>