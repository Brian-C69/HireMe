<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Models\Application;
use App\Models\Candidate;
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
        $app = Application::with(['candidate', 'jobPosting.employer', 'jobPosting.recruiter'])
            ->find($applicantId);
        if (!$app || !$app->candidate || !$app->jobPosting) return;

        $candidate = $app->candidate;
        $job       = $app->jobPosting;
        $employer  = $job->employer;
        $recruiter = $job->recruiter;

        $jobTitle = (string)$job->job_title;
        $cname    = (string)$candidate->full_name;
        $cemail   = (string)$candidate->email;
        $company  = (string)($employer->company_name ?? '');
        $emp      = (string)($employer->email ?? '');
        $recName  = (string)($recruiter->full_name ?? '');
        $rec      = (string)($recruiter->email ?? '');

        $to = array_values(array_filter([$emp, $rec]));
        if ($to) {
            $sub  = "[HireMe] New application for {$jobTitle}";
            $html = "<p>Hi,</p>"
                   . "<p><strong>{$cname}</strong> just applied to <strong>{$jobTitle}</strong> at <strong>{$company}</strong>.</p>"
                   . "<p>Log in to review, shortlist, or schedule interviews.</p>";
            self::send($to, $sub, $html);
        }

        if ($cemail) {
            $sub  = "[HireMe] Application received — {$jobTitle} @ {$company}";
            $html = "<p>Hi {$cname},</p>"
                   . "<p>We’ve received your application for <strong>{$jobTitle}</strong> at <strong>{$company}</strong>.</p>"
                   . "<p>We’ll let you know once the employer updates your status.</p>";
            self::send($cemail, $sub, $html);
        }
    }

    /** On withdraw: notify employer/recruiter + confirmation to candidate. */
    public static function onApplicationWithdrawn(int $applicantId): void
    {
        $app = Application::with(['candidate', 'jobPosting.employer', 'jobPosting.recruiter'])
            ->find($applicantId);
        if (!$app || !$app->candidate || !$app->jobPosting) return;

        $candidate = $app->candidate;
        $job       = $app->jobPosting;
        $employer  = $job->employer;
        $recruiter = $job->recruiter;

        $jobTitle = (string)$job->job_title;
        $cname    = (string)$candidate->full_name;
        $cemail   = (string)$candidate->email;
        $company  = (string)($employer->company_name ?? '');
        $emp      = (string)($employer->email ?? '');
        $rec      = (string)($recruiter->email ?? '');

        $to = array_values(array_filter([$emp, $rec]));
        if ($to) {
            $sub  = "[HireMe] Application withdrawn — {$jobTitle}";
            $html = "<p>Hi,</p>"
                   . "<p><strong>{$cname}</strong> has withdrawn their application for <strong>{$jobTitle}</strong> at <strong>{$company}</strong>.</p>";
            self::send($to, $sub, $html);
        }

        if ($cemail) {
            $sub  = "[HireMe] You have withdrawn your application — {$jobTitle}";
            $html = "<p>Hi {$cname},</p>"
                   . "<p>Your application for <strong>{$jobTitle}</strong> at <strong>{$company}</strong> was withdrawn.</p>";
            self::send($cemail, $sub, $html);
        }
    }

    /** When employer/recruiter updates an application status (Interview/Rejected/etc.) — email candidate. */
    public static function onApplicationStatusChanged(int $applicantId, string $newStatus): void
    {
        $app = Application::with(['candidate', 'jobPosting.employer'])
            ->find($applicantId);
        if (!$app || !$app->candidate || !$app->jobPosting) return;

        $candidate = $app->candidate;
        $job       = $app->jobPosting;
        $employer  = $job->employer;

        $cname   = (string)$candidate->full_name;
        $cemail  = (string)$candidate->email;
        $jobTitle = (string)$job->job_title;
        $company  = (string)($employer->company_name ?? '');

        if (!$cemail) return;

        $sub = "[HireMe] Your application status — {$jobTitle} @ {$company}";
        $msg = match (strtolower($newStatus)) {
            'interview', 'interviewing', 'call for interview' =>
                "Good news! Your application has been moved to <strong>Interview</strong>. The employer/recruiter may contact you soon with details.",
            'rejected', 'declined' =>
                "Thanks for applying. Your application status is now <strong>Rejected</strong>. Don’t be discouraged—there are more roles waiting for you!",
            default =>
                "Your application status is now: <strong>" . htmlspecialchars($newStatus) . "</strong>.",
        };

        $html = "<p>Hi {$cname},</p>"
               . "<p>{$msg}</p>"
               . "<p>Role: <strong>{$jobTitle}</strong><br>Company: <strong>{$company}</strong></p>";
        self::send($cemail, $sub, $html);
    }

    /** Candidate KYC verification decision from Admin. $status: 2=Approved, 1=Pending, 0=Submitted, -1=Rejected (example) */
    public static function onCandidateVerification(int $candidateId, int $status): void
    {
        $cand = Candidate::find($candidateId);
        if (!$cand) return;

        $name  = (string)$cand->full_name;
        $email = (string)$cand->email;
        if (!$email) return;

        if ($status === 2) {
            $sub  = "[HireMe] Your verification is approved";
            $html = "<p>Hi {$name},</p><p>Your account verification has been <strong>approved</strong>. Thanks for verifying!</p>";
        } elseif ($status < 0) {
            $sub  = "[HireMe] Your verification could not be approved";
            $html = "<p>Hi {$name},</p><p>We’re unable to approve your verification at this time. Please re-submit with clear documents.</p>";
        } else {
            $sub  = "[HireMe] Verification update";
            $html = "<p>Hi {$name},</p><p>Your verification status has been updated.</p>";
        }

        self::send($email, $sub, $html);
    }
}
