<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$me     = $me ?? [];
?>
<section class="py-4">
    <div class="container" style="max-width: 760px;">
        <h1 class="h4 mb-3">Verify Your Account</h1>

        <div class="alert alert-light border small">
            Upload a clear image or PDF of your IC, Passport, or Driver’s License. We’ll review and mark your account as verified.
        </div>

        <div class="mb-3">
            <span class="badge <?= !empty($me['verified_status']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= !empty($me['verified_status']) ? 'Verified' : 'Not Verified' ?>
            </span>
            <?php if (!empty($me['verification_date'])): ?>
                <span class="text-muted small ms-2">Last submitted: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$me['verification_date']))) ?></span>
            <?php endif; ?>
        </div>

        <form action="<?= $base ?>/verify" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <div class="mb-3">
                <label class="form-label">Document Type</label>
                <select class="form-select <?= isset($errors['verification_doc_type']) ? 'is-invalid' : '' ?>" name="verification_doc_type" required>
                    <?php $opts = ['IC', 'Passport', 'Driver\'s License']; ?>
                    <?php foreach ($opts as $o): ?>
                        <option value="<?= $o ?>" <?= (isset($me['verification_doc_type']) && $me['verification_doc_type'] === $o) ? 'selected' : '' ?>><?= $o ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['verification_doc_type'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['verification_doc_type']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Upload Document (JPG/PNG/PDF, max 5MB)</label>
                <input class="form-control <?= isset($errors['verification_doc']) ? 'is-invalid' : '' ?>" type="file" name="verification_doc" accept=".jpg,.jpeg,.png,.pdf" required>
                <?php if (!empty($errors['verification_doc'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['verification_doc']) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Submit for Review</button>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">Back</a>
            </div>
        </form>
    </div>
</section>