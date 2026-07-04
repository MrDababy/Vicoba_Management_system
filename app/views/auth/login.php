<h4 class="text-center mb-4">Welcome Back</h4>

<form action="/login" method="POST" id="loginForm">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <!-- Username/Email -->
    <div class="mb-3">
        <label for="username" class="form-label">Username or Email</label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-user"></i>
            </span>
            <input type="text" class="form-control" id="username" name="username" 
                   placeholder="Enter username or email" required autofocus>
        </div>
    </div>
    
    <!-- Password -->
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group position-relative">
            <span class="input-group-text">
                <i class="fas fa-lock"></i>
            </span>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Enter password" required>
            <i class="fas fa-eye password-toggle position-absolute" 
               style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10;"></i>
        </div>
    </div>
    
    <!-- Remember Me & Forgot Password -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Remember Me</label>
        </div>
        <a href="/forgot-password" class="auth-link small">Forgot Password?</a>
    </div>
    
    <!-- Submit Button -->
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-sign-in-alt me-2"></i> Login
    </button>
    
    <!-- Register Link -->
    <div class="text-center mt-4">
        <p class="mb-0">Don't have an account? <a href="/register" class="auth-link">Register Here</a></p>
    </div>
</form>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    
    if (!username || !password) {
        e.preventDefault();
        alert('Please enter both username/email and password.');
        return false;
    }
});
</script>