<?php

/** @var string $role */
/** @var string $email */
/** @var string $displayName */
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<section class="py-5">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-12">
                <div class="p-4 bg-light border rounded-3">
                    <h1 class="h3 mb-1">Welcome back, <?= htmlspecialchars($displayName) ?> 👋</h1>
                    <p class="text-muted mb-0">
                        You’re signed in as <strong><?= htmlspecialchars($role) ?></strong> · <?= htmlspecialchars($email) ?>
                    </p>
                </div>
            </div>

            <?php if ($role === 'Candidate'): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Build/Update Resume</h5>
                            <p class="card-text text-muted">Keep your profile sharp for faster callbacks.</p>
                            <a class="btn btn-primary" href="<?= $base ?>/resume">Open Resume</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Browse Jobs</h5>
                            <p class="card-text text-muted">Search and filter jobs that fit you.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/jobs">Find Jobs</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Verify & Premium Badge</h5>
                            <p class="card-text text-muted">Boost trust and visibility (RM50 badge).</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/verify">Get Verified</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Employer'): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Post a Job</h5>
                            <p class="card-text text-muted">Create openings under your company.</p>
                            <a class="btn btn-primary" href="<?= $base ?>/jobs/create">Post Job</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Applicants</h5>
                            <p class="card-text text-muted">Review, shortlist, and manage candidates.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/jobs/mine">View Applicants</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Resume Credits</h5>
                            <p class="card-text text-muted">Unlock more profiles from the talent pool.</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/credits">Buy Credits</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Recruiter'): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Post for Clients</h5>
                            <p class="card-text text-muted">Manage roles across multiple companies.</p>
                            <a class="btn btn-primary" href="<?= $base ?>/jobs/create">Create Posting</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Talent Pool</h5>
                            <p class="card-text text-muted">Search and unlock high-potential candidates.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/candidates">Browse Candidates</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Companies</h5>
                            <p class="card-text text-muted">Add or remove companies you are helping to recruit</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/companies">Manage</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Admin'): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Overview & Metrics</h5>
                            <p class="card-text text-muted">Quick stats across users, jobs and applications.</p>
                            <a class="btn btn-primary" href="<?= $base ?>/admin/overview">Open Overview</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Candidates</h5>
                            <p class="card-text text-muted">Create, read, update, and delete candidate accounts.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/admin/candidates">Open Candidates</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Employers</h5>
                            <p class="card-text text-muted">Administer employer companies and users.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/admin/employers">Open Employers</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Recruiters</h5>
                            <p class="card-text text-muted">Oversee recruiter accounts and client companies.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/admin/recruiters">Open Recruiters</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Jobs & Applications</h5>
                            <p class="card-text text-muted">Moderate jobs, review applications.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/admin/jobs">Open Jobs</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Check KYC</h5>
                            <p class="card-text text-muted">Review candidate verification submissions.</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/verifications">
                                Open Verifications
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Credits & Payments</h5>
                            <p class="card-text text-muted">Adjust balances, audit purchases.</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/credits">Open Credits</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Manage Admins</h5>
                            <p class="card-text text-muted">Add, edit, or remove admin accounts.</p>
                            <a class="btn btn-outline-primary" href="<?= $base ?>/admin/admins">Open Admins</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">My Profile</h5>
                            <p class="card-text text-muted">Update your name, email, and password.</p>
                            <a class="btn btn-outline-secondary" href="<?= $base ?>/admin/profile">Open Profile</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>