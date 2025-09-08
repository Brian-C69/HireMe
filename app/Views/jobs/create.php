<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old    = $old ?? [];
$val = fn($k, $d = '') => htmlspecialchars($old[$k] ?? $d);
$cls = fn($k) => isset($errors[$k]) ? 'is-invalid' : '';
?>
<section class="py-5">
    <div class="container" style="max-width: 820px;">
        <h1 class="h4 mb-3">Post a Job</h1>
        <div class="card">
            <div class="card-body">
                <form action="<?= $base ?>/jobs" method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
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

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">Publish Job</button>
                        <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>