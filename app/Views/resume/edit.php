<!-- app/Views/resume/edit.php -->
<?php
$errors = $errors ?? [];
$old    = $old ?? [];
$base   = defined('BASE_URL') ? BASE_URL : '';
$get = fn($k, $d = '') => htmlspecialchars($old[$k] ?? ($candidate[$k] ?? $d));
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Build / Update Resume</h1>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= $base ?>/welcome">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/resume/pdf" target="_blank" rel="noopener">
                    <i class="bi bi-filetype-pdf"></i> Preview PDF
                </a>
                <a class="btn btn-primary" href="<?= $base ?>/resume/pdf?download=1">
                    <i class="bi bi-download"></i> Download PDF
                </a>
            </div>
        </div>

        <form action="<?= $base ?>/resume" method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

            <!-- Candidate basics -->
            <div class="card mb-3">
                <div class="card-header fw-semibold">Basic Information</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" name="full_name" value="<?= $get('full_name') ?>" required>
                        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input class="form-control" name="phone_number" value="<?= $get('phone_number') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Birthday</label>
                        <input class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" type="date" name="date_of_birth" value="<?= $get('date_of_birth') ?>">
                        <?php if (isset($errors['date_of_birth'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['date_of_birth']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Salary (RM)</label>
                        <input class="form-control <?= isset($errors['expected_salary']) ? 'is-invalid' : '' ?>" name="expected_salary" value="<?= $get('expected_salary') ?>" placeholder="e.g., 4500">
                        <?php if (isset($errors['expected_salary'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['expected_salary']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Notice Period</label>
                        <select class="form-select" name="notice_period">
                            <?php
                            $np = $candidate['notice_period'] ?? '';
                            $opts = ['1 WEEK', '2 WEEK', '1 MONTH', '2 MONTH', 'OTHER'];
                            foreach ($opts as $o): $sel = ($np === $o) ? 'selected' : ''; ?>
                                <option value="<?= $o ?>" <?= $sel ?>><?= $o ?></option>
                            <?php endforeach; ?>
                            <option value="" <?= $np === '' ? 'selected' : '' ?>>—</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">LinkedIn (optional)</label>
                        <input class="form-control <?= isset($errors['linkedin_url']) ? 'is-invalid' : '' ?>" name="linkedin_url" value="<?= $get('linkedin_url') ?>" placeholder="https://www.linkedin.com/in/…">
                        <?php if (isset($errors['linkedin_url'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['linkedin_url']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input class="form-control" name="location" value="<?= $get('location') ?>" placeholder="Kuala Lumpur">
                    </div>
                </div>
            </div>

            <!-- Experience -->
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Job Experience</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="addExp">Add experience</button>
                </div>
                <div class="card-body">
                    <div id="expList" class="vstack gap-3">
                        <?php
                        $rows = !empty($old['exp_company']) ? count($old['exp_company']) : max(1, count($experiences));
                        for ($i = 0; $i < $rows; $i++):
                            $row = $experiences[$i] ?? [];
                            $company = htmlspecialchars($old['exp_company'][$i] ?? ($row['company'] ?? ''));
                            $title   = htmlspecialchars($old['exp_title'][$i]   ?? ($row['job_title'] ?? ''));
                            $from    = htmlspecialchars($old['exp_from'][$i]    ?? ($row['start_date'] ?? ''));
                            $to      = htmlspecialchars($old['exp_to'][$i]      ?? ($row['end_date'] ?? ''));
                            $desc    = htmlspecialchars($old['exp_desc'][$i]    ?? ($row['description'] ?? ''));
                        ?>
                            <div class="border rounded p-3 exp-item">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Company</label>
                                        <input class="form-control" name="exp_company[]" value="<?= $company ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Job Title</label>
                                        <input class="form-control" name="exp_title[]" value="<?= $title ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">From</label>
                                        <input class="form-control" type="date" name="exp_from[]" value="<?= $from ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">To</label>
                                        <input class="form-control" type="date" name="exp_to[]" value="<?= $to ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" rows="2" name="exp_desc[]"><?= $desc ?></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button class="btn btn-sm btn-outline-danger remove-exp" type="button">Remove</button>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Skills -->
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Job Experience &amp; Skills</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="addSkill">Add skill</button>
                </div>
                <div class="card-body">
                    <div id="skillList" class="vstack gap-2">
                        <?php
                        $rows = !empty($old['skill_name']) ? count($old['skill_name']) : max(1, count($skills));
                        for ($i = 0; $i < $rows; $i++):
                            $row = $skills[$i] ?? [];
                            $name = htmlspecialchars($old['skill_name'][$i]  ?? ($row['name'] ?? ''));
                            $lvl  = (int)($old['skill_level'][$i] ?? ($row['level'] ?? 0));
                        ?>
                            <div class="row g-2 align-items-center skill-item">
                                <div class="col-md-4">
                                    <input class="form-control" name="skill_name[]" placeholder="e.g., PHP" value="<?= $name ?>">
                                </div>
                                <div class="col-md-6">
                                    <input type="range" class="form-range skill-range" name="skill_level[]" min="0" max="100" step="5" value="<?= $lvl ?>">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar skill-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $lvl ?>" style="width: <?= $lvl ?>%"></div>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex justify-content-end align-items-center">
                                    <span class="badge text-bg-primary skill-value me-2" style="min-width:42px;"><?= $lvl ?></span>
                                    <button class="btn btn-sm btn-outline-danger remove-skill" type="button">Remove</button>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Languages -->
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Languages</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="addLang">Add language</button>
                </div>
                <div class="card-body">
                    <div id="langList" class="vstack gap-2">
                        <?php
                        $rows = !empty($old['lang_name']) ? count($old['lang_name']) : max(1, count($languages));
                        $levels = ['Basic', 'Intermediate', 'Fluent', 'Native'];
                        for ($i = 0; $i < $rows; $i++):
                            $row = $languages[$i] ?? [];
                            $lname  = htmlspecialchars($old['lang_name'][$i]   ?? ($row['language'] ?? ''));
                            $spoken = htmlspecialchars($old['lang_spoken'][$i] ?? ($row['spoken_level'] ?? 'Basic'));
                            $writ   = htmlspecialchars($old['lang_written'][$i] ?? ($row['written_level'] ?? 'Basic'));
                        ?>
                            <div class="row g-2 align-items-center lang-item">
                                <div class="col-md-4"><input class="form-control" name="lang_name[]" placeholder="e.g., English" value="<?= $lname ?>"></div>
                                <div class="col-md-3">
                                    <select class="form-select" name="lang_spoken[]">
                                        <?php foreach ($levels as $L): ?><option value="<?= $L ?>" <?= $L === $spoken ? 'selected' : '' ?>>Spoken: <?= $L ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="lang_written[]">
                                        <?php foreach ($levels as $L): ?><option value="<?= $L ?>" <?= $L === $writ ? 'selected' : '' ?>>Written: <?= $L ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button class="btn btn-sm btn-outline-danger remove-lang" type="button">Remove</button>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Education -->
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>Education</span>
                    <button class="btn btn-sm btn-outline-primary" type="button" id="addEdu">Add education</button>
                </div>
                <div class="card-body">
                    <div id="eduList" class="vstack gap-3">
                        <?php
                        $rows = !empty($old['edu_qual']) ? count($old['edu_qual']) : max(1, count($education));
                        $quals = ['SPM', 'Diploma', 'Degree', 'Master', 'Prof Quali'];
                        for ($i = 0; $i < $rows; $i++):
                            $row = $education[$i] ?? [];
                            $qual = htmlspecialchars($old['edu_qual'][$i]   ?? ($row['qualification'] ?? 'Diploma'));
                            $sch  = htmlspecialchars($old['edu_school'][$i] ?? ($row['institution'] ?? ''));
                            $fld  = htmlspecialchars($old['edu_field'][$i]  ?? ($row['field'] ?? ''));
                            $yr   = htmlspecialchars($old['edu_year'][$i]   ?? ($row['graduation_year'] ?? ''));
                            $det  = htmlspecialchars($old['edu_details'][$i] ?? ($row['details'] ?? ''));
                        ?>
                            <div class="border rounded p-3 edu-item">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Qualification</label>
                                        <select class="form-select" name="edu_qual[]">
                                            <?php foreach ($quals as $Q): ?><option value="<?= $Q ?>" <?= $Q === $qual ? 'selected' : '' ?>><?= $Q ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Institution</label>
                                        <input class="form-control" name="edu_school[]" value="<?= $sch ?>" placeholder="e.g., TAR UMT">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Field</label>
                                        <input class="form-control" name="edu_field[]" value="<?= $fld ?>" placeholder="e.g., IT">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Year</label>
                                        <input class="form-control" name="edu_year[]" value="<?= $yr ?>" placeholder="2022">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Details</label>
                                        <textarea class="form-control" rows="2" name="edu_details[]"><?= $det ?></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button class="btn btn-sm btn-outline-danger remove-edu" type="button">Remove</button>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="d-grid">
                <button class="btn btn-primary btn-lg" type="submit">Save Resume</button>
            </div>
        </form>
    </div>
</section>

<script>
    (function() {
        // --- Sync a single skill row (range → bar + number)
        function syncSkillRow(row) {
            const input = row.querySelector('.skill-range');
            const bar = row.querySelector('.skill-bar');
            const valEl = row.querySelector('.skill-value');
            if (!input || !bar) return;
            const v = Math.max(0, Math.min(100, parseInt(input.value || '0', 10)));
            bar.style.width = v + '%';
            bar.setAttribute('aria-valuenow', String(v));
            if (valEl) valEl.textContent = v;
        }

        // --- Generic add-row helper with optional post-append hook
        const addRow = (btnId, listId, html, afterAppend) => {
            const btn = document.getElementById(btnId);
            const list = document.getElementById(listId);
            if (!btn || !list) return;
            btn.addEventListener('click', () => {
                const div = document.createElement('div');
                div.innerHTML = html.trim();
                const node = div.firstElementChild;
                list.appendChild(node);
                if (typeof afterAppend === 'function') afterAppend(node);
            });
        };

        // --- Templates
        const expTpl = `
  <div class="border rounded p-3 exp-item mt-2">
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label">Company</label><input class="form-control" name="exp_company[]"></div>
      <div class="col-md-4"><label class="form-label">Job Title</label><input class="form-control" name="exp_title[]"></div>
      <div class="col-md-2"><label class="form-label">From</label><input class="form-control" type="date" name="exp_from[]"></div>
      <div class="col-md-2"><label class="form-label">To</label><input class="form-control" type="date" name="exp_to[]"></div>
      <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" rows="2" name="exp_desc[]"></textarea></div>
      <div class="col-12 text-end"><button class="btn btn-sm btn-outline-danger remove-exp" type="button">Remove</button></div>
    </div>
  </div>`;

        const skillTpl = `
  <div class="row g-2 align-items-center skill-item">
    <div class="col-md-4"><input class="form-control" name="skill_name[]" placeholder="e.g., PHP"></div>
    <div class="col-md-6">
      <input type="range" class="form-range skill-range" name="skill_level[]" min="0" max="100" step="5" value="50">
      <div class="progress" style="height: 6px;">
        <div class="progress-bar skill-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" style="width: 50%"></div>
      </div>
    </div>
    <div class="col-md-2 d-flex justify-content-end align-items-center">
      <span class="badge text-bg-primary skill-value me-2" style="min-width:42px;">50</span>
      <button class="btn btn-sm btn-outline-danger remove-skill" type="button">Remove</button>
    </div>
  </div>`;

        const langTpl = `
  <div class="row g-2 align-items-center lang-item">
    <div class="col-md-4"><input class="form-control" name="lang_name[]" placeholder="e.g., English"></div>
    <div class="col-md-3"><select class="form-select" name="lang_spoken[]"><option>Basic</option><option>Intermediate</option><option selected>Fluent</option><option>Native</option></select></div>
    <div class="col-md-3"><select class="form-select" name="lang_written[]"><option>Basic</option><option>Intermediate</option><option selected>Fluent</option><option>Native</option></select></div>
    <div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-danger remove-lang" type="button">Remove</button></div>
  </div>`;

        const eduTpl = `
  <div class="border rounded p-3 edu-item mt-2">
    <div class="row g-2">
      <div class="col-md-3"><label class="form-label">Qualification</label><select class="form-select" name="edu_qual[]"><option>SPM</option><option selected>Diploma</option><option>Degree</option><option>Master</option><option>Prof Quali</option></select></div>
      <div class="col-md-5"><label class="form-label">Institution</label><input class="form-control" name="edu_school[]"></div>
      <div class="col-md-2"><label class="form-label">Field</label><input class="form-control" name="edu_field[]"></div>
      <div class="col-md-2"><label class="form-label">Year</label><input class="form-control" name="edu_year[]" placeholder="2024"></div>
      <div class="col-12"><label class="form-label">Details</label><textarea class="form-control" rows="2" name="edu_details[]"></textarea></div>
      <div class="col-12 text-end"><button class="btn btn-sm btn-outline-danger remove-edu" type="button">Remove</button></div>
    </div>
  </div>`;

        // --- Bind add buttons
        addRow('addExp', 'expList', expTpl);
        addRow('addSkill', 'skillList', skillTpl, syncSkillRow);
        addRow('addLang', 'langList', langTpl);
        addRow('addEdu', 'eduList', eduTpl);

        // --- Remove row delegation
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-exp')) e.target.closest('.exp-item')?.remove();
            if (e.target.closest('.remove-skill')) e.target.closest('.skill-item')?.remove();
            if (e.target.closest('.remove-lang')) e.target.closest('.lang-item')?.remove();
            if (e.target.closest('.remove-edu')) e.target.closest('.edu-item')?.remove();
        });

        // --- Input delegation for skills (keeps new rows working)
        const skillList = document.getElementById('skillList');
        if (skillList) {
            // Init any pre-rendered rows
            skillList.querySelectorAll('.skill-item').forEach(syncSkillRow);
            // Live updates
            skillList.addEventListener('input', (e) => {
                if (e.target && e.target.matches('.skill-range')) {
                    const row = e.target.closest('.skill-item');
                    if (row) syncSkillRow(row);
                }
            });
        }
    })();
</script>