<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Basic active record style model for the job_postings table.
 * Only the operations required by JobService are implemented.
 */
final class JobPosting
{
    /**
     * Create a new job posting record.
     *
     * @param array{cid:int,rid:?int,title:string,desc:string,reqs:?string,loc:?string,langs:?string,etype:string,smin:?string,posted:string,ca:string,ua:string} $data
     * @return int The newly created job ID
     */
    public static function create(array $data): int
    {
        $pdo = DB::conn();

        $sql = "INSERT INTO job_postings
            (company_id, recruiter_id, job_title, job_description, job_requirements, job_location, job_languages,
             employment_type, salary_range_min, salary_range_max, application_deadline, date_posted, status,
             number_of_positions, required_experience, education_level, created_at, updated_at)
            VALUES
            (:cid, :rid, :title, :desc, :reqs, :loc, :langs,
             :etype, :smin, NULL, NULL, :posted, 'Open',
             1, NULL, NULL, :ca, :ua)";

        $st = $pdo->prepare($sql);
        $st->execute([
            ':cid'    => $data['cid'],
            ':rid'    => $data['rid'],
            ':title'  => $data['title'],
            ':desc'   => $data['desc'],
            ':reqs'   => $data['reqs'],
            ':loc'    => $data['loc'],
            ':langs'  => $data['langs'],
            ':etype'  => $data['etype'],
            ':smin'   => $data['smin'],
            ':posted' => $data['posted'],
            ':ca'     => $data['ca'],
            ':ua'     => $data['ua'],
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Attach the chosen micro questions to the job posting.
     *
     * @param int   $jobId       Job posting ID
     * @param int[] $questionIds Selected micro question IDs
     */
    public static function attachQuestions(int $jobId, array $questionIds): void
    {
        if (!$questionIds) {
            return;
        }

        $pdo = DB::conn();
        $ins = $pdo->prepare("INSERT INTO job_micro_questions (job_posting_id, question_id) VALUES (:jid, :qid)");
        foreach ($questionIds as $qid) {
            $ins->execute([':jid' => $jobId, ':qid' => $qid]);
        }
    }
}

