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
}
