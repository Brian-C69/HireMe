<?php
// =====================================
// File: app/Views/jobs/edit.php (new)
// =====================================
$base    = defined('BASE_URL') ? BASE_URL : '';
$errors  = $errors ?? [];
$old     = $old ?? [];
$job     = $job ?? [];           // from controller (ownJob)
$qbank   = $qbank ?? [];         // micro question bank
$attached = $attached ?? [];      // selected question ids
// Prefer old values when validation failed
$val = fn(string $k, $fallback = '') => htmlspecialchars((string)($old[$k] ?? $job[$k] ?? $fallback));
$sel = function (int $id) use ($old, $attached) {
    $fromOld = array_map('intval', (array)($old['mi_questions'] ?? []));
    $source = $old ? $fromOld : $attached;
    return in_array($id, $source, true) ? 'checked' : '';
};
?>
<section class="py-4">
    <div class="container" style="max-width:960px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Edit Job</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs/mine">My Jobs</a>
                <a class="btn btn-primary" href="<?= $base ?>/jobs/<?= (int)$job['job_posting_id'] ?>">View</a>
            </div>
        </div>

        <form action="<?= $base ?>/jobs/<?= (int)$job['job_posting_id'] ?>/edit" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Job Title</label>
                            <input class="form-control <?= isset($errors['job_title']) ? 'is-invalid' : '' ?>" name="job_title" value="<?= $val('job_title') ?>">
                            <?php if (isset($errors['job_title'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_title']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Employment Type</label>
                            <?php $etype = $old['employment_type'] ?? $job['employment_type'] ?? 'Full-time'; ?>
                            <select class="form-select" name="employment_type">
                                <?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $etype === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="job_location" value="<?= $val('job_location') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Languages</label>
                            <input class="form-control" name="job_languages" value="<?= $val('job_languages') ?>" placeholder="e.g., Malay, English">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Salary (RM)</label>
                            <input class="form-control <?= isset($errors['salary']) ? 'is-invalid' : '' ?>" name="salary" type="number" min="0" step="100" value="<?= htmlspecialchars($old['salary'] ?? ($job['salary_range_min'] ?? '')) ?>">
                            <?php if (isset($errors['salary'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['salary']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control <?= isset($errors['job_description']) ? 'is-invalid' : '' ?>" rows="6" name="job_description"><?= $val('job_description') ?></textarea>
                            <?php if (isset($errors['job_description'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['job_description']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Micro Interview -->
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Micro Interview — pick exactly 3</span>
                    <span class="small text-muted">Choose 3</span>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['mi_questions'])): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= htmlspecialchars($errors['mi_questions']) ?></div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <?php foreach ($qbank as $q): $qid = (int)$q['id']; ?>
                            <div class="col-md-6">
                                <label class="border rounded p-2 w-100">
                                    <input type="checkbox" class="form-check-input me-2 mi-check" name="mi_questions[]" value="<?= $qid ?>" <?= $sel($qid) ?>>
                                    <span><?= htmlspecialchars($q['prompt']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs/<?= (int)$job['job_posting_id'] ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>

<script>
    // Limit to exactly 3 selections
    document.addEventListener('change', function(e) {
        if (!e.target.matches('.mi-check')) return;
        const checks = Array.from(document.querySelectorAll('.mi-check'));
        const picked = checks.filter(c => c.checked);
        if (picked.length > 3) e.target.checked = false;
    });
</script>