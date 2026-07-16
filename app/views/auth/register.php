<h4 class="text-center mb-4">Create Account</h4>

<form action="<?= BASE_URL ?>/register" method="POST" id="registerForm">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <!-- Full Name -->
    <div class="mb-3">
        <label for="full_name" class="form-label">Full Name</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-user"></i>
            </span>
            <input type="text" class="form-control" id="full_name" name="full_name" 
                   placeholder="Enter your full name" required>
        </div>
    </div>
    
    <!-- Username -->
    <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-at"></i>
            </span>
            <input type="text" class="form-control" id="username" name="username" 
                   placeholder="Choose a username" required minlength="3" maxlength="50">
        </div>
        <small class="text-muted" id="username-feedback"></small>
    </div>
    
    <!-- Email -->
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-envelope"></i>
            </span>
            <input type="email" class="form-control" id="email" name="email" 
                   placeholder="Enter your email" required>
        </div>
        <small class="text-muted" id="email-feedback"></small>
    </div>
    
    <!-- Phone -->
    <div class="mb-3">
        <label for="phone" class="form-label">Phone Number</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-phone"></i>
            </span>
            <input type="tel" class="form-control" id="phone" name="phone" 
                   placeholder="e.g., 0712345678" required>
        </div>
        <small class="text-muted">Format: 07XXXXXXXX or +2557XXXXXXXX</small>
    </div>
    
    <!-- Password -->
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group position-relative">
            <span class="input-group-text">
                <i class="fas fa-lock"></i>
            </span>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Create a password" required minlength="8">
            <i class="fas fa-eye password-toggle position-absolute" 
               style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10;"></i>
        </div>
        <small class="text-muted">Minimum 8 characters with uppercase, lowercase, and numbers</small>
    </div>
    
    <!-- Confirm Password -->
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <div class="input-group position-relative">
            <span class="input-group-text">
                <i class="fas fa-check-circle"></i>
            </span>
            <input type="password" class="form-control" id="password_confirmation" 
                   name="password_confirmation" placeholder="Confirm your password" required>
            <i class="fas fa-eye password-toggle position-absolute" 
               style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10;"></i>
        </div>
    </div>
    
    <!-- Terms -->
    <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
        <label class="form-check-label" for="terms">
            I agree to the <a href="#" class="auth-link">Terms of Service</a> and 
            <a href="#" class="auth-link">Privacy Policy</a>
        </label>
    </div>
    
    <!-- Submit Button -->
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-user-plus me-2"></i> Register
    </button>
    
    <!-- Login Link -->
    <div class="text-center mt-4">
        <p class="mb-0">Already have an account? <a href="<?= BASE_URL ?>/login" class="auth-link">Login Here</a></p>
    </div>
</form>

<script>
// Real-time username availability check
document.getElementById('username').addEventListener('blur', function() {
    const username = this.value.trim();
    const feedback = document.getElementById('username-feedback');
    
    if (username.length >= 3) {
        fetch(`/verify-username?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    feedback.innerHTML = '<i class="fas fa-check text-success"></i> Username available';
                    feedback.className = 'text-success';
                } else {
                    feedback.innerHTML = '<i class="fas fa-times text-danger"></i> ' + data.message;
                    feedback.className = 'text-danger';
                }
            });
    }
});

// Real-time email availability check
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value.trim();
    const feedback = document.getElementById('email-feedback');
    
    if (email.length > 0) {
        fetch(`/verify-email?email=${encodeURIComponent(email)}`)
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    feedback.innerHTML = '<i class="fas fa-check text-success"></i> Email available';
                    feedback.className = 'text-success';
                } else {
                    feedback.innerHTML = '<i class="fas fa-times text-danger"></i> ' + data.message;
                    feedback.className = 'text-danger';
                }
            });
    }
});

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strength = document.createElement('div');
    strength.id = 'password-strength';
    
    let score = 0;
    if (password.length >= 8) score++;
    if (password.match(/[a-z]/)) score++;
    if (password.match(/[A-Z]/)) score++;
    if (password.match(/[0-9]/)) score++;
    if (password.match(/[^a-zA-Z0-9]/)) score++;
    
    let level = '';
    let color = '';
    if (score <= 2) { level = 'Weak'; color = 'danger'; }
    else if (score <= 3) { level = 'Fair'; color = 'warning'; }
    else if (score <= 4) { level = 'Good'; color = 'info'; }
    else { level = 'Strong'; color = 'success'; }
    
    const existing = document.getElementById('password-strength');
    if (existing) existing.remove();
    
    if (password.length > 0) {
        strength.id = 'password-strength';
        strength.className = `text-${color} small`;
        strength.innerHTML = `Password strength: ${level}`;
        this.parentElement.parentElement.appendChild(strength);
    }
});

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmation = document.getElementById('password_confirmation').value;
    const terms = document.getElementById('terms').checked;
    
    if (password !== confirmation) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
        return false;
    }
    
    if (!terms) {
        e.preventDefault();
        alert('Please agree to the Terms of Service and Privacy Policy.');
        return false;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }
});
</script>