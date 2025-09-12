<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class AdminController
{
    // Whitelist tables + primary keys (adjust to your schema)
    private const TABLES = [
        'admins' => 'admin_id',
        'users'                     => 'id',
        'candidates'                => 'candidate_id',
        'employers'                 => 'employer_id',
        'recruiters'                => 'recruiter_id',
        'job_postings'              => 'job_posting_id',
        'applications'              => 'applicant_id',
        'application_answers'       => 'id',
        'application_status_history' => 'id',
        'micro_questions'           => 'id',
        'job_micro_questions'       => 'id',
        'resume_unlocks'            => 'id',
        'resumes'                   => 'id',
        'candidate_experiences'     => 'id',
        'candidate_skills'          => 'id',
        'candidate_languages'       => 'id',
        'candidate_education'       => 'id',
        'payments'                  => 'payment_id',
        'billing'                   => 'billing_id',
    ];

    private function flash(string $t, string $m): void
    {
        $_SESSION['flash'] = ['type' => $t, 'message' => $m];
    }
    private function setErrors(array $e): void
    {
        $_SESSION['errors'] = $e;
    }
    private function takeErrors(): array
    {
        $e = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);
        return $e;
    }
    private function setOld(array $o): void
    {
        $_SESSION['old'] = $o;
    }
    private function takeOld(): array
    {
        $o = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $o;
    }
    private function redirect(string $p): void
    {
        $b = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $b . $p, true, 302);
        exit;
    }
    private function csrf(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
    private function csrfOk(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    }

    private function emailExistsAny(\PDO $pdo, string $email, ?int $ignoreCandidateId = null): bool
    {
        // candidates (optionally ignore a specific id)
        $st = $pdo->prepare("SELECT candidate_id FROM candidates WHERE email=:e");
        $st->execute([':e' => $email]);
        $row = $st->fetch();
        if ($row && (int)$row['candidate_id'] !== (int)($ignoreCandidateId ?? 0)) return true;
        // employers
        $st = $pdo->prepare("SELECT 1 FROM employers WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        if ($st->fetchColumn()) return true;
        // recruiters
        $st = $pdo->prepare("SELECT 1 FROM recruiters WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        if ($st->fetchColumn()) return true;
        return false;
    }

    private function requireAdmin(): void
    {
        Auth::requireRole('Admin');
    }

    public function index(): void
    {
        // admin dashboard
        $root = dirname(__DIR__, 2);
        $title = 'Admin — Dashboard';
        $viewFile = $root . '/app/Views/admin/index.php';
        require $root . '/app/Views/layout.php';
    }

    private function tableOk(string $t): bool
    {
        return isset(self::TABLES[$t]);
    }
    private function pk(string $t): string
    {
        return self::TABLES[$t];
    }

    public function dashboard(array $params = []): void
    {
        \App\Core\Auth::requireRole('Admin');

        $pdo   = \App\Core\DB::conn();
        $root  = dirname(__DIR__, 2);
        $title = 'Admin Dashboard — HireMe';
        $viewFile = $root . '/app/Views/admin/dashboard.php';

        // whatever you compute for the view:
        $counts = [
            'candidates' => (int)$pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
            'employers'  => (int)$pdo->query("SELECT COUNT(*) FROM employers")->fetchColumn(),
            'recruiters' => (int)$pdo->query("SELECT COUNT(*) FROM recruiters")->fetchColumn(),
            'jobs'       => (int)$pdo->query("SELECT COUNT(*) FROM job_postings")->fetchColumn(),
            'apps'       => (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
        ];

        require $root . '/app/Views/layout.php';
    }

    public function create(array $params): void
    {
        $this->requireAdmin();
        $table = (string)($params['table'] ?? '');
        if (!$this->tableOk($table)) {
            $this->flash('danger', 'Unknown table.');
            $this->redirect('/admin/tables');
        }

        $pdo = DB::conn();
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $root = dirname(__DIR__, 2);
        $title = "Admin — Create in $table";
        $viewFile = $root . '/app/Views/admin/form.php';
        $mode = 'create';
        $row = [];
        $csrf = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function store(array $params): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin');
        }

        $table = (string)($params['table'] ?? '');
        if (!$this->tableOk($table)) {
            $this->flash('danger', 'Unknown table.');
            $this->redirect('/admin/tables');
        }

        $pdo = DB::conn();
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pk = $this->pk($table);

        $fields = [];
        $bind   = [];
        foreach ($cols as $c) {
            $name = $c['Field'];
            if ($name === $pk && strpos((string)$c['Extra'], 'auto_increment') !== false) continue;
            if (!array_key_exists($name, $_POST)) continue;
            $fields[] = "`$name`";
            $bind[":$name"] = ($_POST[$name] === '' ? null : $_POST[$name]);
        }

        if (!$fields) {
            $this->flash('warning', 'No data to insert.');
            $this->redirect("/admin/t/$table");
        }

        $place = implode(',', array_map(fn($f) => ':' . trim($f, '`'), $fields));
        $sql = "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES ($place)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);

        $this->flash('success', 'Row created.');
        $this->redirect("/admin/t/$table");
    }

    public function edit(array $params): void
    {
        $this->requireAdmin();
        $table = (string)($params['table'] ?? '');
        $id    = (string)($params['id'] ?? '');
        if (!$this->tableOk($table)) {
            $this->flash('danger', 'Unknown table.');
            $this->redirect('/admin/tables');
        }

        $pdo = DB::conn();
        $pk  = $this->pk($table);
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk`=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->flash('danger', 'Row not found.');
            $this->redirect("/admin/t/$table");
        }

        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $root = dirname(__DIR__, 2);
        $title = "Admin — Edit $table#$id";
        $viewFile = $root . '/app/Views/admin/form.php';
        $mode = 'edit';
        $csrf = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin');
        }

        $table = (string)($params['table'] ?? '');
        $id    = (string)($params['id'] ?? '');
        if (!$this->tableOk($table)) {
            $this->flash('danger', 'Unknown table.');
            $this->redirect('/admin/tables');
        }

        $pdo = DB::conn();
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pk = $this->pk($table);

        $set  = [];
        $bind = [":pk" => $id];
        foreach ($cols as $c) {
            $name = $c['Field'];
            if ($name === $pk && strpos((string)$c['Extra'], 'auto_increment') !== false) continue;
            if (!array_key_exists($name, $_POST)) continue;
            $set[] = "`$name`=:$name";
            $bind[":$name"] = ($_POST[$name] === '' ? null : $_POST[$name]);
        }

        if (!$set) {
            $this->flash('warning', 'Nothing to update.');
            $this->redirect("/admin/t/$table");
        }

        $sql = "UPDATE `$table` SET " . implode(',', $set) . " WHERE `$pk`=:pk";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);

        $this->flash('success', 'Row updated.');
        $this->redirect("/admin/t/$table");
    }

    public function destroy(array $params): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin');
        }

        $table = (string)($params['table'] ?? '');
        $id    = (string)($params['id'] ?? '');
        if (!$this->tableOk($table)) {
            $this->flash('danger', 'Unknown table.');
            $this->redirect('/admin/tables');
        }

        $pdo = DB::conn();
        $pk  = $this->pk($table);
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk`=:id");
        $stmt->execute([':id' => $id]);

        $this->flash('success', 'Row deleted.');
        $this->redirect("/admin/t/$table");
    }

    // ===================== Candidates: LIST =====================
    public function candidatesIndex(array $params = []): void
    {
        $this->requireAdmin();
        $pdo   = \App\Core\DB::conn();

        $q      = trim((string)($_GET['q'] ?? ''));
        $per    = max(1, min(100, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per;

        $where = [];
        $bind  = [];
        if ($q !== '') {
            $where[] = "(full_name LIKE :q OR email LIKE :q OR phone_number LIKE :q OR country LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        $wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $pdo->prepare("SELECT COUNT(*) FROM candidates $wsql");
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        $sql = "SELECT candidate_id, full_name, email, phone_number, country,
                   premium_badge, verified_status, created_at, updated_at
            FROM candidates
            $wsql
            ORDER BY created_at DESC, candidate_id DESC
            LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit', $per, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $per));

        $root    = dirname(__DIR__, 2);
        $title   = 'Admin — Candidates';
        $viewFile = $root . '/app/Views/admin/candidates/index.php';
        $csrf    = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    // ===================== Candidates: CREATE =====================
    public function candidatesCreate(array $params = []): void
    {
        $this->requireAdmin();
        $root = dirname(__DIR__, 2);
        $title = 'Add Candidate';
        $viewFile = $root . '/app/Views/admin/candidates/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $candidate = null;
        require $root . '/app/Views/layout.php';
    }

    public function candidatesStore(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/candidates');
        }

        $pdo  = \App\Core\DB::conn();
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone_number'] ?? ''));
        $country = trim((string)($_POST['country'] ?? 'Malaysia'));
        $password = (string)($_POST['password'] ?? '');
        $premium  = isset($_POST['premium_badge']) ? 1 : 0;
        $verified = isset($_POST['verified_status']) ? 1 : 0;

        $err = [];
        if ($name === '') $err['full_name'] = 'Full name is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Valid email required.';
        if ($password === '' || strlen($password) < 6) $err['password'] = 'Min 6 characters.';
        if (!$err && $this->emailExistsAny($pdo, $email)) $err['email'] = 'Email already in use by another user.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/candidates/create');
        }

        $now  = date('Y-m-d H:i:s');
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = $pdo->prepare("INSERT INTO candidates
            (full_name,email,password_hash,phone_number,country,premium_badge,verified_status,created_at,updated_at)
            VALUES (:n,:e,:p,:ph,:c,:pb,:vs,:ca,:ua)");
        $st->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $hash,
            ':ph' => $phone ?: null,
            ':c' => $country ?: 'Malaysia',
            ':pb' => $premium,
            ':vs' => $verified,
            ':ca' => $now,
            ':ua' => $now
        ]);

        $this->flash('success', 'Candidate created.');
        $this->redirect('/admin/candidates');
    }

    // ===================== Candidates: EDIT/UPDATE =====================
    public function candidatesEdit(array $params = []): void
    {
        $this->requireAdmin();
        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $candidate = $st->fetch();
        if (!$candidate) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/candidates');
        }

        $root = dirname(__DIR__, 2);
        $title = 'Edit Candidate — ' . $candidate['full_name'];
        $viewFile = $root . '/app/Views/admin/candidates/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function candidatesUpdate(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/candidates');
        }

        $id   = (int)($params['id'] ?? 0);
        $pdo  = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $existing = $st->fetch();
        if (!$existing) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/candidates');
        }

        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone_number'] ?? ''));
        $country = trim((string)($_POST['country'] ?? 'Malaysia'));
        $password = (string)($_POST['password'] ?? '');
        $premium  = isset($_POST['premium_badge']) ? 1 : 0;
        $verified = isset($_POST['verified_status']) ? 1 : 0;

        $err = [];
        if ($name === '') $err['full_name'] = 'Full name is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Valid email required.';
        if ($this->emailExistsAny($pdo, $email, $id)) $err['email'] = 'Email already in use by another user.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/candidates/' . $id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        if ($password !== '') {
            if (strlen($password) < 6) {
                $this->setErrors(['password' => 'Min 6 characters.']);
                $this->setOld($_POST);
                $this->redirect('/admin/candidates/' . $id . '/edit');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE candidates SET
                    full_name=:n,email=:e,password_hash=:p,phone_number=:ph,country=:c,
                    premium_badge=:pb,verified_status=:vs,updated_at=:ua
                WHERE candidate_id=:id";
            $args = [
                ':n' => $name,
                ':e' => $email,
                ':p' => $hash,
                ':ph' => $phone ?: null,
                ':c' => $country ?: 'Malaysia',
                ':pb' => $premium,
                ':vs' => $verified,
                ':ua' => $now,
                ':id' => $id
            ];
        } else {
            $sql = "UPDATE candidates SET
                    full_name=:n,email=:e,phone_number=:ph,country=:c,
                    premium_badge=:pb,verified_status=:vs,updated_at=:ua
                WHERE candidate_id=:id";
            $args = [
                ':n' => $name,
                ':e' => $email,
                ':ph' => $phone ?: null,
                ':c' => $country ?: 'Malaysia',
                ':pb' => $premium,
                ':vs' => $verified,
                ':ua' => $now,
                ':id' => $id
            ];
        }
        $up = $pdo->prepare($sql);
        $up->execute($args);

        $this->flash('success', 'Candidate updated.');
        $this->redirect('/admin/candidates');
    }

    // ===================== Candidates: DELETE =====================
    public function candidatesDestroy(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/candidates');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        // Optional: remove related rows if no FK cascade
        $pdo->prepare("DELETE FROM applications WHERE candidate_id=:id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM candidates WHERE candidate_id=:id")->execute([':id' => $id]);

        $this->flash('success', 'Candidate deleted.');
        $this->redirect('/admin/candidates');
    }

    // ===================== Candidates: BULK =====================
    public function candidatesBulk(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/candidates');
        }

        $ids  = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        $ids  = array_unique(array_map('intval', $ids));
        $act  = trim((string)($_POST['bulk_action'] ?? ''));

        if (!$ids || $act === '') {
            $this->flash('danger', 'Choose some rows and an action.');
            $this->redirect('/admin/candidates');
        }

        $pdo = \App\Core\DB::conn();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        if ($act === 'verify_on' || $act === 'verify_off') {
            $val = $act === 'verify_on' ? 1 : 0;
            $st  = $pdo->prepare("UPDATE candidates SET verified_status=$val, updated_at=NOW() WHERE candidate_id IN ($in)");
            $st->execute($ids);
            $this->flash('success', 'Verification updated for selected candidates.');
        } elseif ($act === 'premium_on' || $act === 'premium_off') {
            $val = $act === 'premium_on' ? 1 : 0;
            $st  = $pdo->prepare("UPDATE candidates SET premium_badge=$val, updated_at=NOW() WHERE candidate_id IN ($in)");
            $st->execute($ids);
            $this->flash('success', 'Premium badge updated for selected candidates.');
        } elseif ($act === 'delete') {
            // Optional: remove applications first
            $pdo->prepare("DELETE FROM applications WHERE candidate_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM candidates WHERE candidate_id IN ($in)")->execute($ids);
            $this->flash('success', 'Selected candidates deleted.');
        } else {
            $this->flash('danger', 'Unknown action.');
        }

        $this->redirect('/admin/candidates');
    }

    // ======= (Optional) helper to fetch recruiters for dropdowns =======
    private function allRecruiters(): array
    {
        $pdo = \App\Core\DB::conn();
        return $pdo->query("SELECT recruiter_id, full_name, email FROM recruiters ORDER BY full_name ASC")->fetchAll() ?: [];
    }

    // ===================== Employers: LIST =====================
    public function employersIndex(array $params = []): void
    {
        $this->requireAdmin();
        $pdo   = \App\Core\DB::conn();

        $q      = trim((string)($_GET['q'] ?? ''));
        $type   = trim((string)($_GET['type'] ?? '')); // '', 'account', 'client'
        $per    = max(1, min(100, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per;

        $where = [];
        $bind  = [];

        if ($q !== '') {
            $where[] = "(e.company_name LIKE :q OR e.email LIKE :q OR e.industry LIKE :q OR e.location LIKE :q OR e.contact_person_name LIKE :q OR e.contact_number LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        if ($type === 'account') {
            $where[] = "e.is_client_company = 0";
        } elseif ($type === 'client') {
            $where[] = "e.is_client_company = 1";
        }

        $wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sqlCount = "SELECT COUNT(*) FROM employers e $wsql";
        $st = $pdo->prepare($sqlCount);
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        $sql = "
      SELECT e.employer_id, e.company_name, e.email, e.industry, e.location,
             e.contact_person_name, e.contact_number, e.is_client_company, e.created_by_recruiter_id,
             e.company_logo, e.updated_at,
             (SELECT COUNT(*) FROM job_postings jp WHERE jp.company_id = e.employer_id) AS jobs_count,
             r.full_name AS recruiter_name
      FROM employers e
      LEFT JOIN recruiters r ON r.recruiter_id = e.created_by_recruiter_id
      $wsql
      ORDER BY e.created_at DESC, e.employer_id DESC
      LIMIT :limit OFFSET :offset
    ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit', $per, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $per));

        $root     = dirname(__DIR__, 2);
        $title    = 'Admin — Employers';
        $viewFile = $root . '/app/Views/admin/employers/index.php';
        $csrf     = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    // ===================== Employers: CREATE =====================
    public function employersCreate(array $params = []): void
    {
        $this->requireAdmin();
        $root = dirname(__DIR__, 2);
        $title = 'Add Employer';
        $viewFile = $root . '/app/Views/admin/employers/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $employer = null;
        $recruiters = $this->allRecruiters();
        require $root . '/app/Views/layout.php';
    }

    public function employersStore(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/employers');
        }

        $pdo = \App\Core\DB::conn();

        $company = trim((string)($_POST['company_name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $industry = trim((string)($_POST['industry'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $cpName  = trim((string)($_POST['contact_person_name'] ?? ''));
        $cpPhone = trim((string)($_POST['contact_number'] ?? ''));
        $desc    = trim((string)($_POST['company_description'] ?? ''));
        $isClient = isset($_POST['is_client_company']) ? 1 : 0;
        $rid     = (int)($_POST['created_by_recruiter_id'] ?? 0);

        $err = [];
        if ($company === '') $err['company_name'] = 'Company name is required.';

        // When email is provided, it must be unique and password required.
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
            if (!$err && $this->emailExistsAny($pdo, $email)) $err['email'] = 'Email already in use.';
            if ($password === '' || strlen($password) < 6) $err['password'] = 'Password min 6 characters.';
        }

        if ($isClient && $rid <= 0) {
            $err['created_by_recruiter_id'] = 'Choose a recruiter for client companies.';
        }

        // Logo upload (optional)
        $logoUrl = null;
        if (!empty($_FILES['company_logo']['name'])) {
            $up = $_FILES['company_logo'];
            if ($up['error'] === UPLOAD_ERR_OK) {
                $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                $mime = mime_content_type($up['tmp_name']) ?: '';
                if (!isset($ok[$mime])) $err['company_logo'] = 'Only JPG/PNG allowed.';
                elseif ($up['size'] > 2 * 1024 * 1024) $err['company_logo'] = 'Max 2MB.';
                else {
                    $root = dirname(__DIR__, 2);
                    $dir  = $root . '/public/assets/uploads/company_logos';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $ext  = $ok[$mime];
                    $name = 'logo_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $name;
                    if (!move_uploaded_file($up['tmp_name'], $dest)) $err['company_logo'] = 'Upload failed.';
                    else $logoUrl = '/assets/uploads/company_logos/' . $name;
                }
            } else {
                $err['company_logo'] = 'Upload error.';
            }
        }

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/employers/create');
        }

        $now = date('Y-m-d H:i:s');
        $columns = "company_name, industry, location, contact_person_name, contact_number, company_description, company_logo, is_client_company, created_by_recruiter_id, created_at, updated_at";
        $params  = [':cn' => $company, ':ind' => $industry ?: null, ':loc' => $location ?: null, ':cp' => $cpName ?: null, ':cc' => $cpPhone ?: null, ':desc' => $desc ?: null, ':logo' => $logoUrl, ':ccf' => $isClient, ':rid' => $isClient ? $rid : null, ':ca' => $now, ':ua' => $now];
        $values  = ":cn,:ind,:loc,:cp,:cc,:desc,:logo,:ccf,:rid,:ca,:ua";

        if ($email !== '') {
            $columns = "company_name, email, password_hash, industry, location, contact_person_name, contact_number, company_description, company_logo, is_client_company, created_by_recruiter_id, created_at, updated_at";
            $values  = ":cn,:em,:phash,:ind,:loc,:cp,:cc,:desc,:logo,:ccf,:rid,:ca,:ua";
            $params[':em']   = $email;
            $params[':phash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql = "INSERT INTO employers ($columns) VALUES ($values)";
        $st  = $pdo->prepare($sql);
        $st->execute($params);

        $this->flash('success', 'Employer created.');
        $this->redirect('/admin/employers');
    }

    // ===================== Employers: EDIT/UPDATE =====================
    public function employersEdit(array $params = []): void
    {
        $this->requireAdmin();
        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM employers WHERE employer_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $employer = $st->fetch();
        if (!$employer) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/employers');
        }

        $root = dirname(__DIR__, 2);
        $title = 'Edit Employer — ' . ($employer['company_name'] ?? ('#' . $id));
        $viewFile = $root . '/app/Views/admin/employers/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $recruiters = $this->allRecruiters();
        require $root . '/app/Views/layout.php';
    }

    public function employersUpdate(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/employers');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM employers WHERE employer_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $existing = $st->fetch();
        if (!$existing) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/employers');
        }

        $company = trim((string)($_POST['company_name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $industry = trim((string)($_POST['industry'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $cpName  = trim((string)($_POST['contact_person_name'] ?? ''));
        $cpPhone = trim((string)($_POST['contact_number'] ?? ''));
        $desc    = trim((string)($_POST['company_description'] ?? ''));
        $isClient = isset($_POST['is_client_company']) ? 1 : 0;
        $rid     = (int)($_POST['created_by_recruiter_id'] ?? 0);

        $err = [];
        if ($company === '') $err['company_name'] = 'Company name is required.';

        // Email can be cleared; if provided, must be unique across all users (ignore this employer)
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
            if (!$err) {
                // emailExistsAny doesn’t know which employer to ignore, so do manual checks:
                // 1) candidates
                $x = $pdo->prepare("SELECT 1 FROM candidates WHERE email=:e LIMIT 1");
                $x->execute([':e' => $email]);
                if ($x->fetchColumn()) $err['email'] = 'Email already used by a candidate.';
                // 2) recruiters
                if (!$err) {
                    $x = $pdo->prepare("SELECT 1 FROM recruiters WHERE email=:e LIMIT 1");
                    $x->execute([':e' => $email]);
                    if ($x->fetchColumn()) $err['email'] = 'Email already used by a recruiter.';
                }
                // 3) employers (other than me)
                if (!$err) {
                    $x = $pdo->prepare("SELECT employer_id FROM employers WHERE email=:e LIMIT 1");
                    $x->execute([':e' => $email]);
                    $row = $x->fetch();
                    if ($row && (int)$row['employer_id'] !== $id) $err['email'] = 'Email already used by another employer.';
                }
            }
        }

        if ($isClient && $rid <= 0) {
            $err['created_by_recruiter_id'] = 'Choose a recruiter for client companies.';
        }

        // Logo (optional)
        $logoUrl = $existing['company_logo'] ?? null;
        if (!empty($_FILES['company_logo']['name'])) {
            $up = $_FILES['company_logo'];
            if ($up['error'] === UPLOAD_ERR_OK) {
                $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                $mime = mime_content_type($up['tmp_name']) ?: '';
                if (!isset($ok[$mime])) $err['company_logo'] = 'Only JPG/PNG allowed.';
                elseif ($up['size'] > 2 * 1024 * 1024) $err['company_logo'] = 'Max 2MB.';
                else {
                    $root = dirname(__DIR__, 2);
                    $dir  = $root . '/public/assets/uploads/company_logos';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $ext  = $ok[$mime];
                    $name = 'logo_' . $id . '_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $name;
                    if (!move_uploaded_file($up['tmp_name'], $dest)) $err['company_logo'] = 'Upload failed.';
                    else $logoUrl = '/assets/uploads/company_logos/' . $name;
                }
            } else {
                $err['company_logo'] = 'Upload error.';
            }
        }

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/employers/' . $id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        // Build update query dynamically (password hash only if provided; email can be null)
        $sql = "UPDATE employers SET
              company_name=:cn, email=:em, industry=:ind, location=:loc,
              contact_person_name=:cp, contact_number=:cc, company_description=:desc,
              company_logo=:logo, is_client_company=:ccf, created_by_recruiter_id=:rid, updated_at=:ua";
        $args = [
            ':cn' => $company,
            ':em' => ($email !== '' ? $email : null),
            ':ind' => $industry ?: null,
            ':loc' => $location ?: null,
            ':cp' => $cpName ?: null,
            ':cc' => $cpPhone ?: null,
            ':desc' => $desc ?: null,
            ':logo' => $logoUrl,
            ':ccf' => $isClient,
            ':rid' => $isClient ? $rid : null,
            ':ua' => $now,
            ':id' => $id
        ];

        if ($password !== '') {
            if (strlen($password) < 6) {
                $this->setErrors(['password' => 'Password min 6 characters.']);
                $this->setOld($_POST);
                $this->redirect('/admin/employers/' . $id . '/edit');
            }
            $sql .= ", password_hash=:phash";
            $args[':phash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE employer_id=:id";
        $up = $pdo->prepare($sql);
        $up->execute($args);

        $this->flash('success', 'Employer updated.');
        $this->redirect('/admin/employers');
    }

    // ===================== Employers: DELETE =====================
    public function employersDestroy(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/employers');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        // Optional cleanup
        $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE company_id=:id)")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE company_id=:id)")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM job_postings WHERE company_id=:id")->execute([':id' => $id]);

        $pdo->prepare("DELETE FROM employers WHERE employer_id=:id")->execute([':id' => $id]);

        $this->flash('success', 'Employer deleted.');
        $this->redirect('/admin/employers');
    }

    // ===================== Employers: BULK =====================
    public function employersBulk(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/employers');
        }

        $ids = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        $ids = array_unique(array_map('intval', $ids));
        $act = trim((string)($_POST['bulk_action'] ?? ''));

        if (!$ids || $act === '') {
            $this->flash('danger', 'Choose some rows and an action.');
            $this->redirect('/admin/employers');
        }

        $pdo = \App\Core\DB::conn();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        if ($act === 'client_on' || $act === 'client_off') {
            $val = $act === 'client_on' ? 1 : 0;
            $st  = $pdo->prepare("UPDATE employers SET is_client_company=$val, updated_at=NOW() WHERE employer_id IN ($in)");
            $st->execute($ids);
            $this->flash('success', 'Client-company flag updated.');
        } elseif ($act === 'delete') {
            // cleanup children
            $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE company_id IN ($in))")->execute($ids);
            $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE company_id IN ($in))")->execute($ids);
            $pdo->prepare("DELETE FROM job_postings WHERE company_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM employers WHERE employer_id IN ($in)")->execute($ids);
            $this->flash('success', 'Selected employers deleted.');
        } else {
            $this->flash('danger', 'Unknown action.');
        }

        $this->redirect('/admin/employers');
    }

    // ===================== Recruiters: LIST =====================
    public function recruitersIndex(array $params = []): void
    {
        $this->requireAdmin();
        $pdo   = \App\Core\DB::conn();

        $q      = trim((string)($_GET['q'] ?? ''));
        $per    = max(1, min(100, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per;

        $where = [];
        $bind  = [];

        if ($q !== '') {
            $where[] = "(r.full_name LIKE :q OR r.email LIKE :q OR r.agency_name LIKE :q OR r.location LIKE :q OR r.contact_number LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        $wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $pdo->prepare("SELECT COUNT(*) FROM recruiters r $wsql");
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        $sql = "
      SELECT r.recruiter_id, r.full_name, r.email, r.agency_name, r.contact_number, r.location, r.updated_at,
             (SELECT COUNT(*) FROM employers e WHERE e.is_client_company=1 AND e.created_by_recruiter_id=r.recruiter_id) AS client_companies,
             (SELECT COUNT(*) FROM job_postings jp WHERE jp.recruiter_id=r.recruiter_id) AS jobs_count
      FROM recruiters r
      $wsql
      ORDER BY r.created_at DESC, r.recruiter_id DESC
      LIMIT :limit OFFSET :offset
    ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit', $per, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $pages = max(1, (int)ceil($total / $per));

        $root     = dirname(__DIR__, 2);
        $title    = 'Admin — Recruiters';
        $viewFile = $root . '/app/Views/admin/recruiters/index.php';
        $csrf     = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    // ===================== Recruiters: CREATE =====================
    public function recruitersCreate(array $params = []): void
    {
        $this->requireAdmin();
        $root = dirname(__DIR__, 2);
        $title = 'Add Recruiter';
        $viewFile = $root . '/app/Views/admin/recruiters/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $recruiter = null;
        require $root . '/app/Views/layout.php';
    }

    public function recruitersStore(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/recruiters');
        }

        $pdo = \App\Core\DB::conn();

        $name   = trim((string)($_POST['full_name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass   = (string)($_POST['password'] ?? '');
        $agency = trim((string)($_POST['agency_name'] ?? ''));
        $phone  = trim((string)($_POST['contact_number'] ?? ''));
        $loc    = trim((string)($_POST['location'] ?? ''));

        $err = [];
        if ($name === '') $err['full_name'] = 'Full name is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Valid email required.';
        if (!$err && $this->emailExistsAny($pdo, $email)) $err['email'] = 'Email already in use.';
        if ($pass === '' || strlen($pass) < 6) $err['password'] = 'Password min 6 characters.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/recruiters/create');
        }

        $now  = date('Y-m-d H:i:s');
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $st = $pdo->prepare("INSERT INTO recruiters (full_name,email,password_hash,agency_name,contact_number,location,created_at,updated_at)
                         VALUES (:n,:e,:p,:a,:c,:l,:ca,:ua)");
        $st->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $hash,
            ':a' => $agency ?: null,
            ':c' => $phone ?: null,
            ':l' => $loc ?: null,
            ':ca' => $now,
            ':ua' => $now
        ]);

        $this->flash('success', 'Recruiter created.');
        $this->redirect('/admin/recruiters');
    }

    // ===================== Recruiters: EDIT/UPDATE =====================
    public function recruitersEdit(array $params = []): void
    {
        $this->requireAdmin();
        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM recruiters WHERE recruiter_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $recruiter = $st->fetch();
        if (!$recruiter) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/recruiters');
        }

        $root = dirname(__DIR__, 2);
        $title = 'Edit Recruiter — ' . ($recruiter['full_name'] ?? ('#' . $id));
        $viewFile = $root . '/app/Views/admin/recruiters/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function recruitersUpdate(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/recruiters');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM recruiters WHERE recruiter_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $existing = $st->fetch();
        if (!$existing) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/recruiters');
        }

        $name   = trim((string)($_POST['full_name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass   = (string)($_POST['password'] ?? '');
        $agency = trim((string)($_POST['agency_name'] ?? ''));
        $phone  = trim((string)($_POST['contact_number'] ?? ''));
        $loc    = trim((string)($_POST['location'] ?? ''));

        $err = [];
        if ($name === '') $err['full_name'] = 'Full name is required.';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err['email'] = 'Valid email required.';
        } else {
            // Ensure uniqueness across all tables, excluding this recruiter
            $x = $pdo->prepare("SELECT 1 FROM candidates WHERE email=:e LIMIT 1");
            $x->execute([':e' => $email]);
            if ($x->fetchColumn()) $err['email'] = 'Email already used by a candidate.';
            if (!$err) {
                $x = $pdo->prepare("SELECT 1 FROM employers WHERE email=:e LIMIT 1");
                $x->execute([':e' => $email]);
                if ($x->fetchColumn()) $err['email'] = 'Email already used by an employer.';
            }
            if (!$err) {
                $x = $pdo->prepare("SELECT recruiter_id FROM recruiters WHERE email=:e LIMIT 1");
                $x->execute([':e' => $email]);
                $row = $x->fetch();
                if ($row && (int)$row['recruiter_id'] !== $id) $err['email'] = 'Email already used by another recruiter.';
            }
        }

        if ($pass !== '' && strlen($pass) < 6) $err['password'] = 'Password min 6 characters.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/recruiters/' . $id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE recruiters SET full_name=:n, email=:e, agency_name=:a, contact_number=:c, location=:l, updated_at=:ua";
        $args = [':n' => $name, ':e' => $email, ':a' => $agency ?: null, ':c' => $phone ?: null, ':l' => $loc ?: null, ':ua' => $now, ':id' => $id];

        if ($pass !== '') {
            $sql .= ", password_hash=:p";
            $args[':p'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE recruiter_id=:id";
        $up = $pdo->prepare($sql);
        $up->execute($args);

        $this->flash('success', 'Recruiter updated.');
        $this->redirect('/admin/recruiters');
    }

    // ===================== Recruiters: DELETE =====================
    public function recruitersDestroy(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/recruiters');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        // Delete recruiter’s job postings (+ child tables)
        $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE recruiter_id=:rid)")
            ->execute([':rid' => $id]);
        $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE recruiter_id=:rid)")
            ->execute([':rid' => $id]);
        $pdo->prepare("DELETE FROM job_postings WHERE recruiter_id=:rid")->execute([':rid' => $id]);

        // Delete client companies owned by recruiter (and their jobs/apps for safety)
        // First, jobs under those client companies
        $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN (
                      SELECT jp.job_posting_id FROM job_postings jp
                      WHERE jp.company_id IN (SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id=:rid)
                    )")->execute([':rid' => $id]);
        $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN (
                      SELECT jp.job_posting_id FROM job_postings jp
                      WHERE jp.company_id IN (SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id=:rid)
                    )")->execute([':rid' => $id]);
        $pdo->prepare("DELETE FROM job_postings WHERE company_id IN (
                      SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id=:rid
                    )")->execute([':rid' => $id]);
        $pdo->prepare("DELETE FROM employers WHERE is_client_company=1 AND created_by_recruiter_id=:rid")
            ->execute([':rid' => $id]);

        // Finally, the recruiter
        $pdo->prepare("DELETE FROM recruiters WHERE recruiter_id=:id")->execute([':id' => $id]);

        $this->flash('success', 'Recruiter deleted.');
        $this->redirect('/admin/recruiters');
    }

    // ===================== Recruiters: BULK =====================
    public function recruitersBulk(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/recruiters');
        }

        $ids = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        $ids = array_unique(array_map('intval', $ids));
        $act = trim((string)($_POST['bulk_action'] ?? ''));

        if (!$ids || $act === '') {
            $this->flash('danger', 'Choose some rows and an action.');
            $this->redirect('/admin/recruiters');
        }

        $pdo = \App\Core\DB::conn();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        if ($act === 'delete') {
            // Delete recruiter-linked job trees
            $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE recruiter_id IN ($in))")->execute($ids);
            $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN (SELECT job_posting_id FROM job_postings WHERE recruiter_id IN ($in))")->execute($ids);
            $pdo->prepare("DELETE FROM job_postings WHERE recruiter_id IN ($in)")->execute($ids);

            // Delete client companies under those recruiters (+ their job trees)
            $pdo->prepare("
            DELETE FROM job_micro_questions WHERE job_posting_id IN (
                SELECT jp.job_posting_id FROM job_postings jp
                WHERE jp.company_id IN (
                    SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id IN ($in)
                )
            )
        ")->execute($ids);
            $pdo->prepare("
            DELETE FROM applications WHERE job_posting_id IN (
                SELECT jp.job_posting_id FROM job_postings jp
                WHERE jp.company_id IN (
                    SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id IN ($in)
                )
            )
        ")->execute($ids);
            $pdo->prepare("
            DELETE FROM job_postings WHERE company_id IN (
                SELECT employer_id FROM employers WHERE is_client_company=1 AND created_by_recruiter_id IN ($in)
            )
        ")->execute($ids);
            $pdo->prepare("DELETE FROM employers WHERE is_client_company=1 AND created_by_recruiter_id IN ($in)")->execute($ids);

            // Finally recruiters
            $pdo->prepare("DELETE FROM recruiters WHERE recruiter_id IN ($in)")->execute($ids);

            $this->flash('success', 'Selected recruiters deleted.');
        } else {
            $this->flash('danger', 'Unknown action.');
        }

        $this->redirect('/admin/recruiters');
    }

    /* ================== Admin: JOBS ================== */

    private function jobStatusOptions(): array
    {
        return ['Open', 'Paused', 'Suspended', 'Fulfilled', 'Closed', 'Deleted'];
    }

    /** LIST + search/filter/pagination */
    public function jobsIndex(array $params = []): void
    {
        $this->requireAdmin();
        $pdo   = \App\Core\DB::conn();

        $q        = trim((string)($_GET['q'] ?? ''));
        $status   = trim((string)($_GET['status'] ?? ''));
        $type     = trim((string)($_GET['type'] ?? '')); // employment_type
        $company  = (string)($_GET['company_id'] ?? '');
        $recruiter = (string)($_GET['recruiter_id'] ?? '');

        $per    = max(1, min(100, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per;

        $where = [];
        $bind  = [];

        if ($q !== '') {
            $where[] = "(jp.job_title LIKE :q OR jp.job_description LIKE :q OR e.company_name LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        if ($status !== '' && in_array($status, $this->jobStatusOptions(), true)) {
            $where[] = "jp.status = :st";
            $bind[':st'] = $status;
        }
        if ($type !== '') {
            $where[] = "jp.employment_type = :tp";
            $bind[':tp'] = $type;
        }
        if (ctype_digit($company)) {
            $where[] = "jp.company_id = :cid";
            $bind[':cid'] = (int)$company;
        }
        if (ctype_digit($recruiter)) {
            $where[] = "jp.recruiter_id = :rid";
            $bind[':rid'] = (int)$recruiter;
        }

        $wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $pdo->prepare("SELECT COUNT(*) 
                         FROM job_postings jp 
                         JOIN employers e ON e.employer_id = jp.company_id
                         LEFT JOIN recruiters r ON r.recruiter_id = jp.recruiter_id
                         $wsql");
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        $sql = "
      SELECT jp.*, e.company_name, r.full_name AS recruiter_name,
             (SELECT COUNT(*) FROM applications a WHERE a.job_posting_id = jp.job_posting_id) AS applicants_count
      FROM job_postings jp
      JOIN employers e ON e.employer_id = jp.company_id
      LEFT JOIN recruiters r ON r.recruiter_id = jp.recruiter_id
      $wsql
      ORDER BY jp.date_posted DESC, jp.job_posting_id DESC
      LIMIT :limit OFFSET :offset
    ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $per, \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        // Dropdown data
        $companies = $pdo->query("SELECT employer_id, company_name FROM employers ORDER BY company_name ASC")->fetchAll() ?: [];
        $recruiters = $pdo->query("SELECT recruiter_id, full_name FROM recruiters ORDER BY full_name ASC")->fetchAll() ?: [];

        $pages   = max(1, (int)ceil($total / $per));
        $root    = dirname(__DIR__, 2);
        $title   = 'Admin — Jobs';
        $viewFile = $root . '/app/Views/admin/jobs/index.php';
        $csrf    = $this->csrf();
        $statuses = $this->jobStatusOptions();
        require $root . '/app/Views/layout.php';
    }

    /** CREATE form */
    public function jobsCreate(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = \App\Core\DB::conn();

        $companies = $pdo->query("SELECT employer_id, company_name FROM employers ORDER BY company_name ASC")->fetchAll() ?: [];
        $recruiters = $pdo->query("SELECT recruiter_id, full_name FROM recruiters ORDER BY full_name ASC")->fetchAll() ?: [];
        $qbank     = $pdo->query("SELECT id, prompt FROM micro_questions WHERE active=1 ORDER BY id ASC")->fetchAll() ?: [];

        $root = dirname(__DIR__, 2);
        $title = 'Create Job';
        $viewFile = $root . '/app/Views/admin/jobs/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $job    = null; // create mode
        $statuses = $this->jobStatusOptions();
        require $root . '/app/Views/layout.php';
    }

    /** STORE */
    public function jobsStore(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/jobs');
        }

        $pdo = \App\Core\DB::conn();

        $companyId  = (int)($_POST['company_id'] ?? 0);
        $recruiterId = (string)($_POST['recruiter_id'] ?? '');
        $recruiterId = ctype_digit($recruiterId) ? (int)$recruiterId : null;

        $title   = trim((string)($_POST['job_title'] ?? ''));
        $desc    = trim((string)($_POST['job_description'] ?? ''));
        $loc     = trim((string)($_POST['job_location'] ?? ''));
        $langs   = trim((string)($_POST['job_languages'] ?? ''));
        $salary  = (string)($_POST['salary'] ?? '');
        $etype   = trim((string)($_POST['employment_type'] ?? 'Full-time'));
        $status  = trim((string)($_POST['status'] ?? 'Open'));

        $chosen = array_values(array_filter((array)($_POST['mi_questions'] ?? []), fn($v) => ctype_digit((string)$v)));
        $chosen = array_unique(array_map('intval', $chosen));

        $err = [];
        if ($companyId <= 0) $err['company_id'] = 'Company is required.';
        if ($title === '')   $err['job_title'] = 'Job title is required.';
        if ($desc  === '')   $err['job_description'] = 'Description is required.';
        if ($salary !== '' && !is_numeric($salary)) $err['salary'] = 'Salary must be numeric.';
        if (!in_array($status, $this->jobStatusOptions(), true)) $err['status'] = 'Invalid status.';
        if (count($chosen) !== 3) $err['mi_questions'] = 'Pick exactly 3 micro interview questions.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/jobs/create');
        }

        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("INSERT INTO job_postings
          (company_id, recruiter_id, job_title, job_description, job_requirements, job_location, job_languages,
           employment_type, salary_range_min, salary_range_max, application_deadline, date_posted, status,
           number_of_positions, required_experience, education_level, created_at, updated_at)
         VALUES
          (:cid,:rid,:title,:desc,NULL,:loc,:langs,:etype,:smin,NULL,NULL,:posted,:status,1,NULL,NULL,:ca,:ua)");
            $st->execute([
                ':cid'    => $companyId,
                ':rid'    => $recruiterId,
                ':title'  => $title,
                ':desc'   => $desc,
                ':loc'    => $loc ?: null,
                ':langs'  => $langs ?: null,
                ':etype'  => $etype ?: 'Full-time',
                ':smin'   => ($salary === '' ? null : number_format((float)$salary, 2, '.', '')),
                ':posted' => $now,
                ':status' => $status,
                ':ca'     => $now,
                ':ua'     => $now,
            ]);

            $jobId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO job_micro_questions (job_posting_id, question_id) VALUES (:jid,:qid)");
            foreach ($chosen as $qid) $ins->execute([':jid' => $jobId, ':qid' => $qid]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not create job.');
            $this->redirect('/admin/jobs/create');
        }

        $this->flash('success', 'Job created.');
        $this->redirect('/admin/jobs');
    }

    /** EDIT */
    public function jobsEdit(array $params = []): void
    {
        $this->requireAdmin();
        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM job_postings WHERE job_posting_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $job = $st->fetch();
        if (!$job) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/jobs');
        }

        $companies = $pdo->query("SELECT employer_id, company_name FROM employers ORDER BY company_name ASC")->fetchAll() ?: [];
        $recruiters = $pdo->query("SELECT recruiter_id, full_name FROM recruiters ORDER BY full_name ASC")->fetchAll() ?: [];
        $qbank     = $pdo->query("SELECT id, prompt FROM micro_questions WHERE active=1 ORDER BY id ASC")->fetchAll() ?: [];

        $sel = $pdo->prepare("SELECT question_id FROM job_micro_questions WHERE job_posting_id=:id ORDER BY question_id ASC");
        $sel->execute([':id' => $id]);
        $attached = array_map('intval', array_column($sel->fetchAll() ?: [], 'question_id'));

        $root = dirname(__DIR__, 2);
        $title = 'Edit Job — ' . ($job['job_title'] ?? ('#' . $id));
        $viewFile = $root . '/app/Views/admin/jobs/form.php';
        $errors = $this->takeErrors();
        $old    = $this->takeOld();
        $csrf   = $this->csrf();
        $statuses = $this->jobStatusOptions();
        require $root . '/app/Views/layout.php';
    }

    /** UPDATE */
    public function jobsUpdate(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/jobs');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT * FROM job_postings WHERE job_posting_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $job = $st->fetch();
        if (!$job) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/jobs');
        }

        $companyId  = (int)($_POST['company_id'] ?? 0);
        $recruiterId = (string)($_POST['recruiter_id'] ?? '');
        $recruiterId = ctype_digit($recruiterId) ? (int)$recruiterId : null;

        $title   = trim((string)($_POST['job_title'] ?? ''));
        $desc    = trim((string)($_POST['job_description'] ?? ''));
        $loc     = trim((string)($_POST['job_location'] ?? ''));
        $langs   = trim((string)($_POST['job_languages'] ?? ''));
        $salary  = (string)($_POST['salary'] ?? '');
        $etype   = trim((string)($_POST['employment_type'] ?? 'Full-time'));
        $status  = trim((string)($_POST['status'] ?? 'Open'));

        $chosen = array_values(array_filter((array)($_POST['mi_questions'] ?? []), fn($v) => ctype_digit((string)$v)));
        $chosen = array_unique(array_map('intval', $chosen));

        $err = [];
        if ($companyId <= 0) $err['company_id'] = 'Company is required.';
        if ($title === '')   $err['job_title'] = 'Job title is required.';
        if ($desc  === '')   $err['job_description'] = 'Description is required.';
        if ($salary !== '' && !is_numeric($salary)) $err['salary'] = 'Salary must be numeric.';
        if (!in_array($status, $this->jobStatusOptions(), true)) $err['status'] = 'Invalid status.';
        if (count($chosen) !== 3) $err['mi_questions'] = 'Pick exactly 3 micro interview questions.';

        if ($err) {
            $this->setErrors($err);
            $this->setOld($_POST);
            $this->redirect('/admin/jobs/' . $id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare("
          UPDATE job_postings SET
            company_id=:cid, recruiter_id=:rid, job_title=:title, job_description=:desc,
            job_location=:loc, job_languages=:langs, employment_type=:etype,
            salary_range_min=:smin, status=:status, updated_at=:ua
          WHERE job_posting_id=:id
        ");
            $up->execute([
                ':cid' => $companyId,
                ':rid' => $recruiterId,
                ':title' => $title,
                ':desc' => $desc,
                ':loc' => $loc ?: null,
                ':langs' => $langs ?: null,
                ':etype' => $etype ?: 'Full-time',
                ':smin' => ($salary === '' ? null : number_format((float)$salary, 2, '.', '')),
                ':status' => $status,
                ':ua' => $now,
                ':id' => $id
            ]);

            $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id=:id")->execute([':id' => $id]);
            $ins = $pdo->prepare("INSERT INTO job_micro_questions (job_posting_id, question_id) VALUES (:jid,:qid)");
            foreach ($chosen as $qid) $ins->execute([':jid' => $id, ':qid' => $qid]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not update job.');
            $this->redirect('/admin/jobs/' . $id . '/edit');
        }

        $this->flash('success', 'Job updated.');
        $this->redirect('/admin/jobs');
    }

    /** DELETE (hard delete with cleanups) */
    public function jobsDestroy(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/jobs');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id=:id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM applications WHERE job_posting_id=:id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM job_postings WHERE job_posting_id=:id")->execute([':id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not delete job.');
            $this->redirect('/admin/jobs');
        }

        $this->flash('success', 'Job deleted.');
        $this->redirect('/admin/jobs');
    }

    /** BULK actions: set_status / delete */
    public function jobsBulk(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/jobs');
        }

        $ids = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        $ids = array_unique(array_map('intval', $ids));
        $act = trim((string)($_POST['bulk_action'] ?? ''));
        $newStatus = trim((string)($_POST['new_status'] ?? ''));

        if (!$ids || $act === '') {
            $this->flash('danger', 'Choose some rows and an action.');
            $this->redirect('/admin/jobs');
        }

        $pdo = \App\Core\DB::conn();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        if ($act === 'set_status') {
            if (!in_array($newStatus, $this->jobStatusOptions(), true)) {
                $this->flash('danger', 'Invalid status.');
                $this->redirect('/admin/jobs');
            }
            $args = $ids;
            $sql  = "UPDATE job_postings SET status=?, updated_at=NOW() WHERE job_posting_id IN ($in)";
            array_unshift($args, $newStatus);
            $st = $pdo->prepare($sql);
            $st->execute($args);
            $this->flash('success', 'Status updated for selected jobs.');
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM job_micro_questions WHERE job_posting_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM applications WHERE job_posting_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM job_postings WHERE job_posting_id IN ($in)")->execute($ids);
            $this->flash('success', 'Selected jobs deleted.');
        } else {
            $this->flash('danger', 'Unknown action.');
        }

        $this->redirect('/admin/jobs');
    }

    /* ================== Admin: Candidate Verifications ================== */

    public function verifIndex(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = \App\Core\DB::conn();

        $q      = trim((string)($_GET['q'] ?? ''));            // name/email
        $state  = trim((string)($_GET['status'] ?? ''));       // Pending/Approved/Rejected
        $per    = max(1, min(100, (int)($_GET['per'] ?? 20)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $per;

        $where = ["verification_doc_url IS NOT NULL"]; // only those who submitted
        $bind  = [];

        if ($q !== '') {
            $where[] = "(full_name LIKE :q OR email LIKE :q)";
            $bind[':q'] = "%$q%";
        }
        if (in_array($state, ['Pending', 'Approved', 'Rejected'], true)) {
            $where[] = "verification_state = :st";
            $bind[':st'] = $state;
        }
        $wsql = 'WHERE ' . implode(' AND ', $where);

        $st = $pdo->prepare("SELECT COUNT(*) FROM candidates $wsql");
        $st->execute($bind);
        $total = (int)$st->fetchColumn();

        $sql = "SELECT candidate_id, full_name, email, verification_doc_type, verification_doc_url,
                   verification_date, verification_state, verification_review_notes, verified_status
            FROM candidates
            $wsql
            ORDER BY verification_date DESC, candidate_id DESC
            LIMIT :lim OFFSET :off";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $per, \PDO::PARAM_INT);
        $st->bindValue(':off', $offset, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        $pages   = max(1, (int)ceil($total / $per));
        $root    = dirname(__DIR__, 2);
        $title   = 'Admin — Candidate Verifications';
        $viewFile = $root . '/app/Views/admin/verifications/index.php';
        $csrf    = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function verifShow(array $params = []): void
    {
        $this->requireAdmin();
        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("SELECT candidate_id, full_name, email,
                                verification_doc_type, verification_doc_url, verification_date,
                                verification_state, verification_review_notes
                         FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) {
            $this->flash('danger', 'Not found.');
            $this->redirect('/admin/verifications');
        }

        $root    = dirname(__DIR__, 2);
        $title   = 'Review Verification — ' . $row['full_name'];
        $viewFile = $root . '/app/Views/admin/verifications/show.php';
        $csrf    = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    public function verifApprove(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/verifications');
        }

        $id  = (int)($params['id'] ?? 0);
        $pdo = \App\Core\DB::conn();

        $st = $pdo->prepare("UPDATE candidates
                         SET verified_status=1,
                             verification_state='Approved',
                             verification_review_notes=NULL,
                             verification_reviewed_at=NOW(),
                             verification_reviewed_by=NULL,
                             updated_at=NOW()
                         WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);

        $this->flash('success', 'Verification approved.');
        $this->redirect('/admin/verifications');
    }

    public function verifReject(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/verifications');
        }

        $id    = (int)($params['id'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));

        $pdo = \App\Core\DB::conn();
        $st = $pdo->prepare("UPDATE candidates
                         SET verified_status=2,
                             verification_state='Rejected',
                             verification_review_notes=:n,
                             verification_reviewed_at=NOW(),
                             verification_reviewed_by=NULL,
                             updated_at=NOW()
                         WHERE candidate_id=:id LIMIT 1");
        $st->execute([':n' => $notes ?: null, ':id' => $id]);

        $this->flash('success', 'Verification rejected.');
        $this->redirect('/admin/verifications');
    }

    public function verifBulk(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/admin/verifications');
        }

        $ids = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        $ids = array_unique(array_map('intval', $ids));
        $act = trim((string)($_POST['bulk_action'] ?? '')); // approve|reject
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!$ids || !in_array($act, ['approve', 'reject'], true)) {
            $this->flash('danger', 'Select rows and a valid action.');
            $this->redirect('/admin/verifications');
        }

        $pdo = \App\Core\DB::conn();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        if ($act === 'approve') {
            $sql = "UPDATE candidates
                SET verified_status=1,
                    verification_state='Approved',
                    verification_review_notes=NULL,
                    verification_reviewed_at=NOW(),
                    verification_reviewed_by=NULL,
                    updated_at=NOW()
                WHERE candidate_id IN ($in)";
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $this->flash('success', 'Approved selected submissions.');
        } else {
            // reject
            $sql = "UPDATE candidates
                SET verified_status=0,
                    verification_state='Rejected',
                    verification_review_notes=?,
                    verification_reviewed_at=NOW(),
                    verification_reviewed_by=NULL,
                    updated_at=NOW()
                WHERE candidate_id IN ($in)";
            $st = $pdo->prepare($sql);
            $args = $ids;
            array_unshift($args, $notes ?: null);
            $st->execute($args);
            $this->flash('success', 'Rejected selected submissions.');
        }

        $this->redirect('/admin/verifications');
    }
}
