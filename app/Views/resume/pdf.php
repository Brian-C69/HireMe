<?php
// $candidate, $experiences, $skills, $languages, $education, $profileLocal
$val = fn($k, $d = '') => htmlspecialchars((string)($candidate[$k] ?? $d));
function dateMY($d)
{
    return $d ? date('M Y', strtotime($d)) : '';
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Resume — <?= $val('full_name', 'Candidate') ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #222;
            margin: 24px;
        }

        h1,
        h2,
        h3 {
            margin: 0 0 8px;
        }

        h1 {
            font-size: 22px;
            letter-spacing: .5px;
        }

        h2 {
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-top: 16px;
        }

        .muted {
            color: #666;
        }

        .section {
            margin-top: 14px;
        }

        .grid {
            display: table;
            width: 100%;
        }

        .col {
            display: table-cell;
            vertical-align: top;
        }

        .w-70 {
            width: 70%;
        }

        .w-30 {
            width: 30%;
        }

        .mb-6 {
            margin-bottom: 6px;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-14 {
            margin-bottom: 14px;
        }

        .row {
            margin: 0 -6px;
        }

        .row>.col {
            padding: 0 6px;
        }

        .pill {
            display: inline-block;
            border: 1px solid #ddd;
            padding: 2px 6px;
            margin: 2px 4px 0 0;
            border-radius: 4px;
            font-size: 11px;
        }

        .small {
            font-size: 11px;
        }

        .bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .bar>span {
            display: block;
            height: 100%;
            background: #0d6efd;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 6px 8px;
            border: 1px solid #e5e5e5;
            font-size: 12px;
        }

        .avatar {
            width: 92px;
            height: 92px;
            border-radius: 8px;
            object-fit: cover;
            background: #f3f3f3;
            border: 1px solid #e5e5e5;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="grid mb-14">
        <div class="col w-70">
            <h1><?= $val('full_name', 'Candidate') ?></h1>
            <div class="muted small">
                <span><?= htmlspecialchars((string)($candidate['email'] ?? '')) ?></span> ·
                <span><?= htmlspecialchars((string)($candidate['phone_number'] ?? '')) ?></span>
                <?php if (!empty($candidate['location'])): ?> · <span><?= htmlspecialchars((string)$candidate['location']) ?></span><?php endif; ?>
                <?php if (!empty($candidate['linkedin_url'])): ?> · <span><?= htmlspecialchars((string)$candidate['linkedin_url']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($candidate['expected_salary'])): ?>
                <div class="small muted">Expected Salary: RM <?= number_format((float)$candidate['expected_salary'], 2) ?></div>
            <?php endif; ?>
            <?php if (!empty($candidate['notice_period'])): ?>
                <div class="small muted">Notice Period: <?= htmlspecialchars((string)$candidate['notice_period']) ?></div>
            <?php endif; ?>
            <?php if (!empty($candidate['skills'])): ?>
                <div class="small muted">Summary: <?= htmlspecialchars((string)$candidate['skills']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col w-30" style="text-align:right;">
            <?php if (!empty($profileLocal)): ?>
                <img class="avatar" src="<?= htmlspecialchars($profileLocal) ?>" alt="Profile">
            <?php endif; ?>
        </div>
    </div>

    <!-- Experiences -->
    <?php if (!empty($experiences)): ?>
        <div class="section">
            <h2>Job Experience</h2>
            <?php foreach ($experiences as $e): ?>
                <div class="mb-10">
                    <strong><?= htmlspecialchars((string)$e['job_title'] ?: '') ?></strong>
                    <?php if (!empty($e['company'])): ?> — <?= htmlspecialchars((string)$e['company']) ?><?php endif; ?><br>
                        <span class="small muted"><?= dateMY($e['start_date'] ?? '') ?><?php if (!empty($e['end_date'])): ?> – <?= dateMY($e['end_date']) ?><?php else: ?> – Present<?php endif; ?></span>
                        <?php if (!empty($e['description'])): ?><div class="small"><?= nl2br(htmlspecialchars((string)$e['description'])) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Skills -->
    <?php if (!empty($skills)): ?>
        <div class="section">
            <h2>Skills</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:35%;">Skill</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $s): $lvl = (int)$s['level']; ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$s['name']) ?></td>
                            <td>
                                <div class="bar"><span style="width: <?= max(0, min(100, $lvl)) ?>%"></span></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Languages -->
    <?php if (!empty($languages)): ?>
        <div class="section">
            <h2>Languages</h2>
            <div>
                <?php foreach ($languages as $L): ?>
                    <span class="pill"><?= htmlspecialchars((string)$L['language']) ?> —
                        <span class="muted small">Spoken: <?= htmlspecialchars((string)$L['spoken_level']) ?>, Written: <?= htmlspecialchars((string)$L['written_level']) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Education -->
    <?php if (!empty($education)): ?>
        <div class="section">
            <h2>Education</h2>
            <?php foreach ($education as $ed): ?>
                <div class="mb-6">
                    <strong><?= htmlspecialchars((string)$ed['qualification']) ?></strong>
                    <?php if (!empty($ed['graduation_year'])): ?> — <span class="muted"><?= htmlspecialchars((string)$ed['graduation_year']) ?></span><?php endif; ?><br>
                    <?php if (!empty($ed['institution'])): ?><span><?= htmlspecialchars((string)$ed['institution']) ?></span><?php endif; ?>
                    <?php if (!empty($ed['field'])): ?> · <span><?= htmlspecialchars((string)$ed['field']) ?></span><?php endif; ?>
                    <?php if (!empty($ed['details'])): ?><div class="small"><?= nl2br(htmlspecialchars((string)$ed['details'])) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>

</html>