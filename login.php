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
        if ($user['role'] === 'admin') {
            $error = 'Please use the Admin Login page for administrative access.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
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
    <title>Login — CAVENDIA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sage: #A3B18A;
            --sage-dark: #8A9A6D;
            --cream: #F1F2EE;
            --forest: #1B4332;
            --white: #FFFFFF;
            --text-muted: #6B7C6D;
            --border: #D8DDD3;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            min-height: 100vh;
            display: flex;
            color: var(--forest);
        }

        /* ── Left Pane: Matcha Cake Gallery ── */
        .left-pane {
            flex: 1;
            position: relative;
            overflow: hidden;
            padding: 0;
        }

        .hero-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        /* ── Right Pane: Login Form ── */
        .right-pane {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--cream);
        }
        .form-card {
            background: var(--white);
            border-radius: 28px;
            padding: 52px 48px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 2px 8px rgba(27,67,50,0.04), 0 8px 32px rgba(27,67,50,0.06);
        }
        .form-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .form-header h1 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--forest);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        .form-header p {
            font-size: 0.9rem;
            font-weight: 300;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 400;
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            transition: all 0.3s ease;
            color: var(--forest);
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--sage);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(163,177,138,0.12);
        }
        .form-group input::placeholder {
            color: #B8C9B0;
            font-weight: 300;
        }
        .password-wrap {
            position: relative;
        }
        .password-wrap input {
            padding-right: 44px;
        }
        .toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
        }
        .toggle-eye:hover {
            color: var(--forest);
        }
        .forgot-link {
            text-align: right;
            margin-top: 4px;
        }
        .forgot-link a {
            font-size: 0.78rem;
            font-weight: 400;
            color: var(--sage-dark);
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link a:hover { color: var(--forest); }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--sage);
            color: var(--white);
            border: none;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: var(--forest);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(27,67,50,0.2);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 400;
        }
        .alert-error {
            background: #FDE8E8;
            color: #B91C1C;
            border: 1px solid #FDCACA;
        }

        .form-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }
        .form-footer p {
            font-size: 0.85rem;
            font-weight: 300;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .form-footer a {
            color: var(--forest);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .form-footer a:hover { color: var(--sage); }
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            font-size: 0.78rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.2s;
        }
        .back-home:hover { color: var(--forest); }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(27,67,50,0.25);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--white);
            border-radius: 24px;
            padding: 44px;
            text-align: center;
            max-width: 360px;
            width: 90%;
            box-shadow: 0 24px 64px rgba(27,67,50,0.15);
            animation: modalPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-box .check-circle {
            width: 64px; height: 64px;
            background: #E8F5E9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .modal-box .check-circle i { font-size: 1.6rem; color: #4CAF50; }
        .modal-box h3 { font-size: 1.15rem; font-weight: 600; color: var(--forest); margin-bottom: 8px; }
        .modal-box p { font-size: 0.88rem; font-weight: 300; color: var(--text-muted); margin-bottom: 28px; }
        .modal-box .btn-continue {
            background: var(--sage); color: #fff; border: none; padding: 12px 36px;
            border-radius: 12px; font-size: 0.9rem; font-weight: 500; cursor: pointer;
            transition: background 0.2s;
        }
        .modal-box .btn-continue:hover { background: var(--forest); }

        /* Responsive */
        @media (max-width: 960px) {
            .left-pane { display: none; }
            .right-pane { padding: 24px; }
            .form-card { padding: 36px 28px; }
        }
    </style>
</head>
<body>
    <div class="left-pane">
        <img src="v2-r1qco-jw8t9-700x1215.jpg" alt="Event Hero" class="hero-image">
    </div>

    <div class="right-pane">
        <div class="form-card">
            <div class="form-header">
                <h1>Login</h1>
                <p>Welcome back to CAVENDIA</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-eye" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="forgot-link">
                        <a href="#">Forgot Password?</a>
                    </div>
                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Sign Up</a></p>
                <a href="index.php" class="back-home">
                    <i class="fas fa-arrow-left"></i> Back to home
                </a>
            </div>
    </div>

    <div class="modal-overlay <?php echo $success ? 'active' : ''; ?>" id="successModal">
        <div class="modal-box">
            <div class="check-circle"><i class="fas fa-check"></i></div>
            <h3>Login successful</h3>
            <p>Welcome back! Let's get started.</p>
            <button class="btn-continue" onclick="window.location.href='dashboard.php'">Continue</button>
        </div>

    <script>
        function togglePassword(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
