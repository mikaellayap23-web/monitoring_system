<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged Out - RMMC Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <meta http-equiv="refresh" content="3;url=login.php">
</head>
<body>
    <div class="auth-container">
        <div class="logo-wrapper">
            <img src="Uploads/Images/armmc-logo.png" alt="RMMC Logo" class="login-logo">
            <div class="hospital-name">
                <span class="hospital-title">RMMC Monitoring System</span>
            </div>
        </div>
        
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2><i class="fas fa-sign-out-alt"></i> Logged Out</h2>
        
        <div class="alert success">
            <i class="fas fa-check-circle"></i> You have been successfully logged out.
        </div>
        
        <div class="redirect-message">
            <i class="fas fa-spinner fa-pulse"></i> Redirecting to login page...
        </div>
        
        <div class="manual-link">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Click here if not redirected</a>
        </div>
    </div>
    
    <style>
        /* Additional styles specific to logout page */
        .logout-icon {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .logout-icon i {
            font-size: 4rem;
            color: #10b981;
        }
        
        .redirect-message {
            text-align: center;
            margin: 1.5rem 0 1rem 0;
            color: #1A3D64;
            font-size: 0.9rem;
            padding: 0.75rem;
            background: #F2FCFC;
            border-radius: 12px;
        }
        
        .redirect-message i {
            margin-right: 8px;
            color: #0245A3;
        }
        
        .manual-link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .manual-link a {
            color: #0245A3;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .manual-link a:hover {
            color: #355872;
            text-decoration: underline;
        }
        
        .alert.success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert.success i {
            font-size: 1rem;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
</body>
</html>