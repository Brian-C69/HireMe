<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$questions   = $questions   ?? [];
$apps        = $apps        ?? [];
$answersByApp = $answersByApp ?? [];
$unlockedIds = $unlockedIds ?? [];
$badge = function (string $s): string {
    $map = [
        'Applied'   => 'primary',
        'Reviewed'  => 'info',
        'Interview' => 'warning',
        'Rejected'  => 'danger',
        'Withdrawn' => 'secondary',
    ];
    return 'badge text-bg-' . ($map[$s] ?? 'light');
};
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Applicants</h1>
            <a class="btn btn-outline-secondary" href="<?= $base ?>/jobs/mine">Back to My Jobs</a>
        </div>

        <?php if (!$apps): ?>
            <div class="alert alert-light border">No applicants yet.</div>
        <?php else: ?>

            <!-- questions header -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Screening questions</div>
                    <?php if (!$questions): ?>
                        <div class="text-muted small">No questions attached to this job.</div>
                    <?php else: ?>
                        <ol class="mb-0">
                            <?php foreach ($questions as $q): ?>
                                <li><?= htmlspecialchars((string)$q['prompt']) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>

            <!-- applicants list -->
            <div class="vstack gap-3">
                <?php foreach ($apps as $a): ?>
                    <?php
                    $aid   = (int)$a['applicant_id'];
                    $cid   = (int)$a['candidate_id'];
                    $name  = htmlspecialchars((string)($a['full_name'] ?? 'Candidate'));
                    $loc   = trim(implode(', ', array_filter([(string)($a['city'] ?? ''), (string)($a['state'] ?? '')]))) ?: '—';
                    $exp   = (int)($a['experience_years'] ?? 0);
                    $status = (string)($a['application_status'] ?? 'Applied');
                    $appliedAt = $a['application_date'] ? date('d/m/Y H:i', strtotime((string)$a['application_date'])) : '';
                    $premium = !empty($a['premium_badge']);
                    $verified = !empty($a['verified_status']);
                    $unlocked = in_array($cid, $unlockedIds, true);
                    $avatar = trim((string)($a['profile_picture_url'] ?? ''));
                    ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <div class="border bg-light d-flex align-items-center justify-content-center rounded" style="width:64px;height:64px;">
                                    <?php if ($avatar): ?>
                                        <img src="<?= $base . '/' . ltrim($avatar, '/') ?>" alt="Avatar" style="max-width:100%;max-height:100%;object-fit:cover;border-radius:8px;">
                                    <?php else: ?>
                                        <span class="text-muted small">Avatar</span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="h6 mb-0"><?= $name ?></div>
                                        <?php if ($premium): ?>
                                            <span class="badge rounded-pill text-bg-warning text-dark">⭐ Premium</span>
                                        <?php endif; ?>
                                        <?php if ($verified): ?>
                                            <span class="badge rounded-pill text-bg-success">Verified ✓</span>
                                        <?php endif; ?>
                                        <?php if ($unlocked): ?>
                                            <span class="badge rounded-pill text-bg-success">Resume Unlocked</span>
                                        <?php endif; ?>
                                        <span class="<?= $badge($status) ?> ms-auto"><?= htmlspecialchars($status) ?></span>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars($loc) ?> · <?= $exp ?> yrs experience · Applied <?= htmlspecialchars($appliedAt) ?>
                                    </div>

                                    <!-- Answers -->
                                    <div class="mt-3">
                                        <?php if (!$questions): ?>
                                            <div class="text-muted small">No screening questions for this job.</div>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <?php foreach ($questions as $q): ?>
                                                    <?php
                                                    $qid = (int)$q['id'];
                                                    $ans = (string)($answersByApp[$aid][$qid] ?? '');
                                                    ?>
                                                    <div class="col-12">
                                                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)$q['prompt']) ?></div>
                                                        <div class="p-2 bg-light rounded border">
                                                            <?= $ans !== '' ? nl2br(htmlspecialchars($ans)) : '<span class="text-muted">No answer</span>' ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">

                                        <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/candidates/<?= $cid ?>" target="_blank">View Profile</a>

                                        <?php if (!$unlocked): ?>
                                            <form action="<?= $base ?>/candidates/<?= $cid ?>/unlock" method="post" onsubmit="return confirm('Use 1 credit to unlock this resume?');">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                                <button class="btn btn-sm btn-primary" type="submit">Unlock Resume (−1 credit)</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Quick actions -->
                                        <form action="<?= $base ?>/applications/<?= $aid ?>/status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                            <input type="hidden" name="status" value="Interview">
                                            <button class="btn btn-sm btn-warning" type="submit">Call for Interview</button>
                                        </form>

                                        <form action="<?= $base ?>/applications/<?= $aid ?>/status" method="post" class="d-inline" onsubmit="return confirm('Reject this applicant?');">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                            <input type="hidden" name="status" value="Rejected">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                        </form>

                                        <!-- Or set from dropdown -->
                                        <form action="<?= $base ?>/applications/<?= $aid ?>/status" method="post" class="d-inline ms-auto">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                            <div class="input-group input-group-sm" style="max-width: 320px;">
                                                <select name="status" class="form-select">
                                                    <?php
                                                    $opts = ['Applied', 'Reviewed', 'Interview', 'Rejected'];
                                                    foreach ($opts as $opt):
                                                    ?>
                                                        <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input name="note" type="text" class="form-control" placeholder="Optional note">
                                                <button class="btn btn-outline-secondary" type="submit">Update</button>
                                            </div>
                                        </form>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>