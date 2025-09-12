<?php
$base   = defined('BASE_URL') ? BASE_URL : '';
$errors = $errors ?? [];
$csrf   = htmlspecialchars($_SESSION['csrf'] ?? '');
?>
<section class="py-5">
    <div class="container" style="max-width:720px;">
        <h1 class="h4 mb-3">Buy Credits</h1>

        <?php if (!empty($errors['credits_qty'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errors['credits_qty']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= $base ?>/credits/checkout">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <div class="row g-3">
                <?php foreach ([5, 10, 50, 100, 250, 500] as $qty): ?>
                    <div class="col-6 col-md-4">
                        <label class="border rounded p-3 w-100">
                            <input type="radio" class="form-check-input me-2" name="credits_qty" value="<?= $qty ?>">
                            <div class="fw-semibold"><?= $qty ?> credits</div>
                            <div class="text-muted small">RM <?= number_format($qty, 2) ?></div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Checkout with Stripe</button>
                <a href="<?= $base ?>/welcome" class="btn btn-outline-secondary">Cancel</a>
            </div>

            <div class="text-muted small mt-3">
                1 credit = RM1. You can use credits to unlock candidate contact info/resumes.
            </div>
        </form>
    </div>
</section>