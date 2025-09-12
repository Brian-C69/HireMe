<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$val = fn($k, $d = '') => htmlspecialchars($old[$k] ?? $d);
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
$qbank  = $qbank ?? []; // from controller
$chosen = array_map('intval', (array)($old['mi_questions'] ?? []));
$companies = $companies ?? [];
?>
<section class="py-5">
    <div class="container" style="max-width: 820px;">
        <h1 class="h4 mb-3">Post a Job</h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $base ?>/jobs" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                    <?php if ($role === 'Recruiter'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Company (client)</label>

                            <?php if (!$companies): ?>
                                <div class="alert alert-warning small d-flex align-items-center justify-content-between">
                                    <span>You haven’t added any client companies yet.</span>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/companies/create">Add Company</a>
                                </div>
                            <?php endif; ?>

                            <select class="form-select <?= $cls('company_id') ?>" name="company_id" <?= $companies ? '' : 'disabled' ?>>
                                <option value="">— Select a company —</option>
                                <?php
                                $selectedCompany = (string)($old['company_id'] ?? '');
                                foreach ($companies as $c):
                                    $cid   = (int)$c['employer_id'];
                                    $cname = htmlspecialchars((string)$c['company_name'] ?? '');
                                    $sel   = ($selectedCompany !== '' && (int)$selectedCompany === $cid) ? 'selected' : '';
                                ?>
                                    <option value="<?= $cid ?>" <?= $sel ?>><?= $cname ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['company_id'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['company_id']) ?></div>
                            <?php endif; ?>

                            <div class="small mt-2">
                                <a href="<?= $base ?>/companies" class="link-secondary">Manage companies</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Job Title</label>
                            <input class="form-control <?= $cls('job_title') ?>" name="job_title" value="<?= $val('job_title') ?>" placeholder="Backend Developer" required>
                            <?php if (isset($errors['job_title'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_title']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Type</label>
                            <select class="form-select" name="employment_type">
                                <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $o): ?>
                                    <option value="<?= $o ?>" <?= ($old['employment_type'] ?? '') === $o ? 'selected' : '' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="job_location" value="<?= $val('job_location') ?>" placeholder="Penang">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary (RM)</label>
                            <input class="form-control <?= $cls('salary') ?>" name="salary" value="<?= $val('salary') ?>" placeholder="3500">
                            <?php if (isset($errors['salary'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['salary']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Languages (comma separated)</label>
                            <input class="form-control" name="job_languages" value="<?= $val('job_languages') ?>" placeholder="Malay, English, Mandarin">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control <?= $cls('job_description') ?>" name="job_description" rows="6" placeholder="Describe the role, responsibilities, and requirements..."><?= $val('job_description') ?></textarea>
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
                                <?php foreach ($qbank as $q): ?>
                                    <?php $id = (int)$q['id'];
                                    $checked = in_array($id, $chosen, true) ? 'checked' : ''; ?>
                                    <div class="col-md-6">
                                        <label class="border rounded p-2 w-100">
                                            <input type="checkbox" class="form-check-input me-2 mi-check" name="mi_questions[]" value="<?= $id ?>" <?= $checked ?>>
                                            <span><?= htmlspecialchars($q['prompt']) ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="small text-muted mt-2">Tip: the candidate must answer these 3 during application.</div>
                        </div>
                    </div>

                    <script>
                        // limit to exactly 3 selections
                        document.addEventListener('change', function(e) {
                            if (!e.target.matches('.mi-check')) return;
                            const checks = Array.from(document.querySelectorAll('.mi-check'));
                            const picked = checks.filter(c => c.checked);
                            if (picked.length > 3) {
                                e.target.checked = false;
                            }
                        });
                    </script>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">Publish Job</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>