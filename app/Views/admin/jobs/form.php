<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$job    = $job ?? null;

$companies = $companies ?? [];
$recruiters = $recruiters ?? [];
$qbank     = $qbank ?? [];
$statuses  = $statuses ?? ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];

$val = function ($k, $d = '') use ($old, $job) {
    if (array_key_exists($k, $old)) return htmlspecialchars((string)$old[$k]);
    if ($job && array_key_exists($k, $job)) return htmlspecialchars((string)$job[$k]);
    return htmlspecialchars((string)$d);
};
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
$csrf = htmlspecialchars($_SESSION['csrf'] ?? '');
$isEdit = (bool)$job;

$attached = $attached ?? [];
$chosen   = array_map('intval', (array)($old['mi_questions'] ?? ($attached ?? [])));

$action = $isEdit ? ($base . '/admin/jobs/' . (int)$job['job_posting_id'] . '/edit') : ($base . '/admin/jobs');
?>
<section class="py-4">
    <div class="container" style="max-width: 860px;">
        <h1 class="h4 mb-3"><?= $isEdit ? 'Edit Job' : 'Create Job' ?></h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $action ?>" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <select class="form-select <?= $cls('company_id') ?>" name="company_id" required>
                                <option value="">Select company…</option>
                                <?php $cid = $val('company_id');
                                foreach ($companies as $c): $id = (int)$c['employer_id']; ?>
                                    <option value="<?= $id ?>" <?= (string)$cid === (string)$id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['company_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['company_id']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Recruiter (optional)</label>
                            <select class="form-select" name="recruiter_id">
                                <option value="">— None —</option>
                                <?php $rid = $val('recruiter_id');
                                foreach ($recruiters as $r): $id = (int)$r['recruiter_id']; ?>
                                    <option value="<?= $id ?>" <?= (string)$rid === (string)$id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Job Title</label>
                            <input class="form-control <?= $cls('job_title') ?>" name="job_title" value="<?= $val('job_title') ?>" required>
                            <?php if (isset($errors['job_title'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_title']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Employment Type</label>
                            <?php $tp = $val('employment_type', 'Full-time'); ?>
                            <select class="form-select" name="employment_type">
                                <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $o): ?>
                                    <option value="<?= $o ?>" <?= $tp === $o ? 'selected' : '' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="job_location" value="<?= $val('job_location') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Salary (RM)</label>
                            <input class="form-control <?= $cls('salary') ?>" name="salary" value="<?= $val('salary') ?>" placeholder="3500">
                            <?php if (isset($errors['salary'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['salary']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <?php $st = $val('status', 'Open'); ?>
                            <select class="form-select <?= $cls('status') ?>" name="status">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['status'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['status']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Languages (comma separated)</label>
                            <input class="form-control" name="job_languages" value="<?= $val('job_languages') ?>" placeholder="Malay, English, Mandarin">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control <?= $cls('job_description') ?>" name="job_description" rows="6" placeholder="Describe the role, responsibilities, requirements..."><?= $val('job_description') ?></textarea>
                            <?php if (isset($errors['job_description'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_description']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <!-- Micro Interview -->
                    <div class="card mt-3">
                        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                            <span>Micro Interview — pick exactly 3</span>
                            <span class="small text-muted">Choose 3</span>
                        </div>
                        <div class="card-body">
                            <?php if (isset($errors['mi_questions'])): ?>
                                <div class="alert alert-danger py-2 small mb-3"><?= htmlspecialchars($errors['mi_questions']) ?></div>
                            <?php endif; ?>
                            <div class="row g-2">
                                <?php foreach ($qbank as $q): $id = (int)$q['id'];
                                    $checked = in_array($id, $chosen, true) ? 'checked' : ''; ?>
                                    <div class="col-md-6">
                                        <label class="border rounded p-2 w-100">
                                            <input type="checkbox" class="form-check-input me-2 mi-check" name="mi_questions[]" value="<?= $id ?>" <?= $checked ?>>
                                            <span><?= htmlspecialchars($q['prompt']) ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="small text-muted mt-2">Candidates must answer these during application.</div>
                        </div>
                    </div>

                    <script>
                        // exactly 3 selections
                        document.addEventListener('change', function(e) {
                            if (!e.target.matches('.mi-check')) return;
                            const checks = Array.from(document.querySelectorAll('.mi-check'));
                            const picked = checks.filter(c => c.checked);
                            if (picked.length > 3) e.target.checked = false;
                        });
                    </script>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Job' ?></button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/jobs">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>