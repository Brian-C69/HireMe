<?php
$base  = defined('BASE_URL') ? BASE_URL : '';
$me    = $me ?? [];
$price = $price ?? 50.00;
$has   = !empty($me['premium_badge']);
?>
<section class="py-4">
    <div class="container" style="max-width: 760px;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Go Premium</h1>
            <?php if ($has): ?>
                <span class="badge text-bg-warning"><i class="bi bi-star-fill me-1"></i> Premium Active</span>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <p class="mb-2">
                    Boost trust and visibility with a <strong>Premium Badge</strong>. Your profile gets a golden badge and higher visibility in searches.
                </p>
                <ul class="small text-muted mb-3">
                    <li>One-time purchase: <strong>RM<?= number_format($price, 2) ?></strong></li>
                    <li>Instant activation after successful payment</li>
                </ul>

                <?php if ($has): ?>
                    <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle me-2"></i>
                        Your premium badge is active. Thank you!</div>
                    <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">Back to Dashboard</a>
                <?php else: ?>
                    <form action="<?= $base ?>/premium/pay" method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-credit-card me-1"></i> Pay RM<?= number_format($price, 2) ?>
                        </button>
                    </form>
                    <a class="btn btn-outline-secondary ms-2" href="<?= $base ?>/welcome">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>