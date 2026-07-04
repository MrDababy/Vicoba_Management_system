<?php
/**
 * Create Member View
 * 
 * Form for adding a new member.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Add New Member</h4>
        <p class="text-muted mb-0">Register a new member in the system</p>
    </div>
    <a href="/members" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to Members
    </a>
</div>

<div class="card dashboard-card">
    <div class="card-body">
        <?php include 'partials/_form.php'; ?>
    </div>
</div>