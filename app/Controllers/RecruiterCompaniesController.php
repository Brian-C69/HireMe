<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;

final class RecruiterCompaniesController
{
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

    private function ownCompany(PDO $pdo, int $id, int $rid): ?array
    {
        if ($id <= 0) return null;
        $st = $pdo->prepare("SELECT * FROM employers WHERE employer_id=:id AND is_client_company=1 AND created_by_recruiter_id=:rid LIMIT 1");
        $st->execute([':id' => $id, ':rid' => $rid]);
        return $st->fetch() ?: null;
    }

    /** GET /companies (Recruiter only) */
    public function index(array $params = []): void
    {
        Auth::requireRole('Recruiter');

        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);
        $st = $pdo->prepare("SELECT employer_id, company_name, location, industry, company_logo, updated_at
                             FROM employers
                             WHERE is_client_company=1 AND created_by_recruiter_id=:rid
                             ORDER BY company_name ASC");
        $st->execute([':rid' => $rid]);
        $companies = $st->fetchAll() ?: [];

        $root = dirname(__DIR__, 2);
        $title = 'Companies — Clients I Recruit For';
        $viewFile = $root . '/app/Views/recruiter/index.php';
        $errors = $this->takeErrors();
        $csrf = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** GET /companies/create */
    public function create(array $params = []): void
    {
        Auth::requireRole('Recruiter');

        $root = dirname(__DIR__, 2);
        $title = 'Add Company';
        $viewFile = $root . '/app/Views/recruiter/form.php';
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        $csrf = $this->csrf();
        $company = null; // form in create mode
        require $root . '/app/Views/layout.php';
    }

    /** POST /companies */
    public function store(array $params = []): void
    {
        Auth::requireRole('Recruiter');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/companies');
        }

        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);

        $name = trim((string)($_POST['company_name'] ?? ''));
        $industry = trim((string)($_POST['industry'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $email = ($email === '') ? null : strtolower($email);
        $contact_name = trim((string)($_POST['contact_person_name'] ?? ''));
        $contact_number = trim((string)($_POST['contact_number'] ?? ''));
        $description = trim((string)($_POST['company_description'] ?? ''));
        $errors = [];
        if ($name === '') $errors['company_name'] = 'Company name is required.';
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // logo upload (optional)
        $logoUrl = null;
        if (!empty($_FILES['company_logo']['name'])) {
            $up = $_FILES['company_logo'];
            if ($up['error'] === UPLOAD_ERR_OK) {
                $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                $mime = mime_content_type($up['tmp_name']) ?: '';
                if (!isset($ok[$mime])) $errors['company_logo'] = 'Only JPG or PNG allowed.';
                elseif ($up['size'] > 2 * 1024 * 1024) $errors['company_logo'] = 'Max 2MB.';
                else {
                    $root = dirname(__DIR__, 2);
                    $dir = $root . '/public/assets/uploads/companies';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $ext = $ok[$mime];
                    $nameFile = 'clientco_' . $rid . '_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $nameFile;
                    if (!move_uploaded_file($up['tmp_name'], $dest)) $errors['company_logo'] = 'Upload failed.';
                    else $logoUrl = '/assets/uploads/companies/' . $nameFile;
                }
            } else {
                $errors['company_logo'] = 'Upload error.';
            }
        }

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/companies/create');
        }

        $now = date('Y-m-d H:i:s');

        try {
            $st = $pdo->prepare("
        INSERT INTO employers (
            company_name, email, industry, location, contact_person_name, contact_number,
            company_description, company_logo, is_client_company, created_by_recruiter_id,
            created_at, updated_at
        ) VALUES (
            :name, :email, :ind, :loc, :cp, :cn,
            :desc, :logo, 1, :rid,
            :ca, :ua
        )
    ");
            $st->execute([
                ':name'  => $name,
                ':email' => $email,                 // <-- NULL when blank
                ':ind'   => $industry ?: null,
                ':loc'   => $location ?: null,
                ':cp'    => $contact_name ?: null,
                ':cn'    => $contact_number ?: null,
                ':desc'  => $description ?: null,
                ':logo'  => $logoUrl,
                ':rid'   => $rid,
                ':ca'    => $now,
                ':ua'    => $now
            ]);
        } catch (\PDOException $e) {
            // if user typed an email that already exists in employers (unique constraint)
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'email')) {
                $this->setErrors(['email' => 'That email is already in use.']);
                $this->setOld($_POST);
                $this->redirect('/companies/create');
            }
            throw $e;
        }

        $this->flash('success', 'Company added.');
        $this->redirect('/companies');
    }

    /** GET /companies/{id}/edit */
    public function edit(array $params = []): void
    {
        Auth::requireRole('Recruiter');

        $id = (int)($params['id'] ?? 0);
        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);
        $company = $this->ownCompany($pdo, $id, $rid);
        if (!$company) {
            $this->flash('danger', 'Not found or not yours.');
            $this->redirect('/companies');
        }

        $root = dirname(__DIR__, 2);
        $title = 'Edit Company — ' . $company['company_name'];
        $viewFile = $root . '/app/Views/recruiter/form.php';
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        $csrf = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /companies/{id}/edit */
    public function update(array $params = []): void
    {
        Auth::requireRole('Recruiter');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/companies');
        }

        $id = (int)($params['id'] ?? 0);
        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);
        $company = $this->ownCompany($pdo, $id, $rid);
        if (!$company) {
            $this->flash('danger', 'Not found or not yours.');
            $this->redirect('/companies');
        }

        $name           = trim((string)($_POST['company_name'] ?? ''));
        $industry       = trim((string)($_POST['industry'] ?? ''));
        $location       = trim((string)($_POST['location'] ?? ''));
        $email          = trim((string)($_POST['email'] ?? ''));
        $email          = ($email === '') ? null : strtolower($email);
        $contact_name   = trim((string)($_POST['contact_person_name'] ?? ''));
        $contact_number = trim((string)($_POST['contact_number'] ?? ''));
        $description    = trim((string)($_POST['company_description'] ?? ''));
        $logoUrl        = $company['company_logo'] ?? null;

        $errors = [];
        if ($name === '') $errors['company_name'] = 'Company name is required.';
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!empty($_FILES['company_logo']['name'])) {
            $up = $_FILES['company_logo'];
            if ($up['error'] === UPLOAD_ERR_OK) {
                $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                $mime = mime_content_type($up['tmp_name']) ?: '';
                if (!isset($ok[$mime])) $errors['company_logo'] = 'Only JPG or PNG allowed.';
                elseif ($up['size'] > 2 * 1024 * 1024) $errors['company_logo'] = 'Max 2MB.';
                else {
                    $root = dirname(__DIR__, 2);
                    $dir = $root . '/public/assets/uploads/companies';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $ext = $ok[$mime];
                    $nameFile = 'clientco_' . $rid . '_' . time() . '.' . $ext;
                    $dest = $dir . '/' . $nameFile;
                    if (!move_uploaded_file($up['tmp_name'], $dest)) $errors['company_logo'] = 'Upload failed.';
                    else $logoUrl = '/assets/uploads/companies/' . $nameFile;
                }
            } else {
                $errors['company_logo'] = 'Upload error.';
            }
        }

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/companies/' . $id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        try {
            $st = $pdo->prepare("
        UPDATE employers SET
          company_name=:name,
          email=:email,
          industry=:ind,
          location=:loc,
          contact_person_name=:cp,
          contact_number=:cn,
          company_description=:desc,
          company_logo=:logo,
          updated_at=:ua
        WHERE employer_id=:id
          AND is_client_company=1
          AND created_by_recruiter_id=:rid
    ");
            $st->execute([
                ':name'  => $name,
                ':email' => $email,                 // <-- NULL when blank
                ':ind'   => $industry ?: null,
                ':loc'   => $location ?: null,
                ':cp'    => $contact_name ?: null,
                ':cn'    => $contact_number ?: null,
                ':desc'  => $description ?: null,
                ':logo'  => $logoUrl,
                ':ua'    => $now,
                ':id'    => $id,
                ':rid'   => $rid
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'email')) {
                $this->setErrors(['email' => 'That email is already in use.']);
                $this->setOld($_POST);
                $this->redirect('/companies/' . $id . '/edit');
            }
            throw $e;
        }

        $this->flash('success', 'Company updated.');
        $this->redirect('/companies');
    }

    /** POST /companies/{id}/delete */
    public function destroy(array $params = []): void
    {
        Auth::requireRole('Recruiter');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/companies');
        }

        $id = (int)($params['id'] ?? 0);
        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);
        $company = $this->ownCompany($pdo, $id, $rid);
        if (!$company) {
            $this->flash('danger', 'Not found or not yours.');
            $this->redirect('/companies');
        }

        // Soft delete suggestion: set a flag instead of hard delete (optional)
        // For now, hard delete (ensure you don’t have jobs referencing it)
        $pdo->prepare("DELETE FROM employers WHERE employer_id=:id AND is_client_company=1 AND created_by_recruiter_id=:rid")
            ->execute([':id' => $id, ':rid' => $rid]);

        $this->flash('success', 'Company deleted.');
        $this->redirect('/companies');
    }
    public function bulk(array $params = []): void
    {
        Auth::requireRole('Recruiter');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/companies');
        }

        $action = trim((string)($_POST['bulk_action'] ?? ''));
        $ids    = array_values(array_filter((array)($_POST['ids'] ?? []), fn($v) => ctype_digit((string)$v)));
        if (!$action || !$ids) {
            $this->flash('warning', 'Select at least one company and an action.');
            $this->redirect('/companies');
        }

        $pdo = DB::conn();
        $rid = (int)($_SESSION['user']['id'] ?? 0);

        if ($action === 'delete') {
            // Only delete companies that belong to this recruiter AND are client companies
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "
            DELETE FROM employers
            WHERE is_client_company = 1
              AND created_by_recruiter_id = ?
              AND employer_id IN ($placeholders)
        ";
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare($sql);
                $st->execute(array_merge([$rid], array_map('intval', $ids)));
                $pdo->commit();
                $this->flash('success', 'Selected companies deleted (where allowed).');
            } catch (\Throwable $e) {
                $pdo->rollBack();
                // In case of FK constraints (e.g., jobs referencing a company)
                $this->flash('danger', 'Some companies could not be deleted (in use).');
            }
            $this->redirect('/companies');
        }

        $this->flash('warning', 'Unknown action.');
        $this->redirect('/companies');
    }
}
