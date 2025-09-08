<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$logo   = trim((string)($job['company_logo'] ?? ''));
$salary = $job['salary_range_min'] !== null ? 'RM ' . number_format((float)$job['salary_range_min'], 0) : '—';
$date   = $job['date_posted'] ? date('d/m/Y', strtotime($job['date_posted'])) : '';
$langs  = trim((string)($job['job_languages'] ?? ''));

$errors = $errors ?? [];
$old    = $old ?? [];
$openApplyModal = $openApplyModal ?? false;
$role = $_SESSION['user']['role'] ?? null;
$myApp = $myApp ?? null;

$badgeClass = function (string $s): string {
    $map = ['Applied' => 'primary', 'Reviewed' => 'secondary', 'Interview' => 'info', 'Hired' => 'success', 'Rejected' => 'danger'];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};
?>
<section class="py-5">
    <div class="container" style="max-width:980px;">
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

                <?php if (($role === 'Candidate') && $myApp): ?>
                    <div class="border rounded px-3 py-2 mt-3 text-center">
                        <div class="<?= $badgeClass($myApp['application_status'] ?? 'Applied') ?>">
                            <?= htmlspecialchars($myApp['application_status'] ?? 'Applied') ?>
                        </div>
                        <div class="small text-muted mt-1">
                            Applied on <?= htmlspecialchars(date('d/m/Y', strtotime((string)($myApp['application_date'] ?? 'now')))) ?>
                        </div>
                    </div>

                    <?php $withdrawable = in_array((string)($myApp['application_status'] ?? ''), ['Applied', 'Reviewed'], true); ?>
                    <?php if ($withdrawable): ?>
                        <form action="<?= $base ?>/applications/<?= (int)$myApp['applicant_id'] ?>/withdraw" method="post" class="mt-3" onsubmit="return confirm('Withdraw this application?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                            <button class="btn btn-outline-danger w-100" type="submit">Withdraw Application</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 mt-3" type="button" disabled>Applied</button>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- original Apply button or modal trigger -->
                    <button class="btn btn-primary w-100 mt-3" type="button" data-bs-toggle="modal" data-bs-target="#applyModal">Apply Job</button>
                <?php endif; ?>
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

    <!-- Apply Modal -->
    <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply — <?= htmlspecialchars($job['job_title'] ?? '') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <?php if ($role === 'Candidate' && !$myApp): ?>
                    <form action="<?= $base ?>/applications" method="post" novalidate>
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                        <input type="hidden" name="job_id" value="<?= (int)$job['job_posting_id'] ?>">
                        <div class="modal-body">
                            <?php if (empty($questions)): ?>
                                <div class="alert alert-light border">No questions attached to this job.</div>
                            <?php else: ?>
                                <div class="mb-3 text-muted small">Please answer all questions (max 1000 characters each).</div>
                                <?php foreach ($questions as $q): ?>
                                    <?php $qid = (int)$q['id'];
                                    $name = 'answer_' . $qid;
                                    $err = $errors[$name] ?? null; ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= htmlspecialchars((string)$q['prompt']) ?></label>
                                        <textarea class="form-control <?= $err ? 'is-invalid' : '' ?>" name="<?= $name ?>" rows="3"><?= htmlspecialchars($old[$name] ?? '') ?></textarea>
                                        <?php if ($err): ?><div class="invalid-feedback"><?= htmlspecialchars($err) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit Application</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="modal-body">
                        <?php if ($role !== 'Candidate'): ?>
                            <div class="alert alert-info d-flex align-items-center"><i class="bi bi-info-circle me-2"></i>Please log in as a <strong class="ms-1">Candidate</strong> to apply.</div>
                        <?php else: ?>
                            <div class="alert alert-success">You have already applied to this job.</div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <?php if ($role !== 'Candidate'): ?>
                            <a class="btn btn-primary" href="<?= $base ?>/login">Login</a>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/register">Register</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
    (function() {
        // Auto-open modal when server-side validation failed
        <?php if (!empty($errors) || !empty($openApplyModal)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var el = document.getElementById('applyModal');
                if (el && window.bootstrap && bootstrap.Modal) new bootstrap.Modal(el).show();
            });
        <?php endif; ?>
    })();
</script>