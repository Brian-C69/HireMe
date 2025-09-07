<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(array $params = []): void
    {
?>
        <!doctype html>
        <html lang="en">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>HireMe — Hiring in Malaysia, simplified.</title>
            <meta name="description" content="Malaysia’s recruitment platform — fast, transparent, and app-free.">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #fff;
                    color: #212529;
                }

                .hero {
                    background: radial-gradient(1200px 400px at 20% 0%, rgba(13, 110, 253, .08), transparent),
                        linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
                }

                .rounded-2xl {
                    border-radius: 1rem;
                }

                .bg-soft {
                    background: #f8f9fa;
                }
            </style>
        </head>

        <body>
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
                <div class="container">
                    <a class="navbar-brand fw-bold" href="/"><i class="bi bi-stars text-primary me-1"></i> HireMe</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div id="nav" class="collapse navbar-collapse">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                            <li class="nav-item"><a class="nav-link" href="#how">How It Works</a></li>
                            <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
                        </ul>
                        <div class="d-flex gap-2">
                            <a href="/login" class="btn btn-outline-primary">Login</a>
                            <a href="/register" class="btn btn-primary">Get Started</a>
                        </div>
                    </div>
                </div>
            </nav>

            <header class="hero py-5 border-bottom">
                <div class="container">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <h1 class="display-4 fw-bold lh-1 mb-3">Hiring in Malaysia, <span class="text-primary">simplified</span>.</h1>
                            <p class="lead text-muted">Malaysia’s recruitment platform — fast, transparent, and app-free.</p>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn btn-primary btn-lg" href="/register?role=candidate"><i class="bi bi-person-plus me-1"></i> I’m a Job Seeker</a>
                                <a class="btn btn-outline-primary btn-lg" href="/register?role=employer"><i class="bi bi-building me-1"></i> I’m an Employer</a>
                                <a class="btn btn-outline-primary btn-lg" href="/register?role=recruiter"><i class="bi bi-people me-1"></i> I’m a Recruiter</a>
                            </div>
                            <div class="mt-4 small text-muted">
                                <i class="bi bi-patch-check-fill text-warning me-1"></i> Verified profiles & RM50 premium badge to boost visibility.
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card bg-soft rounded-2xl shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <i class="bi bi-graph-up-arrow fs-1 text-primary"></i>
                                        <div>
                                            <h5 class="mb-0">Faster, Fairer, App-Free</h5>
                                            <small class="text-muted">Mobile, tablet, desktop — all supported.</small>
                                        </div>
                                    </div>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Smart resume generator</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Employer & recruiter tools</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> KYC verification & premium badge</li>
                                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Credit-based resume unlocking</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main>
                <section id="features" class="py-5 bg-light border-top border-bottom">
                    <div class="container">
                        <h2 class="h1 text-center mb-4">Key Features</h2>
                        <div class="row g-3">
                            <?php
                            $features = [
                                ['bi-file-earmark-text', 'Professional Resume Generator', 'Generate clean resumes highlighting key info at a glance.'],
                                ['bi-buildings', 'Employer-Centric Posting', 'Post multiple roles under one company.'],
                                ['bi-briefcase', 'Recruiter Multi-Company', 'Post for multiple companies from one dashboard.'],
                                ['bi-shield-check', 'KYC & Premium Badge', 'Secure verification + golden badge (RM50).'],
                                ['bi-unlock', 'Resume Unlock Credits', 'Unlock high-potential candidates beyond applicants.'],
                                ['bi-phone', 'Cross-Platform', 'Fast, responsive — no apps required.'],
                            ];
                            foreach ($features as $f): ?>
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-body d-flex gap-3">
                                            <i class="bi <?= htmlspecialchars($f[0]) ?> text-primary fs-4"></i>
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($f[1]) ?></h5>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($f[2]) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section id="how" class="py-5">
                    <div class="container">
                        <h2 class="h1 text-center mb-5">How It Works</h2>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="p-4 border rounded-2xl bg-soft h-100">
                                    <h5 class="mb-3"><i class="bi bi-person-check me-2 text-success"></i>Job Seeker Flow</h5>
                                    <ol class="mb-0">
                                        <li>Sign up & confirm email</li>
                                        <li>Build profile & resume (or upload)</li>
                                        <li>Search & filter jobs</li>
                                        <li>Apply with resume</li>
                                        <li>Optional: KYC & RM50 premium badge</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-4 border rounded-2xl bg-soft h-100">
                                    <h5 class="mb-3"><i class="bi bi-briefcase me-2 text-info"></i>Employer & Recruiter Flow</h5>
                                    <ol class="mb-0">
                                        <li>Create account</li>
                                        <li>Post jobs (single company / multi-company)</li>
                                        <li>Review applicants & bookmarks</li>
                                        <li>Unlock additional resumes with credits</li>
                                        <li>Set micro-interview questions (recruiters)</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="py-4 bg-white border-top">
                <div class="container d-flex flex-column flex-lg-row justify-content-between align-items-center gap-2">
                    <div class="small text-muted">© <?= date('Y') ?> HireMe. All rights reserved.</div>
                    <div class="small">
                        <a href="/terms" class="me-3 link-dark">Terms</a>
                        <a href="/privacy" class="link-dark">Privacy</a>
                    </div>
                </div>
            </footer>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>

        </html>
<?php
    }
}
