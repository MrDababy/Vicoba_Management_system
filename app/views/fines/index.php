<?php
/**
 * Fines Index View
 * 
 * Displays fine list with filters and statistics.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Fine Management</h4>
        <p class="text-muted mb-0">Manage member fines and payments</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='/fines/export?<?php echo http_build_query($filters); ?>'">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <?php if ($auth->isAdmin()): ?>
        <a href="/fines/types" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-cog me-1"></i> Fine Types
        </a>
        <?php endif; ?>
        <a href="/fines/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle me-1"></i> Impose Fine
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Outstanding</p>
                    <h5 class="fw-bold mb-0 text-danger"><?php echo number_format($stats['outstanding_amount'], 2); ?></h5>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Paid</p>
                    <h5 class="fw-bold mb-0 text-success"><?php echo number_format($stats['paid_amount'], 2); ?></h5>
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
                    <p class="text-muted small mb-0">Waived</p>
                    <h5 class="fw-bold mb-0 text-warning"><?php echo number_format($stats['waived_amount'], 2); ?></h5>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-hand-peace"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Fines</p>
                    <h5 class="fw-bold mb-0"><?php echo $stats['total_fines']; ?></h5>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-list"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Table - Similar to previous modules -->
<!-- ... (filter form and table) ... -->
 <!-- Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" action="/savings" id="filterForm" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="member_id">
                    <option value="">All Members</option>
                    <?php if (!empty($members)): ?>
                    <?php foreach ($members as $m): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo ($filters['member_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['full_name']); ?> (<?php echo $m['member_no']; ?>)
                    </option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="transaction_type">
                    <option value="">All Types</option>
                    <option value="Deposit" <?php echo ($filters['transaction_type'] ?? '') == 'Deposit' ? 'selected' : ''; ?>>Deposit</option>
                    <option value="Withdrawal" <?php echo ($filters['transaction_type'] ?? '') == 'Withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="from_date" 
                       placeholder="From" value="<?php echo $filters['from_date'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="to_date" 
                       placeholder="To" value="<?php echo $filters['to_date'] ?? ''; ?>">
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
