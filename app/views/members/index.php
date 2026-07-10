<?php
/**
 * Members List View
 * 
 * Displays a paginated list of members with search and filter options.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Members</h4>
        <p class="text-muted mb-0">Manage all VICOBA members</p>
    </div>
    
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='<?= BASE_URL ?>/members/export'">
            <i class="fas fa-file-export me-1"></i> Export
        </button>
        <?php if ($auth->hasRole(['Admin', 'Treasurer', 'Secretary'])): ?>
        <a href="<?= BASE_URL ?>/members/create" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus me-1"></i> Add Member
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Total Members</p>
                    <h5 class="fw-bold mb-0"><?php echo number_format($stats['total']); ?></h5>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Active Members</p>
                    <h5 class="fw-bold mb-0"><?php echo number_format($stats['active']); ?></h5>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Male / Female</p>
                    <h5 class="fw-bold mb-0">
                        <?php echo $stats['gender']['Male'] ?? 0; ?> / <?php echo $stats['gender']['Female'] ?? 0; ?>
                    </h5>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-venus-mars"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-light rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted small mb-0">Per Page</p>
                    <select class="form-select form-select-sm" id="perPageSelect" style="width: auto;">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-list-ul"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card dashboard-card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/members" id="searchForm" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by name, email, phone..." 
                           value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo ($filters['status'] ?? '') == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($filters['status'] ?? '') == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo ($filters['status'] ?? '') == 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="Defaulted" <?php echo ($filters['status'] ?? '') == 'Defaulted' ? 'selected' : ''; ?>>Defaulted</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="gender">
                    <option value="">All Gender</option>
                    <option value="Male" <?php echo ($filters['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($filters['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($filters['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="joining_date_from" 
                       placeholder="From" value="<?php echo $filters['joining_date_from'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="joining_date_to" 
                       placeholder="To" value="<?php echo $filters['joining_date_to'] ?? ''; ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter me-1"></i> Apply Filters
                </button>
                <a href="/members" class="btn btn-secondary btn-sm">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Members Table -->
<div class="card dashboard-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Member</th>
                        <th>Details</th>
                        <th>Contact</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">No members found. <?php if ($auth->hasRole(['Admin', 'Treasurer', 'Secretary'])): ?>
                                <a href="/members/create">Add your first member</a>
                            <?php endif; ?></p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($members as $index => $member): ?>
                    <tr>
                        <td>
                            <?php echo $pagination['from'] + $index; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if (!empty($member['profile_pic'])): ?>
                                <img src="/uploads/profiles/<?php echo htmlspecialchars($member['profile_pic']); ?>" 
                                     class="rounded-circle me-2" width="40" height="40" 
                                     alt="<?php echo htmlspecialchars($member['full_name']); ?>">
                                <?php else: ?>
                                <div class="avatar-sm me-2">
                                    <?php echo strtoupper(substr($member['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold small">
                                        <?php echo htmlspecialchars($member['full_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($member['member_no']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <div><?php echo htmlspecialchars($member['gender'] ?? 'N/A'); ?></div>
                                <div class="text-muted"><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <div><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></div>
                            </div>
                        </td>
                        <td class="small">
                            <?php echo date('M d, Y', strtotime($member['joining_date'] ?? 'now')); ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'active';
                            if ($member['status'] === 'Inactive') $statusClass = 'inactive';
                            if ($member['status'] === 'Suspended') $statusClass = 'suspended';
                            if ($member['status'] === 'Defaulted') $statusClass = 'danger';
                            ?>
                            <span class="badge badge-status <?php echo $statusClass; ?>">
                                <?php echo $member['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="/members/<?php echo $member['id']; ?>" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($auth->hasRole(['Admin', 'Treasurer', 'Secretary'])): ?>
                                <a href="/members/<?php echo $member['id']; ?>/edit" class="btn btn-outline-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($auth->isAdmin()): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['full_name']); ?>')"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($members)): ?>
        <div class="card-footer bg-transparent">
            <?php echo \App\Helpers\Pagination::render($pagination, '/members', $filters); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteMemberName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone. All associated records will be affected.</p>
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

<script>
// Per page change
document.getElementById('perPageSelect')?.addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', this.value);
    window.location.href = url.toString();
});

// Delete member
let deleteId = null;

function deleteMember(id, name) {
    deleteId = id;
    document.getElementById('deleteMemberName').textContent = name;
    document.getElementById('deleteForm').action = '/members/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function confirmDelete() {
    document.getElementById('deleteForm').submit();
}

// Auto-submit search on filter change
document.querySelectorAll('#searchForm select, #searchForm input[type="date"]').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
});

// Auto-submit search on Enter key
document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchForm').submit();
    }
});
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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

.stat-icon.info {
    background: rgba(66, 153, 225, 0.1);
    color: #4299e1;
}

.stat-icon.warning {
    background: rgba(237, 137, 54, 0.1);
    color: #ed8936;
}

[data-bs-theme="dark"] .stat-card {
    background: #2d2d3f !important;
}

[data-bs-theme="dark"] .table-light {
    background: #3d3d5f;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .table-light th {
    color: #e2e8f0;
}

[data-bs-theme="dark"] .stat-icon.primary {
    background: rgba(102, 126, 234, 0.2);
}

[data-bs-theme="dark"] .stat-icon.success {
    background: rgba(72, 187, 120, 0.2);
}

[data-bs-theme="dark"] .stat-icon.info {
    background: rgba(66, 153, 225, 0.2);
}

[data-bs-theme="dark"] .stat-icon.warning {
    background: rgba(237, 137, 54, 0.2);
}
</style>                            <a class="dropdown-item" href="<?= BASE_URL ?>/profile"></a>