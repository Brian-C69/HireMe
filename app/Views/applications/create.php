<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$old = $old ?? [];
?>
<section class="py-5">
    <div class="container" style="max-width: 860px;">
        <h1 class="h4 mb-3">Apply — <?= htmlspecialchars($job['job_title'] ?? '') ?></h1>
        <div class="card mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="border bg-light d-flex align-items-center justify-content-center rounded" style="width:64px;height:64px;">
                    <?php if (!empty($job['company_logo'])): ?>
                        <img src="<?= $base . '/' . ltrim($job['company_logo'], '/') ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
                    <?php else: ?>
                        <span class="text-muted small">Logo</span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="fw-semibold"><?= htmlspecialchars($job['company_name'] ?? '') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($job['job_location'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <form action="<?= $base ?>/applications" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="job_id" value="<?= (int)$job['job_posting_id'] ?>">

            <div class="card">
                <div class="card-header fw-semibold">Micro Interview — answer all</div>
                <div class="card-body">
                    <?php foreach (($questions ?? []) as $q): ?>
                        <?php $qid = (int)$q['id'];
                        $name = 'answer_' . $qid;
                        $err = $errors[$name] ?? null; ?>
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars($q['prompt']) ?></label>
                            <textarea class="form-control <?= $err ? 'is-invalid' : '' ?>" name="<?= $name ?>" rows="3"
                                placeholder="Your answer (max 1000 characters)"><?= htmlspecialchars($old[$name] ?? '') ?></textarea>
                            <?php if ($err): ?><div class="invalid-feedback"><?= htmlspecialchars($err) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit">Submit Application</button>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs/<?= (int)$job['job_posting_id'] ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>