<?php
/**
 * Member Form Partial
 * 
 * Reusable form for creating and editing members.
 * 
 * @var array $member Member data (null for create)
 * @var bool $is_edit Whether this is an edit form
 * @var string $csrf_token CSRF token
 */
?>

<form action="<?php echo $is_edit ? '/members/' . $member['id'] : '/members'; ?>" 
      method="POST" enctype="multipart/form-data" id="memberForm">
    
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <?php if ($is_edit): ?>
    <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Personal Information</h6>
            
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="full_name" name="full_name" 
                       value="<?php echo htmlspecialchars($member['full_name'] ?? ''); ?>" 
                       required minlength="3" maxlength="100">
            </div>
            
            <div class="mb-3">
                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($member['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($member['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($member['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                       value="<?php echo htmlspecialchars($member['date_of_birth'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="national_id" class="form-label">National ID <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="national_id" name="national_id" 
                       value="<?php echo htmlspecialchars($member['national_id'] ?? ''); ?>" 
                       required minlength="12" maxlength="14" pattern="\d{12,14}">
                <small class="text-muted">Enter 12-14 digits (e.g., 19850110231234)</small>
            </div>
            
            <div class="mb-3">
                <label for="occupation" class="form-label">Occupation</label>
                <input type="text" class="form-control" id="occupation" name="occupation" 
                       value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>" maxlength="100">
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Contact Information</h6>
            
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" 
                       required pattern="^(?:\+255|0)[67]\d{8}$">
                <small class="text-muted">Format: 07XXXXXXXX or +2557XXXXXXXX</small>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Physical Address</label>
                <textarea class="form-control" id="address" name="address" rows="3" maxlength="500"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <!-- Joining Information -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Joining Information</h6>
            
            <div class="mb-3">
                <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="joining_date" name="joining_date" 
                       value="<?php echo htmlspecialchars($member['joining_date'] ?? date('Y-m-d')); ?>" required>
            </div>
            
            <?php if ($is_edit): ?>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="Active" <?php echo ($member['status'] ?? '') == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($member['status'] ?? '') == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo ($member['status'] ?? '') == 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="Defaulted" <?php echo ($member['status'] ?? '') == 'Defaulted' ? 'selected' : ''; ?>>Defaulted</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Profile Picture -->
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Profile Picture</h6>
            
            <?php if ($is_edit && !empty($member['profile_pic'])): ?>
            <div class="mb-3">
                <img src="/uploads/profiles/<?php echo htmlspecialchars($member['profile_pic']); ?>" 
                     class="img-thumbnail" width="100" height="100" 
                     alt="Current Profile Picture">
                <small class="text-muted d-block mt-1">Current picture</small>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="profile_pic" class="form-label">Upload Profile Picture</label>
                <input type="file" class="form-control" id="profile_pic" name="profile_pic" 
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="text-muted">Max size: 5MB. Allowed: JPEG, PNG, GIF, WebP</small>
            </div>
            
            <div id="imagePreview" class="mt-2"></div>
        </div>
        
        <!-- Submit Buttons -->
        <div class="col-12">
            <hr>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> 
                    <?php echo $is_edit ? 'Update Member' : 'Register Member'; ?>
                </button>
                <a href="/members" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
// Profile picture preview
document.getElementById('profile_pic')?.addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-thumbnail';
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            preview.appendChild(img);
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Form validation
document.getElementById('memberForm')?.addEventListener('submit', function(e) {
    const fullName = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const nationalId = document.getElementById('national_id').value.trim();
    
    // Validate full name
    if (fullName.length < 3) {
        e.preventDefault();
        alert('Full name must be at least 3 characters.');
        document.getElementById('full_name').focus();
        return false;
    }
    
    // Validate email
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        document.getElementById('email').focus();
        return false;
    }
    
    // Validate phone
    if (phone && !isValidPhone(phone)) {
        e.preventDefault();
        alert('Please enter a valid phone number (07XXXXXXXX or +2557XXXXXXXX).');
        document.getElementById('phone').focus();
        return false;
    }
    
    // Validate national ID
    if (nationalId && !/^\d{12,14}$/.test(nationalId)) {
        e.preventDefault();
        alert('National ID must be 12-14 digits.');
        document.getElementById('national_id').focus();
        return false;
    }
    
    return true;
});

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
    return /^(?:\+255|0)[67]\d{8}$/.test(phone);
}
</script>