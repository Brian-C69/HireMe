<?php
$base = defined('BASE_URL') ? BASE_URL : '';
$counts = $counts ?? [];
$chart  = $chart  ?? [];
$from   = htmlspecialchars($chart['from'] ?? '');
$to     = htmlspecialchars($chart['to'] ?? '');

$labels = $chart['labels'] ?? [];
$reg    = $chart['registrations'] ?? ['candidates' => [], 'employers' => [], 'recruiters' => []];
$jobs   = $chart['jobs'] ?? [];
$apps   = $chart['applications_daily'] ?? [];
$appsStatus = $chart['applications_status'] ?? [];
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Overview & Metrics</h1>
            <form class="d-flex gap-2" method="get" action="<?= $base ?>/admin/overview">
                <input type="date" class="form-control" name="from" value="<?= $from ?>">
                <input type="date" class="form-control" name="to" value="<?= $to ?>">
                <button class="btn btn-outline-primary" type="submit">Update</button>
            </form>
        </div>

        <div class="d-flex mb-3">
            <div class="d-flex gap-2">
                <a class="btn btn-dark" href="<?= $base ?>/admin/overview/export-all">
                    <i class="bi bi-download me-1"></i> Download All (ZIP)
                </a>
            </div>
        </div>

        <!-- Top tiles -->
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Candidates</div>
                        <div class="h4 mb-0"><?= number_format((int)($counts['candidates'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Employers</div>
                        <div class="h4 mb-0"><?= number_format((int)($counts['employers'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Recruiters</div>
                        <div class="h4 mb-0"><?= number_format((int)($counts['recruiters'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Open Jobs</div>
                        <div class="h4 mb-0"><?= number_format((int)($counts['jobs_open'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">Registrations (daily)</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/metrics/export?type=registrations&from=<?= $from ?>&to=<?= $to ?>">Download CSV</a>
                        </div>
                        <canvas id="regChart" height="140"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">Jobs Posted (daily)</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/metrics/export?type=jobs&from=<?= $from ?>&to=<?= $to ?>">Download CSV</a>
                        </div>
                        <canvas id="jobsChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">Applications (daily)</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/metrics/export?type=applications&from=<?= $from ?>&to=<?= $to ?>">Download CSV</a>
                        </div>
                        <canvas id="appsDailyChart" height="140"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">Applications by Status</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/admin/metrics/export?type=applications_status">Download CSV</a>
                        </div>
                        <canvas id="appsStatusChart" height="140"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const LABELS = <?= json_encode($labels) ?>;

    // Line: Registrations
    new Chart(document.getElementById('regChart'), {
        type: 'line',
        data: {
            labels: LABELS,
            datasets: [{
                    label: 'Candidates',
                    data: <?= json_encode($reg['candidates'] ?? []) ?>,
                    tension: .25
                },
                {
                    label: 'Employers',
                    data: <?= json_encode($reg['employers']  ?? []) ?>,
                    tension: .25
                },
                {
                    label: 'Recruiters',
                    data: <?= json_encode($reg['recruiters'] ?? []) ?>,
                    tension: .25
                },
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Bar: Jobs per day
    new Chart(document.getElementById('jobsChart'), {
        type: 'bar',
        data: {
            labels: LABELS,
            datasets: [{
                label: 'Jobs',
                data: <?= json_encode($jobs) ?>
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Line: Applications per day
    new Chart(document.getElementById('appsDailyChart'), {
        type: 'line',
        data: {
            labels: LABELS,
            datasets: [{
                label: 'Applications',
                data: <?= json_encode($apps) ?>,
                tension: .25
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // Doughnut: Applications by Status (all-time snapshot)
    const statusLabels = <?= json_encode(array_keys($appsStatus)) ?>;
    const statusData = <?= json_encode(array_values($appsStatus)) ?>;
    new Chart(document.getElementById('appsStatusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>