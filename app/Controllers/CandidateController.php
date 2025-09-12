<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use PDO;
use Throwable;

final class CandidateController
{
    private function flash(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    }
    private function setErrors(array $errs): void
    {
        $_SESSION['errors'] = $errs;
    }
    private function takeErrors(): array
    {
        $e = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);
        return $e;
    }
    private function setOld(array $old): void
    {
        $_SESSION['old'] = $old;
    }
    private function takeOld(): array
    {
        $o = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $o;
    }
    private function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
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

    public function edit(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $root     = dirname(__DIR__, 2);
        $title    = 'My Profile — HireMe';
        $viewFile = $root . '/app/Views/account/edit.php';
        $errors   = $this->takeErrors();
        $old      = $this->takeOld();

        $pdo  = DB::conn();
        $user = $_SESSION['user'];
        $st = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = :id LIMIT 1");
        $st->execute([':id' => $user['id']]);
        $candidate = $st->fetch() ?: [];

        require $root . '/app/Views/layout.php';
    }

    public function update(array $params = []): void
    {
        // Guard: Candidate only
        if (class_exists(\App\Core\Auth::class)) {
            \App\Core\Auth::requireRole('Candidate');
        } else {
            if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Candidate') {
                $this->flash('warning', 'Please log in as Candidate.');
                $this->redirect('/login');
            }
        }

        // CSRF
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/account');
        }

        $pdo = \App\Core\DB::conn();
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $st  = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = :id LIMIT 1");
        $st->execute([':id' => $uid]);
        $candidate = $st->fetch();
        if (!$candidate) {
            $this->flash('danger', 'Profile not found.');
            $this->redirect('/account');
        }

        // Determine which form was submitted
        $section = (string)($_POST['section'] ?? 'details');

        // Upload targets
        $root        = dirname(__DIR__, 2);
        $profilesDir = $root . '/public/assets/uploads/profiles';
        $resumesDir  = $root . '/public/assets/uploads/resumes';
        if (!is_dir($profilesDir)) @mkdir($profilesDir, 0777, true);
        if (!is_dir($resumesDir))  @mkdir($resumesDir, 0777, true);

        $now = date('Y-m-d H:i:s');

        if ($section === 'photo') {
            // --- Only handle profile picture ---
            $errors = [];
            $profileUrl = null;

            if (!empty($_FILES['profile_picture']['name'])) {
                $up = $_FILES['profile_picture'];
                if ($up['error'] === UPLOAD_ERR_OK) {
                    $okTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                    $mime = mime_content_type($up['tmp_name']) ?: '';
                    if (!isset($okTypes[$mime])) {
                        $errors['profile_picture'] = 'Only JPG or PNG allowed.';
                    } elseif ($up['size'] > 2 * 1024 * 1024) {
                        $errors['profile_picture'] = 'Max 2MB.';
                    } else {
                        $ext = $okTypes[$mime];
                        $name = 'cand_' . $uid . '_' . time() . '.' . $ext;
                        $dest = $profilesDir . '/' . $name;
                        if (!move_uploaded_file($up['tmp_name'], $dest)) {
                            $errors['profile_picture'] = 'Upload failed.';
                        } else {
                            $profileUrl = '/assets/uploads/profiles/' . $name;
                        }
                    }
                } else {
                    $errors['profile_picture'] = 'Upload error.';
                }
            } else {
                $errors['profile_picture'] = 'Please choose an image.';
            }

            if ($errors) {
                $this->setErrors($errors);
                $this->redirect('/account');
            }

            $pdo->prepare("UPDATE candidates SET profile_picture_url=:u, updated_at=:ua WHERE candidate_id=:id")
                ->execute([':u' => $profileUrl, ':ua' => $now, ':id' => $uid]);

            $this->flash('success', 'Profile photo updated.');
            $this->redirect('/account');
        }

        if ($section === 'resume') {
            // --- Only handle resume file ---
            $errors = [];
            $resumeUrl = null;

            if (!empty($_FILES['resume_file']['name'])) {
                $up = $_FILES['resume_file'];
                if ($up['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                        $errors['resume_file'] = 'Allowed: PDF, DOC, DOCX.';
                    } elseif ($up['size'] > 5 * 1024 * 1024) {
                        $errors['resume_file'] = 'Max 5MB.';
                    } else {
                        $name = 'resume_' . $uid . '_' . time() . '.' . $ext;
                        $dest = $resumesDir . '/' . $name;
                        if (!move_uploaded_file($up['tmp_name'], $dest)) {
                            $errors['resume_file'] = 'Upload failed.';
                        } else {
                            $resumeUrl = '/assets/uploads/resumes/' . $name;
                        }
                    }
                } else {
                    $errors['resume_file'] = 'Upload error.';
                }
            } else {
                $errors['resume_file'] = 'Please choose a file.';
            }

            if ($errors) {
                $this->setErrors($errors);
                $this->redirect('/account');
            }

            try {
                $pdo->beginTransaction();

                $pdo->prepare("UPDATE candidates SET resume_url=:u, updated_at=:ua WHERE candidate_id=:id")
                    ->execute([':u' => $resumeUrl, ':ua' => $now, ':id' => $uid]);

                $pdo->prepare("
                INSERT INTO resumes (candidate_id, resume_url, generated_by_system, summary, created_at, updated_at)
                VALUES (:cid, :url, 0, :summary, :ca, :ua)
            ")->execute([
                    ':cid' => $uid,
                    ':url' => $resumeUrl,
                    ':summary' => null,
                    ':ca' => $now,
                    ':ua' => $now,
                ]);

                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $this->setErrors(['resume_file' => 'Could not save resume.']);
                $this->redirect('/account');
            }

            $this->flash('success', 'Resume uploaded.');
            $this->redirect('/account');
        }

        // --- section === 'details' (default): validate and save profile fields only ---
        $full_name  = trim((string)($_POST['full_name'] ?? ''));
        $phone      = trim((string)($_POST['phone_number'] ?? ''));
        $address    = trim((string)($_POST['address'] ?? ''));
        $city       = trim((string)($_POST['city'] ?? ''));
        $state      = trim((string)($_POST['state'] ?? ''));
        $postal     = trim((string)($_POST['postal_code'] ?? ''));
        $country    = trim((string)($_POST['country'] ?? 'Malaysia'));
        $skills     = trim((string)($_POST['skills'] ?? ''));
        $exp_years  = (string)($_POST['experience_years'] ?? '');
        $education  = trim((string)($_POST['education_level'] ?? ''));

        $errors = [];
        if ($full_name === '') $errors['full_name'] = 'Full name is required.';
        if ($exp_years !== '' && !ctype_digit($exp_years)) $errors['experience_years'] = 'Years must be a whole number.';

        if ($errors) {
            $this->setErrors($errors);
            $this->setOld($_POST);
            $this->redirect('/account');
        }

        $fields = [
            'full_name'        => $full_name,
            'phone_number'     => $phone ?: null,
            'address'          => $address ?: null,
            'city'             => $city ?: null,
            'state'            => $state ?: null,
            'postal_code'      => $postal ?: null,
            'country'          => $country ?: 'Malaysia',
            'skills'           => $skills ?: null,
            'experience_years' => ($exp_years === '' ? null : (int)$exp_years),
            'education_level'  => $education ?: null,
            'updated_at'       => $now,
        ];

        $set = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $set[] = "{$k} = :{$k}";
            $params[":{$k}"] = $v;
        }
        $params[':id'] = $uid;

        $pdo->prepare("UPDATE candidates SET " . implode(', ', $set) . " WHERE candidate_id = :id")
            ->execute($params);

        $this->flash('success', 'Profile updated.');
        $this->redirect('/account');
    }

    /** GET /verify */
    public function verifyForm(array $params = []): void
    {
        Auth::requireRole('Candidate');

        $pdo = DB::conn();
        $id  = (int)($_SESSION['user']['id'] ?? 0);
        $st  = $pdo->prepare("SELECT candidate_id, full_name, verified_status, verification_date, verification_doc_type, verification_doc_url, premium_badge FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $me  = $st->fetch() ?: [];

        $root   = dirname(__DIR__, 2);
        $title  = 'Verify Account — HireMe';
        $viewFile = $root . '/app/Views/candidate/verify.php';
        $errors = $this->takeErrors();
        $csrf   = $this->csrf();
        require $root . '/app/Views/layout.php';
    }

    /** POST /verify */
    public function submitVerification(array $params = []): void
    {
        Auth::requireRole('Candidate');
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/verify');
        }

        $pdo = DB::conn();
        $id  = (int)($_SESSION['user']['id'] ?? 0);

        // Basic input
        $docType = trim((string)($_POST['verification_doc_type'] ?? ''));
        $allowedTypes = ['IC', 'Passport', 'Driver\'s License'];
        $errors = [];
        if (!in_array($docType, $allowedTypes, true)) $errors['verification_doc_type'] = 'Select a valid document type.';

        // Upload
        $url = null;
        if (!empty($_FILES['verification_doc']['name'])) {
            $up = $_FILES['verification_doc'];
            if ($up['error'] !== UPLOAD_ERR_OK) {
                $errors['verification_doc'] = 'Upload error.';
            } else {
                $mime = mime_content_type($up['tmp_name']) ?: '';
                $ok   = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'application/pdf' => 'pdf'
                ];
                if (!isset($ok[$mime])) {
                    $errors['verification_doc'] = 'Only JPG/PNG/PDF are allowed.';
                } elseif ($up['size'] > 5 * 1024 * 1024) {
                    $errors['verification_doc'] = 'Max 5MB.';
                } else {
                    $root = dirname(__DIR__, 2);
                    $dir  = $root . '/public/assets/uploads/kyc';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $name = 'kyc_' . $id . '_' . time() . '.' . $ok[$mime];
                    $dest = $dir . '/' . $name;
                    if (!move_uploaded_file($up['tmp_name'], $dest)) {
                        $errors['verification_doc'] = 'Failed to save upload.';
                    } else {
                        $url = '/assets/uploads/kyc/' . $name; // public URL
                    }
                }
            }
        } else {
            $errors['verification_doc'] = 'Please upload your document.';
        }

        if ($errors) {
            $this->setErrors($errors);
            $this->redirect('/verify');
        }

        // Persist: mark as submitted (still not verified — admin will approve later)
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("
            UPDATE candidates
            SET verification_doc_type=:t, verification_doc_url=:u, verified_status=0, verification_date=:d, updated_at=:u2
            WHERE candidate_id=:id
        ")->execute([':t' => $docType, ':u' => $url, ':d' => $now, ':u2' => $now, ':id' => $id]);

        $this->flash('success', 'Verification submitted. We’ll review and update your status soon.');
        $this->redirect('/verify');
    }

    private function requireEmployerOrRecruiter(): void
    {
        Auth::requireRole(['Employer', 'Recruiter']);
    }

    private function isUnlocked(PDO $pdo, string $viewerType, int $viewerId, int $candidateId): bool
    {
        $st = $pdo->prepare("SELECT 1 FROM resume_unlocks WHERE unlocked_by_type=:t AND unlocked_by_id=:v AND candidate_id=:c LIMIT 1");
        $st->execute([':t' => $viewerType, ':v' => $viewerId, ':c' => $candidateId]);
        return (bool)$st->fetchColumn();
    }

    private function deductOneCredit(PDO $pdo, string $viewerType, int $viewerId): bool
    {
        if ($viewerType === 'Employer') {
            $st = $pdo->prepare("UPDATE employers SET credits_balance = credits_balance - 1 WHERE employer_id=:id AND credits_balance > 0");
        } else {
            $st = $pdo->prepare("UPDATE recruiters SET credits_balance = credits_balance - 1 WHERE recruiter_id=:id AND credits_balance > 0");
        }
        $st->execute([':id' => $viewerId]);
        return $st->rowCount() === 1;
    }

    private function obfuscateEmail(string $email): string
    {
        if (!str_contains($email, '@')) return 'hidden';
        [$u, $d] = explode('@', $email, 2);
        if (strlen($u) <= 2) $u = substr($u, 0, 1) . '*';
        else $u = substr($u, 0, 1) . str_repeat('*', max(1, strlen($u) - 2)) . substr($u, -1);
        return $u . '@' . $d;
    }
    private function obfuscatePhone(string $p): string
    {
        $digits = preg_replace('/\D+/', '', $p);
        if ($digits === '') return 'hidden';
        return substr($digits, 0, 2) . str_repeat('*', max(1, strlen($digits) - 5)) . substr($digits, -3);
    }

    # --------------------------------------------------------
    # GET /candidates  (Employer/Recruiter only)
    # --------------------------------------------------------
    public function directory(array $params = []): void
    {
        \App\Core\Auth::requireRole(['Employer', 'Recruiter']); // or allow public browsing if you want

        $pdo = \App\Core\DB::conn();

        // Filters
        $q        = trim((string)($_GET['q'] ?? ''));
        $city     = trim((string)($_GET['city'] ?? ''));
        $state    = trim((string)($_GET['state'] ?? ''));
        $minExp   = (string)($_GET['min_exp'] ?? '');
        $verified = (string)($_GET['verified'] ?? '');  // '1' or ''
        $premium  = (string)($_GET['premium'] ?? '');   // '1' or ''
        $per      = max(1, min(50, (int)($_GET['per'] ?? 12)));
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $offset   = ($page - 1) * $per;

        $where = ['1=1'];
        $bind  = [];

        if ($q !== '') {
            $where[] = '(c.full_name LIKE :q OR c.skills LIKE :q)';
            $bind[':q'] = "%$q%";
        }
        if ($city !== '') {
            $where[] = 'c.city LIKE :city';
            $bind[':city'] = "%$city%";
        }
        if ($state !== '') {
            $where[] = 'c.state LIKE :state';
            $bind[':state'] = "%$state%";
        }
        if ($minExp !== '' && is_numeric($minExp)) {
            $where[] = 'c.experience_years >= :minexp';
            $bind[':minexp'] = (int)$minExp;
        }
        if ($verified === '1') {
            $where[] = 'c.verified_status = 1';
        }
        if ($premium === '1') {
            $where[] = 'c.premium_badge = 1';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Count
        $st = $pdo->prepare("SELECT COUNT(*) FROM candidates c $whereSql");
        $st->execute($bind);
        $total = (int)($st->fetchColumn() ?: 0);

        // Page
        $sql = "
      SELECT c.candidate_id, c.full_name, c.city, c.state, c.experience_years,
             c.premium_badge, c.verified_status, c.profile_picture_url, c.skills
      FROM candidates c
      $whereSql
      ORDER BY c.updated_at DESC, c.candidate_id DESC
      LIMIT :lim OFFSET :off
    ";
        $st = $pdo->prepare($sql);
        foreach ($bind as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $per, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        // After fetching $rows:
        $unlockedIds = $this->unlockedIds($pdo);

        $root     = dirname(__DIR__, 2);
        $title    = 'Candidates — HireMe';
        $viewFile = $root . '/app/Views/candidates/index.php';
        $filters  = [
            'q' => $q,
            'city' => $city,
            'state' => $state,
            'min_exp' => $minExp,
            'verified' => $verified,
            'premium' => $premium,
            'per' => $per,
            'page' => $page,
            'total' => $total
        ];

        require $root . '/app/Views/layout.php';
    }

    # --------------------------------------------------------
    # GET /candidates/{id}
    # --------------------------------------------------------
    public function view(array $params = []): void
    {
        $this->requireEmployerOrRecruiter();

        $id  = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('danger', 'Invalid candidate.');
            $this->redirect('/candidates');
        }

        $pdo = DB::conn();
        $st  = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $candidate = $st->fetch();

        if (!$candidate) {
            http_response_code(404);
            $root = dirname(__DIR__, 2);
            require $root . '/app/Views/errors/404.php';
            return;
        }

        $viewerType = $_SESSION['user']['role'] ?? '';
        $viewerId   = (int)($_SESSION['user']['id'] ?? 0);
        $unlocked   = $this->isUnlocked($pdo, $viewerType, $viewerId, $id);

        // limited sets if not unlocked
        $experiences = $skills = $languages = $education = [];
        if ($unlocked) {
            $experiences = $this->fetchAll($pdo, "SELECT * FROM candidate_experiences WHERE candidate_id=:id ORDER BY start_date DESC, id DESC", [':id' => $id]);
            $skills      = $this->fetchAll($pdo, "SELECT * FROM candidate_skills       WHERE candidate_id=:id ORDER BY level DESC, name ASC", [':id' => $id]);
            $languages   = $this->fetchAll($pdo, "SELECT * FROM candidate_languages    WHERE candidate_id=:id ORDER BY language ASC", [':id' => $id]);
            $education   = $this->fetchAll($pdo, "SELECT * FROM candidate_education    WHERE candidate_id=:id ORDER BY graduation_year DESC, id DESC", [':id' => $id]);
        }

        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

        $root     = dirname(__DIR__, 2);
        $title    = 'Candidate — ' . htmlspecialchars($candidate['full_name'] ?? '');
        $viewFile = $root . '/app/Views/candidates/show.php';
        require $root . '/app/Views/layout.php';
    }

    # --------------------------------------------------------
    # POST /candidates/{id}/unlock
    # --------------------------------------------------------
    public function unlock(array $params = []): void
    {
        $this->requireEmployerOrRecruiter();
        if (!$this->csrfOk()) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/candidates');
        }

        $cid = (int)($params['id'] ?? 0);
        if ($cid <= 0) {
            $this->flash('danger', 'Invalid candidate.');
            $this->redirect('/candidates');
        }

        $pdo = DB::conn();
        $viewerType = $_SESSION['user']['role'] ?? '';
        $viewerId   = (int)($_SESSION['user']['id'] ?? 0);

        // Already unlocked? (free)
        if ($this->isUnlocked($pdo, $viewerType, $viewerId, $cid)) {
            $this->flash('info', 'This resume is already unlocked for you.');
            $this->redirect('/candidates/' . $cid);
        }

        $pdo->beginTransaction();
        try {
            // Deduct 1 credit atomically
            if (!$this->deductOneCredit($pdo, $viewerType, $viewerId)) {
                $pdo->rollBack();
                $this->flash('danger', 'Not enough credits. Purchase more to unlock resumes.');
                $this->redirect('/credits'); // or your payment page
            }

            // Record unlock
            $ins = $pdo->prepare("INSERT INTO resume_unlocks (unlocked_by_type, unlocked_by_id, candidate_id, created_at) VALUES (:t,:v,:c,:d)");
            $ins->execute([
                ':t' => $viewerType,
                ':v' => $viewerId,
                ':c' => $cid,
                ':d' => date('Y-m-d H:i:s')
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('danger', 'Could not unlock this resume.');
            $this->redirect('/candidates/' . $cid);
        }

        $this->flash('success', 'Resume unlocked. You can now view full details.');
        $this->redirect('/candidates/' . $cid);
    }

    // tiny fetchAll helper
    private function fetchAll(PDO $pdo, string $sql, array $bind = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll() ?: [];
    }

    private function unlockedIds(PDO $pdo): array
    {
        $role = $_SESSION['user']['role'] ?? '';
        if (!in_array($role, ['Employer', 'Recruiter'], true)) {
            return [];
        }
        $viewerId = (int)($_SESSION['user']['id'] ?? 0);

        $st = $pdo->prepare("
        SELECT candidate_id
        FROM resume_unlocks
        WHERE unlocked_by_type = :t
          AND unlocked_by_id   = :id
    ");
        $st->execute([':t' => $role, ':id' => $viewerId]);

        return array_map('intval', array_column($st->fetchAll() ?: [], 'candidate_id'));
    }
}
