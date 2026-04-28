<?php
require_once 'config/config.php';

$error = '';
$success = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $lastname = trim($_POST['lastname']);
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $name = $firstname . ' ' . $lastname;
    if (!empty($suffix)) {
        $name .= ' ' . $suffix;
    }

    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (empty($lastname) || empty($firstname)) {
        $error = 'Last Name and First Name are required';
    } elseif (strpos($email, '@gmail.com') === false) {
        $error = 'Email must be a Gmail address (@gmail.com)';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email already registered';
        } else {
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO users 
                (email, name, lastname, firstname, middlename, suffix, address, contact_number, password, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')");
            
            if ($stmt->execute([$email, $name, $lastname, $firstname, $middlename ?: null, $suffix ?: null, $address, $contact_number, $hashedPassword])) {
                $success = 'Registration successful! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — CAVENDIA</title>
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
            --error: #d32f2f;
            --error-bg: #fde8e8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            min-height: 100vh;
            display: flex;
            color: var(--forest);
        }

        .left-pane {
            flex: 1;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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

        .left-pane .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.4;
        }
        .left-pane .orb-1 {
            width: 360px; height: 360px;
            background: radial-gradient(circle, #DCEDC8 0%, transparent 70%);
            top: -8%; right: -10%;
        }
        .left-pane .orb-2 {
            width: 280px; height: 280px;
            background: radial-gradient(circle, #F1F8E9 0%, transparent 70%);
            bottom: 10%; left: -8%;
        }
        .left-pane .orb-3 {
            width: 200px; height: 200px;
            background: radial-gradient(circle, #C5E1A5 0%, transparent 70%);
            top: 45%; right: 25%;
            opacity: 0.3;
        }

        .cake-scene {
            position: relative;
            z-index: 2;
            width: 400px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cake-plate {
            width: 320px;
            height: 220px;
            background: radial-gradient(ellipse at 40% 35%, #ffffff 0%, #f5f5f0 45%, #e8e8e0 100%);
            border-radius: 50%;
            box-shadow: 0 25px 70px rgba(27,67,50,0.16), inset 0 -6px 20px rgba(0,0,0,0.04);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cakes-pair {
            position: relative;
            width: 240px;
            height: 160px;
        }

        .cake-roll {
            position: absolute;
            width: 80px;
            height: 110px;
            left: 35px;
            bottom: 20px;
            background: linear-gradient(90deg, #81C784 0%, #A5D6A7 30%, #C8E6C9 50%, #A5D6A7 70%, #81C784 100%);
            border-radius: 40px 40px 16px 16px;
            box-shadow: inset 0 -8px 16px rgba(0,0,0,0.08), 0 8px 20px rgba(0,0,0,0.08);
        }
        .cake-roll::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 35px;
            background: linear-gradient(180deg, #E8F5E9 0%, #C8E6C9 100%);
            border-radius: 40px 40px 0 0;
        }
        .cake-roll::after {
            content: '';
            position: absolute;
            top: 28px; left: 10%; right: 10%;
            height: 2px;
            background: rgba(255,255,255,0.4);
            border-radius: 2px;
        }

        .cake-slice {
            position: absolute;
            width: 90px;
            height: 90px;
            right: 30px;
            bottom: 25px;
            background: linear-gradient(180deg, #C8E6C9 0%, #A5D6A7 35%, #81C784 65%, #66BB6A 100%);
            border-radius: 16px 16px 20px 20px;
            box-shadow: inset 0 -6px 12px rgba(0,0,0,0.08), 0 6px 16px rgba(0,0,0,0.08);
        }
        .cake-slice::before {
            content: '';
            position: absolute;
            top: 18px; left: 12%; right: 12%;
            height: 2px;
            background: rgba(255,255,255,0.35);
            border-radius: 2px;
            box-shadow: 0 22px 0 rgba(255,255,255,0.3);
        }

        .rosette {
            position: absolute;
            background: radial-gradient(circle at 35% 35%, #ffffff 0%, #f5f3ed 70%, #e8e4dc 100%);
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .rosette-1 { width: 18px; height: 16px; top: 48px; left: 55px; }
        .rosette-2 { width: 14px; height: 12px; top: 52px; left: 80px; }
        .rosette-3 { width: 16px; height: 14px; top: 42px; right: 55px; }
        .rosette-4 { width: 12px; height: 10px; top: 50px; right: 40px; }

        .dust {
            position: absolute;
            width: 4px; height: 4px;
            background: #4CAF50;
            border-radius: 50%;
            opacity: 0.35;
        }
        .dust-1 { top: 55px; left: 100px; }
        .dust-2 { top: 48px; left: 130px; opacity: 0.25; width: 3px; height: 3px; }
        .dust-3 { top: 60px; right: 95px; }
        .dust-4 { top: 52px; right: 115px; opacity: 0.2; width: 3px; height: 3px; }

        .utensil {
            position: absolute;
            background: linear-gradient(135deg, #D4AF37 0%, #C5A028 50%, #B8941F 100%);
            border-radius: 2px;
            opacity: 0.6;
        }
        .fork {
            width: 3px; height: 55px;
            bottom: 25px; left: 18px;
            transform: rotate(-12deg);
        }
        .fork::before {
            content: '';
            position: absolute;
            top: -8px; left: -4px;
            width: 11px; height: 12px;
            background: linear-gradient(135deg, #D4AF37 0%, #C5A028 100%);
            border-radius: 2px 2px 0 0;
            opacity: 0.8;
        }

        .right-pane {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--cream);
            overflow-y: auto;
        }
        .form-card {
            background: var(--white);
            border-radius: 28px;
            padding: 44px 48px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 2px 8px rgba(27,67,50,0.04), 0 8px 32px rgba(27,67,50,0.06);
        }
        .form-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--forest);
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .form-header p {
            font-size: 0.88rem;
            font-weight: 300;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 16px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 40px 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.92rem;
            font-weight: 400;
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            transition: all 0.3s ease;
            color: var(--forest);
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--sage);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(163,177,138,0.12);
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #B8C9B0;
            font-weight: 300;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 64px;
            padding-right: 14px;
        }

        /* Error styles */
        .form-group input.error,
        .form-group textarea.error {
            border-color: var(--error);
            background: var(--error-bg);
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.08);
        }
        .form-group input.error:focus,
        .form-group textarea.error:focus {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.12);
        }

        .error-icon {
            display: none;
            position: absolute;
            right: 12px;
            top: 31px;
            width: 18px;
            height: 18px;
            background: var(--error);
            color: var(--white);
            border-radius: 50%;
            font-size: 0.7rem;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        .form-group.has-error .error-icon {
            display: flex;
        }
        .form-group.has-error input,
        .form-group.has-error textarea {
            border-color: var(--error);
            padding-right: 40px;
        }
        .form-group.has-error input:focus,
        .form-group.has-error textarea:focus {
            border-color: var(--error);
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.12);
        }

        .error-msg {
            display: none;
            font-size: 0.75rem;
            color: var(--error);
            margin-top: 4px;
            font-weight: 400;
        }
        .form-group.has-error .error-msg {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .optional-label::after {
            content: ' (optional)';
            font-weight: 300;
            color: #B8C9B0;
            text-transform: none;
            font-size: 0.68rem;
        }

        .password-wrap {
            position: relative;
        }
        .password-wrap input {
            padding-right: 74px;
        }
        .password-wrap.has-error input {
            padding-right: 74px;
        }
        .toggle-eye {
            position: absolute;
            right: 38px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
            z-index: 2;
        }
        .toggle-eye:hover {
            color: var(--forest);
        }
        .password-wrap .error-icon {
            right: 38px;
            top: 50%;
            transform: translateY(-50%);
        }

        .btn-signup {
            width: 100%;
            padding: 14px;
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
        .btn-signup:hover {
            background: var(--forest);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(27,67,50,0.2);
        }
        .btn-signup:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 400;
        }
        .alert-error {
            background: #FDE8E8;
            color: #B91C1C;
            border: 1px solid #FDCACA;
        }
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
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
            margin-top: 6px;
            font-size: 0.78rem;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.2s;
        }
        .back-home:hover { color: var(--forest); }

        @media (max-width: 960px) {
            .left-pane { display: none; }
            .right-pane { padding: 24px; }
            .form-card { padding: 32px 24px; }
            .form-row { grid-template-columns: 1fr; }
            .form-row-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Left Pane -->
<div class="left-pane">
    <img src="v2-r1qco-jw8t9-700x1215.jpg" alt="Event Hero" class="hero-image">
</div>

<!-- Right Pane -->
<div class="right-pane">
    <div class="form-card">
        <div class="form-header">
            <h1>Create Your Account</h1>
            <p>Sign up to start planning with CAVENDIA</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="signupForm" novalidate>
            <div class="form-row-3">
                <div class="form-group" data-validate="required">
                    <label>Last Name *</label>
                    <input type="text" name="lastname" placeholder="Doe"
                           value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>">
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                    <span class="error-msg">Last name is required</span>
                </div>
                <div class="form-group" data-validate="required">
                    <label>First Name *</label>
                    <input type="text" name="firstname" placeholder="Jane"
                           value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>">
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                    <span class="error-msg">First name is required</span>
                </div>
                <div class="form-group">
                    <label class="optional-label" style="letter-spacing: 0.5px; text-transform: uppercase; font-size: 0.72rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; display: block;">Middle Name</label>
                    <input type="text" name="middlename" placeholder="M." style="padding: 12px 14px; border-radius: 8px; border: 1.5px solid var(--border); font-size: 0.92rem; width: 100%;"
                           value="<?php echo htmlspecialchars($_POST['middlename'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="optional-label">Suffix</label>
                <input type="text" name="suffix" placeholder="Jr., Sr., III, etc."
                       value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group" data-validate="email">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="you@gmail.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                    <span class="error-msg">Email must be a Gmail address (@gmail.com)</span>
                </div>
                <div class="form-group" data-validate="phone">
                    <label>Contact Number *</label>
                    <input type="tel" name="contact_number" id="contact_number" placeholder="091234567890" maxlength="11" inputmode="numeric"
                           value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                    <span class="error-msg">Enter exactly 11 digits, numbers only</span>
                </div>
            </div>

            <div class="form-group" data-validate="required">
                <label>Address *</label>
                <textarea name="address" placeholder="Street, City, Province, ZIP"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                <span class="error-msg">Address is required</span>
            </div>

            <div class="form-group" data-validate="password">
                <label>Password *</label>
                <div class="password-wrap">
                    <input type="password" name="password" id="password" placeholder="Create a password">
                    <button type="button" class="toggle-eye" onclick="togglePassword('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                </div>
                <span class="error-msg">Password must be at least 6 characters</span>
            </div>

            <div class="form-group" data-validate="confirmPassword">
                <label>Confirm Password *</label>
                <div class="password-wrap">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password">
                    <button type="button" class="toggle-eye" onclick="togglePassword('confirm_password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                    <span class="error-icon"><i class="fas fa-exclamation"></i></span>
                </div>
                <span class="error-msg">Passwords must match</span>
            </div>

            <button type="submit" class="btn-signup" id="submitBtn">Sign Up</button>
        </form>

        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login</a></p>
            <a href="index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to home
            </a>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
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

    // Real-time validation
    const form = document.getElementById('signupForm');
    const submitBtn = document.getElementById('submitBtn');

    function validateField(group) {
        const input = group.querySelector('input, textarea');
        const validateType = group.dataset.validate;
        let isValid = true;
        let errorMsg = '';

        if (!input) return true;

        const value = input.value.trim();

        switch (validateType) {
            case 'required':
                if (!value) {
                    isValid = false;
                    errorMsg = 'This field is required';
                }
                break;
            case 'email':
                if (!value) {
                    isValid = false;
                    errorMsg = 'Email is required';
                } else if (!value.endsWith('@gmail.com')) {
                    isValid = false;
                    errorMsg = 'Email must be a Gmail address (@gmail.com)';
                }
                break;
            case 'password':
                if (!value) {
                    isValid = false;
                    errorMsg = 'Password is required';
                } else if (value.length < 6) {
                    isValid = false;
                    errorMsg = 'Password must be at least 6 characters';
                }
                break;
            case 'phone':
                const digitsOnly = value.replace(/\D/g, '');
                if (!value) {
                    isValid = false;
                    errorMsg = 'Contact number is required';
                } else if (/[^0-9]/.test(value)) {
                    isValid = false;
                    errorMsg = 'Numbers only (no letters)';
                } else if (digitsOnly.length !== 11) {
                    isValid = false;
                    errorMsg = 'Enter exactly 11 digits';
                }
                break;
            case 'confirmPassword':
                const password = document.getElementById('password').value;
                if (!value) {
                    isValid = false;
                    errorMsg = 'Please confirm your password';
                } else if (value !== password) {
                    isValid = false;
                    errorMsg = 'Passwords must match';
                }
                break;
        }

        if (isValid) {
            group.classList.remove('has-error');
        } else {
            group.classList.add('has-error');
            const msgEl = group.querySelector('.error-msg');
            if (msgEl && errorMsg) {
                msgEl.textContent = errorMsg;
            }
        }

        return isValid;
    }

    function validateAll() {
        const groups = form.querySelectorAll('[data-validate]');
        let allValid = true;
        groups.forEach(group => {
            if (!validateField(group)) {
                allValid = false;
            }
        });
        return allValid;
    }

    // Real-time input restriction for phone
    const phoneInput = document.getElementById('contact_number');
    if (phoneInput) {
        phoneInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 11);
        });
    }

    // Validate on blur
    form.querySelectorAll('[data-validate]').forEach(group => {
        const input = group.querySelector('input, textarea');
        if (input) {
            input.addEventListener('blur', () => validateField(group));
            input.addEventListener('input', () => {
                if (group.classList.contains('has-error')) {
                    validateField(group);
                }
            });
        }
    });

    // Validate on submit
    form.addEventListener('submit', (e) => {
        if (!validateAll()) {
            e.preventDefault();
        }
    });
</script>

</body>
</html>
