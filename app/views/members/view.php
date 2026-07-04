<?php
/**
 * View Member Details
 * 
 * Displays comprehensive member information and statistics.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Member Details</h4>
        <p class="text-muted mb-0">View complete member information</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/members" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <?php if ($can_edit): ?>
        <a href="/members/<?php echo $member['id']; ?>/edit" class="btn btn-warning btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <?php endif; ?>
        <?php if ($can_delete): ?>
        <button class="btn btn-danger btn-sm" onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['full_name']); ?>')">
            <i class="fas fa-trash me-1"></i> Delete
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Section -->
    <div class="col-lg-4">
        <div class="card dashboard-card">
            <div class="card-body text-center">
                <?php if (!empty($member['profile_pic'])): ?>
                <img src="/uploads/profiles/<?php echo htmlspecialchars($member['profile_pic']); ?>" 
                     class="rounded-circle mb-3" width="150" height="150" 
                     alt="<?php echo htmlspecialchars($member['full_name']); ?>">
                <?php else: ?>
                <div class="profile-avatar mb-3">
                    <?php echo strtoupper(substr($member['full_name'] ?? 'U', 0, 2)); ?>
                </div>
                <?php endif; ?>
                
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($member['full_name']); ?></h5>
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($member['member_no']); ?></p>
                
                <?php
                $statusClass = 'active';
                if ($member['status'] === 'Inactive') $statusClass = 'inactive';
                if ($member['status'] === 'Suspended') $statusClass = 'suspended';
                if ($member['status'] === 'Defaulted') $statusClass = 'danger';
                ?>
                <span class="badge badge-status <?php echo $statusClass; ?> mb-3">
                    <?php echo $member['status']; ?>
                </span>
                
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded-3">
                            <small class="text-muted d-block">Gender</small>
                            <strong><?php echo htmlspecialchars($member['gender']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded-3">
                            <small class="text-muted d-block">Occupation</small>
                            <strong><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Personal Information -->
    <div class="col-lg-8">
        <div class="card dashboard-card mb-4">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-user me-2"></i>Personal Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Full Name</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['full_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Member Number</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['member_no']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Gender</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['gender']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Date of Birth</label>
                        <p class="fw-semibold"><?php echo date('F d, Y', strtotime($member['date_of_birth'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">National ID</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['national_id']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Occupation</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-12">
                        <label class="text-muted small">Address</label>
                        <p class="fw-semibold"><?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card dashboard-card mb-4">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-address-card me-2"></i>Contact Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Phone</label>
                        <p class="fw-semibold">
                            <i class="fas fa-phone me-2 text-muted"></i>
                            <?php echo htmlspecialchars($member['phone']); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Email</label>
                        <p class="fw-semibold">
                            <i class="fas fa-envelope me-2 text-muted"></i>
                            <?php echo htmlspecialchars($member['email']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Joining Information -->
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Joining Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Joining Date</label>
                        <p class="fw-semibold"><?php echo date('F d, Y', strtotime($member['joining_date'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Member Since</label>
                        <p class="fw-semibold">
                            <?php
                            $joinDate = new DateTime($member['joining_date']);
                            $now = new DateTime();
                            $diff = $joinDate->diff($now);
                            echo $diff->y . ' years, ' . $diff->m . ' months';
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
<div class="row g-4 mt-4">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-piggy-bank me-2"></i>Savings Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-muted d-block">Total Deposits</small>
                            <h5 class="fw-bold mb-0"><?php echo number_format($stats['savings']['total_deposits'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-muted d-block">Current Balance</small>
                            <h5 class="fw-bold mb-0"><?php echo number_format($stats['savings']['balance'], 2); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Loan Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Total</small>
                            <strong><?php echo $stats['loans']['total']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Active</small>
                            <strong class="text-success"><?php echo $stats['loans']['active']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Pending</small>
                            <strong class="text-warning"><?php echo $stats['loans']['pending']; ?></strong>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="p-2 bg-light rounded-3">
                            <small class="text-muted d-block">Total Loan Amount</small>
                            <strong><?php echo number_format($stats['loans']['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-0">
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Fines Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Total</small>
                            <strong><?php echo $stats['fines']['total']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Pending</small>
                            <strong class="text-danger"><?php echo $stats['fines']['pending']; ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 text-center">
                            <small class="text-muted d-block">Paid</small>
                            <strong class="text-success"><?php echo $stats['fines']['paid']; ?></strong>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="p-2 bg-light rounded-3">
                            <small class="text-muted d-block">Pending Amount</small>
                            <strong class="text-danger"><?php echo number_format($stats['fines']['pending_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card dashboard-card">
            <div class="card-header bg-transparent">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-line me-2"></i>Dividends Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-muted d-block">Total Earned</small>
                            <h5 class="fw-bold mb-0"><?php echo number_format($stats['dividends']['total_earned'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <small class="text-muted d-block">Paid</small>
                            <h5 class="fw-bold mb-0"><?php echo number_format($stats['dividends']['paid_earned'], 2); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

<style>
.profile-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    margin: 0 auto;
}

[data-bs-theme="dark"] .bg-light {
    background: #2d2d3f !important;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .bg-light .text-muted {
    color: #a0aec0 !important;
}
</style>

<script>
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
</script>