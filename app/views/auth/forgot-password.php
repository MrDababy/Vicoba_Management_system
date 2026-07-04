<h4 class="text-center mb-4">Reset Password</h4>

<p class="text-muted text-center mb-4">
    Enter your email address and we'll send you a link to reset your password.
</p>

<form action="/forgot-password" method="POST">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <!-- Email -->
    <div class="mb-4">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-envelope"></i>
            </span>
            <input type="email" class="form-control" id="email" name="email" 
                   placeholder="Enter your registered email" required>
        </div>
    </div>
    
    <!-- Submit Button -->
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-paper-plane me-2"></i> Send Reset Link
    </button>
    
    <!-- Login Link -->
    <div class="text-center mt-4">
        <p class="mb-0">Remember your password? <a href="/login" class="auth-link">Login Here</a></p>
    </div>
</form>