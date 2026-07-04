<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo $dark_mode ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/dashboard.css" rel="stylesheet">
    <link href="/assets/css/dark-mode.css" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 70px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --bg-light: #f8f9fa;
            --bg-dark: #1a1a2e;
            --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --border-radius: 16px;
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: var(--bg-light);
            min-height: 100vh;
        }
        
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-brand h2 {
            font-weight: 800;
            font-size: 22px;
            margin: 0;
        }
        
        .sidebar-brand h2 i {
            color: var(--primary-color);
        }
        
        .sidebar-brand small {
            display: block;
            opacity: 0.6;
            font-size: 12px;
            font-weight: 300;
        }
        
        .sidebar-menu {
            padding: 20px 15px;
        }
        
        .sidebar-menu .menu-label {
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.4;
            padding: 10px 10px 5px;
            letter-spacing: 1px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 2px;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
        }
        
        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .sidebar-menu a i {
            width: 24px;
            font-size: 18px;
            margin-right: 12px;
        }
        
        .sidebar-menu a .badge {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        /* Header Styles */
        .top-header {
            height: var(--header-height);
            background: white;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-left .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #4a5568;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .header-right .search-box {
            position: relative;
        }
        
        .header-right .search-box input {
            padding: 8px 40px 8px 20px;
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            background: #f7fafc;
            width: 250px;
            transition: all 0.3s ease;
        }
        
        .header-right .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .header-right .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        .header-right .notifications {
            position: relative;
            cursor: pointer;
        }
        
        .header-right .notifications .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #fc8181;
            font-size: 10px;
            padding: 2px 6px;
        }
        
        .header-right .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .header-right .profile-dropdown:hover {
            background: #f7fafc;
        }
        
        .header-right .profile-dropdown .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .header-right .profile-dropdown .info {
            line-height: 1.3;
        }
        
        .header-right .profile-dropdown .info .name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .header-right .profile-dropdown .info .role {
            font-size: 12px;
            color: #a0aec0;
        }
        
        /* Page Content */
        .page-content {
            padding: 30px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header-left .sidebar-toggle {
                display: block;
            }
            
            .header-right .search-box input {
                width: 150px;
            }
        }
        
        @media (max-width: 576px) {
            .header-right .search-box {
                display: none;
            }
            
            .header-right .profile-dropdown .info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <h2>
                    <i class="fas fa-hand-holding-usd me-2"></i>
                    VICOBA
                </h2>
                <small>Community Banking System</small>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-label">Main Menu</div>
                <a href="/dashboard" class="active">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="/members">
                    <i class="fas fa-users"></i> Members
                </a>
                <a href="/savings">
                    <i class="fas fa-piggy-bank"></i> Savings
                </a>
                <a href="/loans">
                    <i class="fas fa-hand-holding-usd"></i> Loans
                </a>
                <a href="/repayments">
                    <i class="fas fa-credit-card"></i> Repayments
                </a>
                <a href="/fines">
                    <i class="fas fa-exclamation-triangle"></i> Fines
                </a>
                <a href="/dividends">
                    <i class="fas fa-chart-line"></i> Dividends
                </a>
                <a href="/attendance">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
                
                <?php if ($auth->hasRole(['Admin', 'Treasurer'])): ?>
                <div class="menu-label mt-3">Reports</div>
                <a href="/reports">
                    <i class="fas fa-file-alt"></i> Reports
                </a>
                <a href="/reports/financial">
                    <i class="fas fa-file-invoice-dollar"></i> Financial Summary
                </a>
                <?php endif; ?>
                
                <?php if ($auth->isAdmin()): ?>
                <div class="menu-label mt-3">Administration</div>
                <a href="/admin/users">
                    <i class="fas fa-user-cog"></i> Users
                </a>
                <a href="/admin/settings">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="/admin/audit">
                    <i class="fas fa-history"></i> Audit Logs
                </a>
                <?php endif; ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0 fw-semibold"><?php echo $page_title ?? 'Dashboard'; ?></h5>
                </div>
                
                <div class="header-right">
                    <div class="search-box">
                        <input type="text" placeholder="Search..." id="globalSearch">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="notifications" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fa-lg"></i>
                        <span class="badge">3</span>
                    </div>
                    
                    <div class="profile-dropdown" data-bs-toggle="dropdown">
                        <div class="avatar">
                            <?php 
                            $user = $auth->user();
                            $initial = $user ? strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) : 'U';
                            echo $initial;
                            ?>
                        </div>
                        <div class="info">
                            <div class="name"><?php echo $user['full_name'] ?? 'User'; ?></div>
                            <div class="role"><?php echo $user['role'] ?? 'Member'; ?></div>
                        </div>
                        <i class="fas fa-chevron-down fa-xs text-muted ms-1"></i>
                    </div>
                    
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                        <li>
                            <a class="dropdown-item" href="/profile">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/settings">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" id="darkModeToggle">
                                <i class="fas fa-moon me-2" id="darkModeIcon"></i> 
                                <span id="darkModeText">Dark Mode</span>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="/logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
                <?php echo $content; ?>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Close sidebar on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 991 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
        
        // Dark Mode Toggle
        document.getElementById('darkModeToggle')?.addEventListener('click', function(e) {
            e.preventDefault();
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', newTheme);
            
            // Update icon and text
            const icon = document.getElementById('darkModeIcon');
            const text = document.getElementById('darkModeText');
            
            if (newTheme === 'dark') {
                icon.className = 'fas fa-sun me-2';
                text.textContent = 'Light Mode';
            } else {
                icon.className = 'fas fa-moon me-2';
                text.textContent = 'Dark Mode';
            }
            
            // Save preference via AJAX
            fetch('/dashboard/toggle-dark-mode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ dark_mode: newTheme === 'dark' })
            });
        });
        
        // Global Search
        document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query.length > 0) {
                    window.location.href = '/search?q=' + encodeURIComponent(query);
                }
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    
    <?php echo $additional_scripts ?? ''; ?>
</body>
</html>