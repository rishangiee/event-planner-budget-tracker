<?php
require_once 'config/config.php';

$error = '';
$success = false;

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password'])) {
        if ($user['role'] !== 'admin') {
            $error = 'Access denied. This account does not have administrative privileges.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $success = true;
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - CAVENDIA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f6f7f4;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Top Navigation */
        .top-nav {
            background: #ffffff;
            border-bottom: 1px solid #e8ebe3;
            padding: 14px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .top-nav .logo {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1a472a;
            letter-spacing: 1px;
        }
        .top-nav .nav-links {
            display: flex;
            gap: 28px;
        }
        .top-nav .nav-links a {
            text-decoration: none;
            color: #5a6b5c;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .top-nav .nav-links a:hover {
            color: #2d6a4f;
        }
        /* Split Screen Layout */
        .split-container {
            flex: 1;
            display: flex;
            min-height: calc(100vh - 57px);
        }
        /* Left Pane - Visual */
        .left-pane {
            flex: 1;
            background: linear-gradient(135deg, #1a472a 0%, #2d6a4f 40%, #40916c 70%, #52b788 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .left-pane::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
            top: 10%;
            left: 10%;
        }
        .left-pane::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: 15%;
            right: 15%;
        }
        .matcha-visual {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 40px;
        }
        .shield-icon {
            width: 200px;
            height: 200px;
            margin: 0 auto 30px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 2px solid rgba(255,255,255,0.15);
        }
        .shield-icon i {
            font-size: 4rem;
            color: rgba(255,255,255,0.95);
        }
        .left-pane h2 {
            color: #ffffff;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .left-pane p {
            color: rgba(255,255,255,0.85);
            font-size: 1rem;
            max-width: 320px;
            line-height: 1.6;
        }
        /* Right Pane - Form */
        .right-pane {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #f6f7f4;
        }
        .form-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 48px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 4px 24px rgba(26, 71, 42, 0.06);
        }
        .form-card .brand {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1a472a;
            text-align: center;
            margin-bottom: 28px;
            letter-spacing: 1px;
        }
        .form-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .form-header .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #1a472a;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
        .form-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 6px;
        }
        .form-header p {
            font-size: 0.9rem;
            color: #6b7c6d;
        }
        /* Notice Box */
        .notice-box {
            background: #e9f5db;
            border-left: 4px solid #2d6a4f;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .notice-box i {
            color: #2d6a4f;
            font-size: 0.9rem;
            margin-top: 2px;
        }
        .notice-box span {
            font-size: 0.8rem;
            color: #2d6a4f;
            line-height: 1.5;
        }
        /* Form Fields */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5d4c;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #dde5df;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: #fafbf9;
            transition: all 0.25s ease;
            color: #1b4332;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2d6a4f;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(45, 106, 79, 0.08);
        }
        .form-group input::placeholder {
            color: #a3b5a6;
        }
        .forgot-link {
            text-align: right;
            margin-top: 4px;
        }
        .forgot-link a {
            font-size: 0.78rem;
            color: #6b7c6d;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link a:hover {
            color: #2d6a4f;
            text-decoration: underline;
        }
        /* Button */
        .btn-primary {
            width: 100%;
            padding: 15px;
            background: #1a472a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 8px;
        }
        .btn-primary:hover {
            background: #2d6a4f;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(26, 71, 42, 0.2);
        }
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        /* Footer Links */
        .form-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef1ec;
        }
        .form-footer p {
            font-size: 0.85rem;
            color: #6b7c6d;
            margin-bottom: 10px;
        }
        .form-footer a {
            color: #1a472a;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .form-footer a:hover {
            color: #2d6a4f;
            text-decoration: underline;
        }
        .user-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            font-size: 0.8rem;
            color: #5a6b5c;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .user-link:hover {
            color: #1a472a;
        }
        .user-link i {
            font-size: 0.75rem;
        }
        /* Success Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 360px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            animation: modalPop 0.3s ease;
        }
        @keyframes modalPop {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-box .check-circle {
            width: 64px;
            height: 64px;
            background: #d8f3dc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .modal-box .check-circle i {
            font-size: 1.8rem;
            color: #2d6a4f;
        }
        .modal-box h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1b4332;
            margin-bottom: 8px;
        }
        .modal-box p {
            font-size: 0.9rem;
            color: #6b7c6d;
            margin-bottom: 24px;
        }
        .modal-box .btn-continue {
            background: #1a472a;
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .modal-box .btn-continue:hover {
            background: #2d6a4f;
        }
        /* Responsive */
        @media (max-width: 900px) {
            .left-pane { display: none; }
            .right-pane { padding: 24px; }
            .form-card { padding: 32px; }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="logo">CAVENDIA</div>
        <div class="nav-links">
            <a href="index.php">HOME</a>
            <a href="index.php#about">ABOUT</a>
            <a href="index.php#contact">CONTACT</a>
        </div>
    </nav>

    <div class="split-container">
        <!-- Left Pane -->
        <div class="left-pane">
            <div class="matcha-visual">
                <div class="shield-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2>Secure Admin Portal</h2>
                <p>Authorized personnel only. Manage events, users, and system settings.</p>
            </div>
        </div>

        <!-- Right Pane -->
        <div class="right-pane">
            <div class="form-card">
                <div class="brand">CAVENDIA</div>
                <div class="form-header">
                    <div class="admin-badge"><i class="fas fa-lock"></i> Admin Access</div>
                    <h1>Admin Access</h1>
                    <p>Sign in with your admin credentials</p>
                </div>

                <div class="notice-box">
                    <i class="fas fa-info-circle"></i>
                    <span>Notice: Administrative access is restricted to authorized personnel only.</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="adminLoginForm">
                    <div class="form-group">
                        <label>Email or Username</label>
                        <input type="text" name="email" required placeholder="Enter admin email">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Enter admin password">
                        <div class="forgot-link">
                            <a href="#">Forgot Password?</a>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Login</button>
                </form>

                <div class="form-footer">
                    <p>Not an administrator? <a href="login.php">User Login</a></p>
                    <a href="index.php" class="user-link">
                        <i class="fas fa-arrow-left"></i> Back to home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay <?php echo $success ? 'active' : ''; ?>" id="successModal">
        <div class="modal-box">
            <div class="check-circle">
                <i class="fas fa-check"></i>
            </div>
            <h3>Login successful.</h3>
            <p>Welcome back, Admin.</p>
            <button class="btn-continue" onclick="window.location.href='admin_dashboard.php'">Continue</button>
        </div>
    </div>

</body>
</html>

