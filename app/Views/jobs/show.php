<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$logo   = trim((string)($job['company_logo'] ?? ''));
$salary = $job['salary_range_min'] !== null ? 'RM ' . number_format((float)$job['salary_range_min'], 0) : '—';
$date   = $job['date_posted'] ? date('d/m/Y', strtotime($job['date_posted'])) : '';
$langs  = trim((string)($job['job_languages'] ?? ''));
?>
<section class="py-5">
    <div class="container" style="max-width: 980px;">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="border bg-light d-flex align-items-center justify-content-center rounded" style="width:100%;aspect-ratio:1/1;">
                    <?php if ($logo): ?>
                        <img src="<?= $base . '/' . ltrim($logo, '/') ?>" alt="Logo" style="max-width:90%;max-height:90%;object-fit:contain;">
                    <?php else: ?>
                        <span class="text-muted small">Company<br>Logo</span>
                    <?php endif; ?>
                </div>

                <div class="border rounded px-3 py-2 mt-3 text-center">
                    <strong>Salary</strong><br><span class="text-muted">(<?= htmlspecialchars($salary) ?>)</span>
                </div>

                <a class="btn btn-primary w-100 mt-3" href="<?= $base ?>/applications/create?job=<?= (int)$job['job_posting_id'] ?>">Apply Job</a>
            </div>

            <div class="col-md-9">
                <div class="border rounded px-3 py-2 mb-2"><strong>Job Title</strong> (<?= htmlspecialchars($job['job_title']) ?>)</div>
                <div class="border rounded px-3 py-2 mb-2"><strong>Company</strong> (<?= htmlspecialchars($job['company_name']) ?>)</div>
                <div class="border rounded px-3 py-2 mb-2"><strong>Location</strong> (<?= htmlspecialchars($job['job_location'] ?? '—') ?>)</div>
                <div class="border rounded px-3 py-2 mb-2"><strong>Date</strong> (<?= htmlspecialchars($date) ?>)</div>
                <div class="border rounded px-3 py-2 mb-3"><strong>Languages</strong> (<?= htmlspecialchars($langs ?: '—') ?>)</div>

                <div class="card">
                    <div class="card-header fw-semibold">Job Description</div>
                    <div class="card-body">
                        <div style="white-space: pre-wrap;"><?= htmlspecialchars($job['job_description'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($questions)): ?>
        <div class="card mt-3">
            <div class="card-header fw-semibold">Micro Interview (3 questions)</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($questions as $q): ?>
                    <li class="list-group-item"><?= htmlspecialchars($q['prompt']) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body small text-muted">
                You’ll need to answer these to apply.
                <a class="btn btn-sm btn-primary float-end" href="<?= $base ?>/jobs/<?= (int)$job['job_posting_id'] ?>/apply">Apply Now</a>
            </div>
        </div>
    <?php endif; ?>

</section>