<?php
/**
 * Edit Member View
 * 
 * Form for editing an existing member.
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit Member</h4>
        <p class="text-muted mb-0">Update member information</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/members/<?php echo $member['id']; ?>" class="btn btn-info btn-sm">
            <i class="fas fa-eye me-1"></i> View
        </a>
        <a href="/members" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="card dashboard-card">
    <div class="card-body">
        <?php include 'partials/_form.php'; ?>
    </div>
</div>