<h4 class="text-center mb-4">Create New Password</h4>

<p class="text-muted text-center mb-4">
    Enter your new password below.
</p>

<form action="/reset-password" method="POST">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="token" value="<?php echo $token; ?>">
    
    <!-- New Password -->
    <div class="mb-3">
        <label for="password" class="form-label">New Password</label>
        <div class="input-group position-relative">
            <span class="input-group-text">
                <i class="fas fa-lock"></i>
            </span>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Enter new password" required minlength="8">
            <i class="fas fa-eye password-toggle position-absolute" 
               style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10;"></i>
        </div>
        <small class="text-muted">Minimum 8 characters with uppercase, lowercase, and numbers</small>
    </div>
    
    <!-- Confirm Password -->
    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <div class="input-group position-relative">
            <span class="input-group-text">
                <i class="fas fa-check-circle"></i>
            </span>
            <input type="password" class="form-control" id="password_confirmation" 
                   name="password_confirmation" placeholder="Confirm new password" required>
            <i class="fas fa-eye password-toggle position-absolute" 
               style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10;"></i>
        </div>
    </div>
    
    <!-- Submit Button -->
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-save me-2"></i> Reset Password
    </button>
    
    <!-- Login Link -->
    <div class="text-center mt-4">
        <p class="mb-0">Remember your password? <a href="/login" class="auth-link">Login Here</a></p>
    </div>
</form>

<script>
document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmation = document.getElementById('password_confirmation').value;
    
    if (password !== confirmation) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
        return false;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }
});
</script>