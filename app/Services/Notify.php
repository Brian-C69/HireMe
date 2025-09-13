<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Mailer;
use PDO;
use Throwable;

final class Notify
{
    /** Low-level sender (array or string email OK). */
    private static function send($to, string $subject, string $html, ?string $text = null): void
    {
        try {
            (new Mailer())->send($to, $subject, $html, $text ?? strip_tags($html));
        } catch (Throwable) {
            // Silent fail: never block main flow on email failure.
        }
    }

    /** On new application (or re-apply): notify employer/recruiter + confirmation to candidate. */
    public static function onApplicationCreated(int $applicantId): void
    {
        $pdo = DB::conn();
        $sql = "
            SELECT a.applicant_id, a.candidate_id, a.job_posting_id, a.application_date,
                   c.full_name AS cand_name, c.email AS cand_email,
                   jp.job_title, jp.company_id, jp.recruiter_id,
                   e.company_name, e.email AS emp_email,
                   r.full_name AS rec_name, r.email AS rec_email
            FROM applications a
            JOIN candidates  c  ON c.candidate_id = a.candidate_id
            JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
            JOIN employers   e  ON e.employer_id   = jp.company_id
            LEFT JOIN recruiters r ON r.recruiter_id = jp.recruiter_id
            WHERE a.applicant_id = :id
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $applicantId]);
        $row = $st->fetch();
        if (!$row) return;

        $job     = (string)$row['job_title'];
        $cname   = (string)$row['cand_name'];
        $cemail  = (string)$row['cand_email'];
        $company = (string)$row['company_name'];
        $emp     = (string)$row['emp_email'];
        $recName = (string)($row['rec_name'] ?? '');
        $rec     = (string)($row['rec_email'] ?? '');

        // To employer/recruiter
        $to = array_values(array_filter([$emp, $rec])); // notify both if recruiter exists
        if ($to) {
            $sub  = "[HireMe] New application for {$job}";
            $html = "<p>Hi,</p>
                     <p><strong>{$cname}</strong> just applied to <strong>{$job}</strong> at <strong>{$company}</strong>.</p>
                     <p>Log in to review, shortlist, or schedule interviews.</p>";
            self::send($to, $sub, $html);
        }

        // Confirmation to candidate
        if ($cemail) {
            $sub  = "[HireMe] Application received — {$job} @ {$company}";
            $html = "<p>Hi {$cname},</p>
                     <p>We’ve received your application for <strong>{$job}</strong> at <strong>{$company}</strong>.</p>
                     <p>We’ll let you know once the employer updates your status.</p>";
            self::send($cemail, $sub, $html);
        }
    }

    /** On withdraw: notify employer/recruiter + confirmation to candidate. */
    public static function onApplicationWithdrawn(int $applicantId): void
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("
            SELECT a.applicant_id, a.candidate_id, a.job_posting_id, a.application_date,
                   c.full_name AS cand_name, c.email AS cand_email,
                   jp.job_title, jp.company_id, jp.recruiter_id,
                   e.company_name, e.email AS emp_email,
                   r.full_name AS rec_name, r.email AS rec_email
            FROM applications a
            JOIN candidates  c  ON c.candidate_id = a.candidate_id
            JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
            JOIN employers   e  ON e.employer_id   = jp.company_id
            LEFT JOIN recruiters r ON r.recruiter_id = jp.recruiter_id
            WHERE a.applicant_id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $applicantId]);
        $row = $st->fetch();
        if (!$row) return;

        $job     = (string)$row['job_title'];
        $cname   = (string)$row['cand_name'];
        $cemail  = (string)$row['cand_email'];
        $company = (string)$row['company_name'];
        $emp     = (string)$row['emp_email'];
        $rec     = (string)($row['rec_email'] ?? '');

        $to = array_values(array_filter([$emp, $rec]));
        if ($to) {
            $sub  = "[HireMe] Application withdrawn — {$job}";
            $html = "<p>Hi,</p>
                     <p><strong>{$cname}</strong> has withdrawn their application for <strong>{$job}</strong> at <strong>{$company}</strong>.</p>";
            self::send($to, $sub, $html);
        }

        if ($cemail) {
            $sub  = "[HireMe] You have withdrawn your application — {$job}";
            $html = "<p>Hi {$cname},</p>
                     <p>Your application for <strong>{$job}</strong> at <strong>{$company}</strong> was withdrawn.</p>";
            self::send($cemail, $sub, $html);
        }
    }

    /** When employer/recruiter updates an application status (Interview/Rejected/etc.) — email candidate. */
    public static function onApplicationStatusChanged(int $applicantId, string $newStatus): void
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("
            SELECT a.applicant_id, a.application_status,
                   c.full_name AS cand_name, c.email AS cand_email,
                   jp.job_title, e.company_name
            FROM applications a
            JOIN candidates  c  ON c.candidate_id = a.candidate_id
            JOIN job_postings jp ON jp.job_posting_id = a.job_posting_id
            JOIN employers   e  ON e.employer_id   = jp.company_id
            WHERE a.applicant_id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $applicantId]);
        $row = $st->fetch();
        if (!$row) return;

        $cname   = (string)$row['cand_name'];
        $cemail  = (string)$row['cand_email'];
        $job     = (string)$row['job_title'];
        $company = (string)$row['company_name'];

        if (!$cemail) return;

        $sub = "[HireMe] Your application status — {$job} @ {$company}";
        $msg = match (strtolower($newStatus)) {
            'interview', 'interviewing', 'call for interview' =>
            "Good news! Your application has been moved to <strong>Interview</strong>. The employer/recruiter may contact you soon with details.",
            'rejected', 'declined' =>
            "Thanks for applying. Your application status is now <strong>Rejected</strong>. Don’t be discouraged—there are more roles waiting for you!",
            default =>
            "Your application status is now: <strong>" . htmlspecialchars($newStatus) . "</strong>.",
        };

        $html = "<p>Hi {$cname},</p>
                 <p>{$msg}</p>
                 <p>Role: <strong>{$job}</strong><br>Company: <strong>{$company}</strong></p>";
        self::send($cemail, $sub, $html);
    }

    /** Candidate KYC verification decision from Admin. $status: 2=Approved, 1=Pending, 0=Submitted, -1=Rejected (example) */
    public static function onCandidateVerification(int $candidateId, int $status): void
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("SELECT full_name, email FROM candidates WHERE candidate_id=:id LIMIT 1");
        $st->execute([':id' => $candidateId]);
        $row = $st->fetch();
        if (!$row) return;

        $name  = (string)$row['full_name'];
        $email = (string)$row['email'];
        if (!$email) return;

        if ($status === 2) {
            $sub  = "[HireMe] Your verification is approved";
            $html = "<p>Hi {$name},</p><p>Your account verification has been <strong>approved</strong>. Thanks for verifying!</p>";
        } elseif ($status < 0) {
            $sub  = "[HireMe] Your verification could not be approved";
            $html = "<p>Hi {$name},</p><p>We’re unable to approve your verification at this time. Please re-submit with clear documents.</p>";
        } else {
            // Usually we don’t email for pending, but you can if you want.
            $sub  = "[HireMe] Verification update";
            $html = "<p>Hi {$name},</p><p>Your verification status has been updated.</p>";
        }

        self::send($email, $sub, $html);
    }
}
