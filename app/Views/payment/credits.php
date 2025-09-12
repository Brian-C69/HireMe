<?php
$base     = defined('BASE_URL') ? BASE_URL : '';
$balance  = $balance ?? 0;
$unit     = $unit ?? 1.00;
$packages = $packages ?? [5, 10, 50, 100, 250];
?>
<section class="py-4">
    <div class="container" style="max-width: 880px;">
        <h1 class="h4 mb-3">Buy Credits</h1>

        <div class="alert alert-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>Current Balance: <strong><?= (int)$balance ?></strong> credits</div>
                <div>Price: RM<?= number_format((float)$unit, 2) ?> / credit</div>
            </div>
        </div>

        <form action="<?= $base ?>/credits/pay" method="post" class="card p-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <div class="row g-3">

                <!-- radios keep name="credits" -->
                <?php foreach ($packages as $p): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label class="w-100">
                            <input type="radio" name="credits" class="btn-check" value="<?= (int)$p ?>" id="cr-<?= (int)$p ?>">
                            <span class="btn btn-outline-primary w-100">
                                <?= (int)$p ?> Credits<br>
                                <small class="text-muted">RM<?= number_format($p * (float)$unit, 2) ?></small>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <!-- Custom amount now uses a different name -->
                <div class="col-12">
                    <div class="input-group">
                        <span class="input-group-text">Custom</span>
                        <input
                            type="number"
                            min="1" max="10000" step="1"
                            class="form-control"
                            name="credits_custom"
                            placeholder="Enter credits (1–10000)"
                            oninput="document.querySelectorAll('input[name=credits]').forEach(r=>r.checked=false)">
                        <button class="btn btn-primary" type="submit">Pay (Mock)</button>
                    </div>
                    <div class="form-text">Pick a package OR enter a custom amount.</div>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <p class="text-muted small">
            Each unlocked candidate costs <strong>1 credit</strong>. Unlocking reveals full contact details and full resume.
        </p>
        <a class="btn btn-outline-secondary" href="<?= $base ?>/candidates">Browse Candidates</a>
    </div>
</section>