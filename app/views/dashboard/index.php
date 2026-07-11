<?php
/**
 * Dashboard View
 * 
 * Displays the main dashboard with statistics, charts, and activities.
 */
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard</h4>
        <p class="text-muted mb-0">Welcome back, <?php echo $user['full_name'] ?? 'User'; ?>!</p>
    </div>
    
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="periodFilter" style="width: auto;">
            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
            <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
        </select>
        
        <button class="btn btn-primary btn-sm" onclick="window.location.href='/dashboard/export'">
            <i class="fas fa-download me-1"></i> Export
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Members</p>
                        <h3 class="fw-bold mb-0" id="totalMembers"><?php echo number_format($stats['total_members']); ?></h3>
                        <small class="text-success"><i class="fas fa-arrow-up me-1"></i> Active: <?php echo number_format($dashboardModel->getActiveMembers()); ?></small>
                    </div>
                    <div class="card-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Savings</p>
                        <h3 class="fw-bold mb-0" id="totalSavings"><?php echo number_format($stats['total_savings'], 2); ?></h3>
                        <small class="text-muted">TSh</small>
                    </div>
                    <div class="card-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-piggy-bank fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Loans</p>
                        <h3 class="fw-bold mb-0" id="totalLoans"><?php echo number_format($stats['total_loans'], 2); ?></h3>
                        <small class="text-warning"><i class="fas fa-clock me-1"></i> Pending: <?php echo $stats['pending_loans']; ?></small>
                    </div>
                    <div class="card-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-hand-holding-usd fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Repayments</p>
                        <h3 class="fw-bold mb-0" id="totalRepayments"><?php echo number_format($stats['total_repayments'], 2); ?></h3>
                        <small class="text-muted">TSh</small>
                    </div>
                    <div class="card-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-credit-card fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Second Row Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Outstanding Fines</p>
                        <h3 class="fw-bold mb-0" id="outstandingFines"><?php echo number_format($stats['outstanding_fines'], 2); ?></h3>
                        <small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Unpaid</small>
                    </div>
                    <div class="card-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Annual Profit</p>
                        <h3 class="fw-bold mb-0" id="annualProfit"><?php echo number_format($stats['annual_profit'], 2); ?></h3>
                        <small class="text-success">TSh</small>
                    </div>
                    <div class="card-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Dividends Distributed</p>
                        <h3 class="fw-bold mb-0" id="dividendsDistributed"><?php echo number_format($stats['dividends_distributed'], 2); ?></h3>
                        <small class="text-muted">TSh</small>
                    </div>
                    <div class="card-icon bg-purple bg-opacity-10 text-purple">
                        <i class="fas fa-gift fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card dashboard-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Active Loans</p>
                        <h3 class="fw-bold mb-0"><?php echo $dashboardModel->getActiveLoans(); ?></h3>
                        <small class="text-muted">Ongoing</small>
                    </div>
                    <div class="card-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-spinner fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-8 col-lg-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Financial Overview</h6>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary active" data-chart="savings">Savings</button>
                    <button class="btn btn-outline-primary" data-chart="loans">Loans</button>
                    <button class="btn btn-outline-primary" data-chart="repayments">Repayments</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="financialChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-12">
        <div class="card dashboard-card h-100">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Loan Status Distribution</h6>
            </div>
            <div class="card-body">
                <canvas id="loanStatusChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Second Chart Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-6 col-lg-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Member Growth</h6>
            </div>
            <div class="card-body">
                <canvas id="memberGrowthChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-lg-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Monthly Income</h6>
            </div>
            <div class="card-body">
                <canvas id="incomeChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities and Top Savers -->
<div class="row g-3">
    <div class="col-xl-7 col-lg-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Recent Activities</h6>
                <a href="/audit" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="activity-list" id="activityList">
                    <?php foreach ($stats['recent_activities'] as $activity): ?>
                    <div class="activity-item d-flex align-items-start p-3 border-bottom">
                        <div class="activity-icon me-3">
                            <?php
                            $icon = 'fa-circle';
                            $color = 'text-primary';
                            switch ($activity['action']) {
                                case 'CREATE':
                                    $icon = 'fa-plus-circle';
                                    $color = 'text-success';
                                    break;
                                case 'UPDATE':
                                    $icon = 'fa-edit';
                                    $color = 'text-info';
                                    break;
                                case 'DELETE':
                                    $icon = 'fa-trash';
                                    $color = 'text-danger';
                                    break;
                                case 'LOGIN':
                                    $icon = 'fa-sign-in-alt';
                                    $color = 'text-primary';
                                    break;
                                case 'LOGOUT':
                                    $icon = 'fa-sign-out-alt';
                                    $color = 'text-secondary';
                                    break;
                                case 'APPROVE':
                                    $icon = 'fa-check-circle';
                                    $color = 'text-success';
                                    break;
                                default:
                                    $icon = 'fa-circle';
                                    $color = 'text-muted';
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?> <?php echo $color; ?> fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <p class="mb-0 small fw-semibold">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                    <span class="text-muted fw-normal">on <?php echo htmlspecialchars($activity['table_name']); ?></span>
                                </p>
                                <small class="text-muted">
                                    <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                                </small>
                            </div>
                            <p class="mb-0 small text-muted">
                                <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                            </p>
                            <small class="text-muted">
                                <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($stats['recent_activities'])): ?>
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">No recent activities</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-5 col-lg-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Top Savers</h6>
                <a href="<?php echo BASE_URL; ?>/members" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_savers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th class="text-end">Savings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_savers as $index => $saver): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            <?php echo strtoupper(substr($saver['full_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold small">
                                                <?php echo htmlspecialchars($saver['full_name'] ?? 'Unknown'); ?>
                                            </div>
                                            <small class="text-muted"><?php echo $saver['member_no']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?php echo number_format($saver['total_savings'] ?? 0, 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">No savings data available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mt-2">
    <div class="col-12">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL ?>/members/create" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Add Member
                    </a>
                    <a href="<?= BASE_URL ?>/savings/create" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> Record Savings
                    </a>
                    <a href="<?= BASE_URL ?>/loans/create" class="btn btn-warning">
                        <i class="fas fa-hand-holding-usd me-1"></i> Apply Loan
                    </a>
                    <a href="<?= BASE_URL ?>/loans/pending" class="btn btn-info">
                        <i class="fas fa-check-double me-1"></i> Approve Loans
                    </a>
                    <a href="<?= BASE_URL ?>/reports" class="btn btn-secondary">
                        <i class="fas fa-file-alt me-1"></i> Generate Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
    background: white;
}

.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
}

.dashboard-card .card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.text-purple {
    color: #9f7aea;
}

.bg-purple {
    background-color: #9f7aea;
}

.bg-purple.bg-opacity-10 {
    background-color: rgba(159, 122, 234, 0.1);
}

.activity-item {
    transition: all 0.2s ease;
}

.activity-item:hover {
    background: #f7fafc;
}

.activity-icon {
    width: 36px;
    flex-shrink: 0;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

/* Dark Mode Adjustments */
[data-bs-theme="dark"] .dashboard-card {
    background: #2d2d3f;
}

[data-bs-theme="dark"] .dashboard-card .card-header {
    border-color: #3d3d5f;
}

[data-bs-theme="dark"] .activity-item:hover {
    background: #3d3d5f;
}

[data-bs-theme="dark"] .dashboard-card .table {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .dashboard-card .table-hover tbody tr:hover {
    background: #3d3d5f;
}

/* Responsive Adjustments */
@media (max-width: 576px) {
    .dashboard-card .card-icon {
        width: 40px;
        height: 40px;
    }
    
    .dashboard-card .card-icon i {
        font-size: 1.5rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Period Filter
    document.getElementById('periodFilter')?.addEventListener('change', function() {
        window.location.href = '/dashboard/' + this.value;
    });
    
    // Initialize Charts
    initializeCharts();
    
    // Chart Type Switcher
    document.querySelectorAll('[data-chart]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[data-chart]').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            updateChart(this.dataset.chart);
        });
    });
});

let financialChart = null;
let loanStatusChart = null;
let memberGrowthChart = null;
let incomeChart = null;

function initializeCharts() {
    // Financial Chart
    const ctx1 = document.getElementById('financialChart').getContext('2d');
    financialChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($stats['monthly_savings'])); ?>,
            datasets: [
                {
                    label: 'Savings',
                    data: <?php echo json_encode(array_values($stats['monthly_savings'])); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Loan Status Chart
    const ctx2 = document.getElementById('loanStatusChart').getContext('2d');
    const loanStatusData = <?php echo json_encode($stats['loan_status_distribution']); ?>;
    const statusColors = {
        'Pending': 'rgba(255, 206, 86, 0.8)',
        'Approved': 'rgba(54, 162, 235, 0.8)',
        'Rejected': 'rgba(255, 99, 132, 0.8)',
        'Active': 'rgba(75, 192, 192, 0.8)',
        'Completed': 'rgba(153, 102, 255, 0.8)',
        'Defaulted': 'rgba(255, 159, 64, 0.8)'
    };
    
    loanStatusChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: Object.keys(loanStatusData),
            datasets: [{
                data: Object.values(loanStatusData),
                backgroundColor: Object.keys(loanStatusData).map(key => statusColors[key] || 'rgba(0,0,0,0.2)'),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    
    // Member Growth Chart
    const ctx3 = document.getElementById('memberGrowthChart').getContext('2d');
    memberGrowthChart = new Chart(ctx3, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($stats['member_growth'])); ?>,
            datasets: [{
                label: 'New Members',
                data: <?php echo json_encode(array_values($stats['member_growth'])); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Income Chart
    const ctx4 = document.getElementById('incomeChart').getContext('2d');
    const monthlyIncome = <?php echo json_encode($dashboardModel->getMonthlyIncome()); ?>;
    incomeChart = new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: Object.keys(monthlyIncome),
            datasets: [{
                label: 'Monthly Income',
                data: Object.values(monthlyIncome),
                backgroundColor: 'rgba(153, 102, 255, 0.6)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function updateChart(type) {
    if (!financialChart) return;
    
    let data;
    let label;
    
    switch(type) {
        case 'savings':
            data = <?php echo json_encode(array_values($stats['monthly_savings'])); ?>;
            label = 'Savings';
            color = 'rgba(54, 162, 235, 0.6)';
            borderColor = 'rgba(54, 162, 235, 1)';
            break;
        case 'loans':
            data = <?php echo json_encode(array_values($stats['monthly_loans'])); ?>;
            label = 'Loans';
            color = 'rgba(255, 99, 132, 0.6)';
            borderColor = 'rgba(255, 99, 132, 1)';
            break;
        case 'repayments':
            data = <?php echo json_encode(array_values($stats['monthly_repayments'])); ?>;
            label = 'Repayments';
            color = 'rgba(75, 192, 192, 0.6)';
            borderColor = 'rgba(75, 192, 192, 1)';
            break;
        default:
            return;
    }
    
    financialChart.data.datasets = [{
        label: label,
        data: data,
        backgroundColor: color,
        borderColor: borderColor,
        borderWidth: 1
    }];
    financialChart.update();
}

// Auto-refresh dashboard every 60 seconds
setInterval(function() {
    fetch('/dashboard/data', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update statistics
            document.getElementById('totalMembers').textContent = data.data.stats.total_members.toLocaleString();
            document.getElementById('totalSavings').textContent = data.data.stats.total_savings.toFixed(2);
            document.getElementById('totalLoans').textContent = data.data.stats.total_loans.toFixed(2);
            document.getElementById('totalRepayments').textContent = data.data.stats.total_repayments.toFixed(2);
            document.getElementById('outstandingFines').textContent = data.data.stats.outstanding_fines.toFixed(2);
            document.getElementById('annualProfit').textContent = data.data.stats.annual_profit.toFixed(2);
            document.getElementById('dividendsDistributed').textContent = data.data.stats.dividends_distributed.toFixed(2);
        }
    })
    .catch(error => console.error('Error refreshing dashboard:', error));
}, 60000);
</script>