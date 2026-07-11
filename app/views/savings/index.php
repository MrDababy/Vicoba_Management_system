<?php
/**
 * Savings Index View
 * 
 * Displays savings transactions with filters and summary.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Savings Management</h4>
        <p class="text-muted mb-0">Record and manage member savings</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='<?= BASE_URL ?>/savings/export?<?php echo http_build_query($filters); ?>'">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <a href="<?= BASE_URL ?>/savings/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle me-1"></i> Record Savings
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Deposits</p>
                    <h5 class="fw-bold mb-0 text-success"><?php echo number_format($summary['total_deposits'], 2); ?></h5>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Withdrawals</p>
                    <h5 class="fw-bold mb-0 text-danger"><?php echo number_format($summary['total_withdrawals'], 2); ?></h5>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Net Savings</p>
                    <h5 class="fw-bold mb-0 text-primary"><?php echo number_format($summary['net_savings'], 2); ?></h5>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Transactions</p>
                    <h5 class="fw-bold mb-0"><?php echo number_format($summary['total_transactions']); ?></h5>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-list"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/savings" id="filterForm" class="row g-3">
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

<!-- Transactions Table -->
<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Mode</th>
                        <th>Receipt</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-piggy-bank fa-2x text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">No savings transactions found.</p>
                            <a href="<?= BASE_URL ?>/savings/create" class="btn btn-primary btn-sm mt-2">
                                <i class="fas fa-plus-circle me-1"></i> Record First Savings
                            </a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="small">
                            <?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-2">
                                    <?php echo strtoupper(substr($transaction['member_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold small">
                                        <?php echo htmlspecialchars($transaction['member_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <small class="text-muted"><?php echo $transaction['member_no']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($transaction['transaction_type'] === 'Deposit'): ?>
                            <span class="badge bg-success">Deposit</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Withdrawal</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold <?php echo $transaction['transaction_type'] === 'Deposit' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($transaction['amount'], 2); ?>
                        </td>
                        <td><?php echo number_format($transaction['balance_after'], 2); ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_mode']); ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/savings/receipt/<?php echo $transaction['id']; ?>" target="_blank" class="text-primary">
                                <i class="fas fa-print"></i> <?php echo htmlspecialchars($transaction['receipt_no']); ?>
                            </a>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/savings/<?php echo $transaction['id']; ?>" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/savings/<?php echo $transaction['id']; ?>/edit" class="btn btn-outline-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-outline-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($transactions)): ?>
        <div class="card-footer bg-transparent">
            <?php echo \App\Helpers\Pagination::render($pagination, '/savings', $filters); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this transaction?</p>
                <p class="text-danger small">This action cannot be undone. The member's balance will be recalculated.</p>
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="_method" value="DELETE">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
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

<script>
let deleteId = null;

function deleteTransaction(id) {
    deleteId = id;
    document.getElementById('deleteForm').action = '<?= BASE_URL ?>/savings/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function confirmDelete() {
    document.getElementById('deleteForm').submit();
}

// Auto-submit on filter change
document.querySelectorAll('#filterForm select, #filterForm input[type="date"]').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>