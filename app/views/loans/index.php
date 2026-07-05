<?php
/**
 * Loans Index View
 * 
 * Displays loan list with filters and statistics.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Loan Management</h4>
        <p class="text-muted mb-0">Manage member loans and applications</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$is_member || $is_admin): ?>
        <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='/loans/export?<?php echo http_build_query($filters); ?>'">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <?php endif; ?>
        <?php if ($is_member || $is_admin): ?>
        <a href="/loans/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle me-1"></i> Apply Loan
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <?php if ($is_member): ?>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Loans</p>
                    <h5 class="fw-bold mb-0"><?php echo $stats['total'] ?? 0; ?></h5>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Loans</p>
                    <h5 class="fw-bold mb-0"><?php echo $stats['total_loans']; ?></h5>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Pending</p>
                    <h5 class="fw-bold mb-0 text-warning"><?php echo $stats['pending']; ?></h5>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Active</p>
                    <h5 class="fw-bold mb-0 text-success"><?php echo $stats['active']; ?></h5>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Amount</p>
                    <h5 class="fw-bold mb-0"><?php echo number_format($stats['total_amount'], 2); ?></h5>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" action="/loans" id="filterForm" class="row g-3">
            <?php if (!$is_member): ?>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo ($filters['status'] ?? '') == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo ($filters['status'] ?? '') == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo ($filters['status'] ?? '') == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Active" <?php echo ($filters['status'] ?? '') == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Completed" <?php echo ($filters['status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Defaulted" <?php echo ($filters['status'] ?? '') == 'Defaulted' ? 'selected' : ''; ?>>Defaulted</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="loan_type_id">
                    <option value="">All Types</option>
                    <?php foreach ($loan_types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo ($filters['loan_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <input type="date" class="form-control" name="from_date" 
                       value="<?php echo $filters['from_date'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="to_date" 
                       value="<?php echo $filters['to_date'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" name="search" 
                       placeholder="Search..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loans Table -->
<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan No</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loans)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-hand-holding-usd fa-2x text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">No loans found.</p>
                            <?php if ($is_member || $is_admin): ?>
                            <a href="/loans/create" class="btn btn-primary btn-sm mt-2">
                                <i class="fas fa-plus-circle me-1"></i> Apply for Loan
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?php echo htmlspecialchars($loan['loan_no']); ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2">
                                    <?php echo strtoupper(substr($loan['member_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold small">
                                        <?php echo htmlspecialchars($loan['member_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <small class="text-muted"><?php echo $loan['member_no']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($loan['loan_type_name']); ?></td>
                        <td class="fw-semibold"><?php echo number_format($loan['amount'], 2); ?></td>
                        <td><?php echo $loan['duration_months']; ?> months</td>
                        <td>
                            <?php
                            $statusClass = 'pending';
                            if ($loan['status'] === 'Approved') $statusClass = 'info';
                            if ($loan['status'] === 'Active') $statusClass = 'success';
                            if ($loan['status'] === 'Completed') $statusClass = 'completed';
                            if ($loan['status'] === 'Rejected') $statusClass = 'danger';
                            if ($loan['status'] === 'Defaulted') $statusClass = 'danger';
                            ?>
                            <span class="badge badge-status <?php echo $statusClass; ?>">
                                <?php echo $loan['status']; ?>
                            </span>
                        </td>
                        <td class="small"><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="/loans/<?php echo $loan['id']; ?>" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($is_admin && $loan['status'] === 'Pending'): ?>
                                <a href="/loans/<?php echo $loan['id']; ?>/approve" class="btn btn-outline-success" title="Approve">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($loan['status'] === 'Active' || $loan['status'] === 'Completed'): ?>
                                <a href="/loans/<?php echo $loan['id']; ?>/installments" class="btn btn-outline-info" title="Installments">
                                    <i class="fas fa-list"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($loans)): ?>
        <div class="card-footer bg-transparent">
            <?php echo \App\Helpers\Pagination::render($pagination, '/loans', $filters); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
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

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon.primary {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.stat-icon.success {
    background: rgba(72, 187, 120, 0.1);
    color: #48bb78;
}

.stat-icon.warning {
    background: rgba(237, 137, 54, 0.1);
    color: #ed8936;
}

.stat-icon.danger {
    background: rgba(245, 101, 101, 0.1);
    color: #f56565;
}

.stat-icon.info {
    background: rgba(66, 153, 225, 0.1);
    color: #4299e1;
}

[data-bs-theme="dark"] .stat-card {
    background: #2d2d3f !important;
}

[data-bs-theme="dark"] .table-light {
    background: #3d3d5f;
    color: #e2e8f0;
}
</style>