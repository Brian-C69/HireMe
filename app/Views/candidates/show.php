<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$viewer = $_SESSION['user']['role'] ?? '';
$csrf = $_SESSION['csrf'] ?? '';
$unlocked = $unlocked ?? false;
$avatar = trim((string)($candidate['profile_picture_url'] ?? ''));
$name = htmlspecialchars((string)($candidate['full_name'] ?? ''));
$loc  = trim(implode(', ', array_filter([(string)($candidate['city'] ?? ''), (string)($candidate['state'] ?? '')])));
$loc  = $loc !== '' ? htmlspecialchars($loc) : '—';
$exp  = (int)($candidate['experience_years'] ?? 0);
$premium = !empty($candidate['premium_badge']);
$verified = !empty($candidate['verified_status']);
?>
<section class="py-4">
    <div class="container" style="max-width: 980px;">
        <div class="d-flex align-items-center mb-3">
            <div class="border bg-light d-flex align-items-center justify-content-center rounded me-3" style="width:80px;height:80px;">
                <?php if ($avatar): ?>
                    <img src="<?= $base . '/' . ltrim($avatar, '/') ?>" alt="Avatar" style="max-width:100%;max-height:100%;object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    <span class="text-muted small">Avatar</span>
                <?php endif; ?>
            </div>
            <div>
                <div class="h5 mb-1">
                    <?= $name ?>
                    <?= $premium ? '<span class="badge rounded-pill bg-warning text-dark ms-1">&#9733; Premium</span>' : '' ?>
                    <?= $verified ? '<span class="badge rounded-pill bg-success ms-1">&#10003; Verified</span>' : '' ?>
                </div>
                <div class="text-muted small"><?= $loc ?> · <?= $exp ?> yrs experience</div>
            </div>
            <div class="ms-auto">
                <?php if ($unlocked): ?>
                    <span class="badge text-bg-success">Unlocked</span>
                <?php else: ?>
                    <form action="<?= $base ?>/candidates/<?= (int)$candidate['candidate_id'] ?>/unlock" method="post" onsubmit="return confirm('Use 1 credit to unlock this resume?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button class="btn btn-primary">Unlock Resume (−1 credit)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Contact</div>
                    <div class="card-body">
                        <?php if ($unlocked): ?>
                            <div>Email: <?= htmlspecialchars((string)($candidate['email'] ?? '')) ?></div>
                            <div>Phone: <?= htmlspecialchars((string)($candidate['phone_number'] ?? '')) ?></div>
                            <?php if (!empty($candidate['address'])): ?><div>Address: <?= htmlspecialchars((string)$candidate['address']) ?></div><?php endif; ?>
                        <?php else: ?>
                            <div>Email: <span class="text-muted">hidden</span></div>
                            <div>Phone: <span class="text-muted">hidden</span></div>
                            <div class="small text-muted mt-2">Unlock to reveal contact & full resume.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Summary</div>
                    <div class="card-body">
                        <?php if (!empty($candidate['skills'])): ?>
                            <div class="<?= $unlocked ? '' : 'text-muted' ?>">
                                <?= $unlocked ? nl2br(htmlspecialchars((string)$candidate['skills'])) : 'Summary available after unlock.' ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">—</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Full resume sections only when unlocked -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold">Resume</div>
                    <div class="card-body">
                        <?php if ($unlocked): ?>
                            <?php if (!empty($experiences)): ?>
                                <h6>Experience</h6>
                                <ul class="mb-3">
                                    <?php foreach ($experiences as $e): ?>
                                        <li>
                                            <strong><?= htmlspecialchars((string)($e['job_title'] ?? '')) ?></strong>
                                            <?php if (!empty($e['company'])): ?> — <?= htmlspecialchars((string)$e['company']) ?><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($skills)): ?>
                                <h6>Skills</h6>
                                <div class="mb-3">
                                    <?php foreach ($skills as $s): ?>
                                        <span class="badge text-bg-light border me-1 mb-1"><?= htmlspecialchars((string)$s['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($languages)): ?>
                                <h6>Languages</h6>
                                <div class="mb-3">
                                    <?php foreach ($languages as $L): ?>
                                        <span class="badge text-bg-light border me-1 mb-1">
                                            <?= htmlspecialchars((string)$L['language']) ?> (<?= htmlspecialchars((string)$L['spoken_level']) ?>/<?= htmlspecialchars((string)$L['written_level']) ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($education)): ?>
                                <h6>Education</h6>
                                <ul class="mb-0">
                                    <?php foreach ($education as $ed): ?>
                                        <li>
                                            <strong><?= htmlspecialchars((string)$ed['qualification']) ?></strong>
                                            <?php if (!empty($ed['institution'])): ?> — <?= htmlspecialchars((string)$ed['institution']) ?><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted">Full resume is hidden. Unlock to view experience, skills, languages & education.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>