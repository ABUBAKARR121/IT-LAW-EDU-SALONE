<?php
require_once 'config.php';
$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['district'] = $user['district'];
                session_regenerate_id(true);
                setFlash('success', 'Welcome back, ' . $user['fullname'] . '!');
                if ($user['role'] === 'admin') {
                    redirect('admin.php');
                } else {
                    redirect('system.php');
                }
            } else {
                $error = 'Invalid email or password.';
                usleep(500000);
            }
        }
    }
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $fullname = trim(filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $district = filter_input(INPUT_POST, 'district', FILTER_SANITIZE_STRING);
        $school = trim(filter_input(INPUT_POST, 'school', FILTER_SANITIZE_STRING));
        $errors = [];
        if (empty($fullname))
            $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Valid email is required.';
        if (strlen($password) < 8)
            $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $password))
            $errors[] = 'Password must contain uppercase letter.';
        if (!preg_match('/[0-9]/', $password))
            $errors[] = 'Password must contain a number.';
        if ($password !== $confirm_password)
            $errors[] = 'Passwords do not match.';
        if (!in_array($role, ['student', 'teacher']))
            $errors[] = 'Invalid role.';
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("INSERT INTO users (fullname, email, phone, role, password, district, school_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$fullname, $email, $phone, $role, $hashed, $district, $school])) {
                    setFlash('success', 'Registration successful! Please login.');
                    redirect('auth.php?action=login');
                } else {
                    $error = 'Registration failed.';
                }
            }
        }
    }
}

if ($action === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    if (empty($email)) {
        $error = 'Please enter your email.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = generateToken(64);
            $expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            $reset_link = SITE_URL . "auth.php?action=reset&token=" . $token;
            $success = "<div style='background:#d4edda; padding:20px; border-radius:8px; margin:20px 0;'><strong>Reset Link Generated!</strong><br><br><div style='background:#fff; padding:15px; border:2px dashed #28a745; border-radius:5px; word-break:break-all;'><a href='{$reset_link}'>{$reset_link}</a></div><br><strong>Reset Code:</strong><br><code style='background:#fff; padding:10px; display:block; word-break:break-all;'>{$token}</code><br><small>Expires in 30 minutes.</small></div>";
        } else {
            $success = "If that email exists, a reset link has been generated.";
        }
    }
}

if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->execute([$hashed, $user['id']]);
            setFlash('success', 'Password reset successful! Please login.');
            redirect('auth.php?action=login');
        } else {
            $error = 'Invalid or expired token.';
        }
    }
}

$reset_token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - <?= ucfirst($action) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .auth-header i {
            font-size: 50px;
            margin-bottom: 10px;
        }

        .auth-header h1 {
            font-size: 22px;
            margin-bottom: 5px;
        }

        .auth-header p {
            opacity: 0.9;
            font-size: 13px;
        }

        .auth-body {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fde8e8;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2980b9;
            box-shadow: 0 0 0 4px rgba(41, 128, 185, 0.1);
        }

        .form-group .input-icon {
            position: relative;
        }

        .form-group .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
            background: linear-gradient(135deg, #1a5276, #2980b9);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(41, 128, 185, 0.4);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .auth-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }

        .auth-footer a {
            color: #2980b9;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #95a5a6;
            background: none;
            border: none;
            font-size: 16px;
        }

        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 576px) {
            .auth-container {
                max-width: 100%;
            }

            .auth-body {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-graduation-cap"></i>
            <h1><?= SITE_NAME ?></h1>
            <p>Digital Public Good for Sierra Leone</p>
        </div>
        <div class="auth-body">
            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= $flash['message'] ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <?php if ($action === 'reset' && $reset_token): ?>
                <h3 style="margin-bottom:20px;"><i class="fas fa-lock"></i> Reset Password</h3>
                <form method="POST" action="auth.php?action=reset" id="resetForm">
                    <input type="hidden" name="token" value="<?= h($reset_token) ?>">
                    <div class="form-group"><label>New Password</label>
                        <div class="input-icon"><i class="fas fa-key"></i><input type="password" name="new_password"
                                id="new_password" placeholder="Min 8 characters" required minlength="8"><button
                                type="button" class="password-toggle" onclick="togglePass('new_password')"><i
                                    class="fas fa-eye"></i></button></div>
                    </div>
                    <div class="form-group"><label>Confirm Password</label>
                        <div class="input-icon"><i class="fas fa-check-circle"></i><input type="password"
                                name="confirm_password" id="confirm_password" placeholder="Confirm password" required
                                minlength="8"><button type="button" class="password-toggle"
                                onclick="togglePass('confirm_password')"><i class="fas fa-eye"></i></button></div>
                    </div>
                    <button type="submit" class="btn" id="resetBtn"><i class="fas fa-save"></i> Reset Password</button>
                </form>
            <?php elseif ($action === 'forgot'): ?>
                <h3 style="margin-bottom:20px;"><i class="fas fa-question-circle"></i> Forgot Password?</h3>
                <p style="color:#666; margin-bottom:20px; font-size:14px;">Enter your registered email to receive a reset
                    link.</p>
                <form method="POST" action="auth.php?action=forgot" id="forgotForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-group"><label>Email Address</label>
                        <div class="input-icon"><i class="fas fa-envelope"></i><input type="email" name="email"
                                placeholder="Enter your email" required></div>
                    </div>
                    <button type="submit" class="btn" id="forgotBtn"><i class="fas fa-paper-plane"></i> Send Reset
                        Link</button>
                </form>
            <?php elseif ($action === 'register'): ?>
                <h3 style="margin-bottom:20px;"><i class="fas fa-user-plus"></i> Create Account</h3>
                <form method="POST" action="auth.php?action=register" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-group"><label>Full Name</label>
                        <div class="input-icon"><i class="fas fa-user"></i><input type="text" name="fullname"
                                placeholder="Full name" required></div>
                    </div>
                    <div class="form-group"><label>Email</label>
                        <div class="input-icon"><i class="fas fa-envelope"></i><input type="email" name="email"
                                placeholder="Email address" required></div>
                    </div>
                    <div class="form-group"><label>Phone</label>
                        <div class="input-icon"><i class="fas fa-phone"></i><input type="tel" name="phone"
                                placeholder="+232 XX XXX XXXX"></div>
                    </div>
                    <div class="form-group"><label>Register As</label>
                        <div class="input-icon"><i class="fas fa-user-tag"></i><select name="role" required>
                                <option value="">Select Role</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label>District</label>
                        <div class="input-icon"><i class="fas fa-map-marker-alt"></i><select name="district">
                                <option value="">Select</option>
                                <option>Western Area Urban</option>
                                <option>Western Area Rural</option>
                                <option>Bo</option>
                                <option>Bombali</option>
                                <option>Bonthe</option>
                                <option>Falaba</option>
                                <option>Kailahun</option>
                                <option>Kambia</option>
                                <option>Kenema</option>
                                <option>Koinadugu</option>
                                <option>Kono</option>
                                <option>Moyamba</option>
                                <option>Port Loko</option>
                                <option>Pujehun</option>
                                <option>Tonkolili</option>
                            </select></div>
                    </div>
                    <div class="form-group"><label>School</label>
                        <div class="input-icon"><i class="fas fa-school"></i><input type="text" name="school"
                                placeholder="School name"></div>
                    </div>
                    <div class="form-group"><label>Password (min 8 chars, uppercase, number)</label>
                        <div class="input-icon"><i class="fas fa-lock"></i><input type="password" name="password"
                                id="reg_password" placeholder="Create password" required minlength="8"><button type="button"
                                class="password-toggle" onclick="togglePass('reg_password')"><i
                                    class="fas fa-eye"></i></button></div>
                    </div>
                    <div class="form-group"><label>Confirm Password</label>
                        <div class="input-icon"><i class="fas fa-check-circle"></i><input type="password"
                                name="confirm_password" id="reg_confirm_password" placeholder="Confirm password" required
                                minlength="8"><button type="button" class="password-toggle"
                                onclick="togglePass('reg_confirm_password')"><i class="fas fa-eye"></i></button></div>
                    </div>
                    <button type="submit" class="btn" id="registerBtn"><i class="fas fa-user-plus"></i> Register</button>
                </form>
            <?php else: ?>
                <h3 style="margin-bottom:20px;"><i class="fas fa-sign-in-alt"></i> Welcome Back!</h3>
                <form method="POST" action="auth.php?action=login" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="form-group"><label>Email</label>
                        <div class="input-icon"><i class="fas fa-envelope"></i><input type="email" name="email"
                                placeholder="Enter your email" required autofocus></div>
                    </div>
                    <div class="form-group"><label>Password</label>
                        <div class="input-icon"><i class="fas fa-lock"></i><input type="password" name="password"
                                id="login_password" placeholder="Enter password" required><button type="button"
                                class="password-toggle" onclick="togglePass('login_password')"><i
                                    class="fas fa-eye"></i></button></div>
                    </div>
                    <div style="text-align:right; margin-bottom:20px;"><a href="auth.php?action=forgot"
                            style="color:#2980b9; font-size:14px;"><i class="fas fa-question-circle"></i> Forgot
                            Password?</a></div>
                    <button type="submit" class="btn" id="loginBtn"><i class="fas fa-sign-in-alt"></i> Login</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="auth-footer">
            <?php if ($action === 'login'): ?>
                <p>Don't have an account? <a href="auth.php?action=register">Register Here</a></p>
            <?php elseif ($action === 'register'): ?>
                <p>Already have an account? <a href="auth.php?action=login">Login Here</a></p>
            <?php else: ?>
                <p><a href="auth.php?action=login">Back to Login</a></p>
            <?php endif; ?>
            <p style="margin-top:10px; font-size:12px; color:#999;"><?= SOFTWARE_LICENSE ?> | Content:
                <?= CONTENT_LICENSE ?></p>
        </div>
    </div>
    <script>
        function togglePass(id) { const i = document.getElementById(id); const b = i.parentElement.querySelector('.password-toggle i'); if (i.type === 'password') { i.type = 'text'; b.classList.replace('fa-eye', 'fa-eye-slash'); } else { i.type = 'password'; b.classList.replace('fa-eye-slash', 'fa-eye'); } }
        document.getElementById('loginForm')?.addEventListener('submit', function () { const b = document.getElementById('loginBtn'); b.disabled = true; b.innerHTML = '<span class="loader"></span> Logging in...'; });
        document.getElementById('registerForm')?.addEventListener('submit', function () { const b = document.getElementById('registerBtn'); b.disabled = true; b.innerHTML = '<span class="loader"></span> Registering...'; });
        document.getElementById('forgotForm')?.addEventListener('submit', function () { const b = document.getElementById('forgotBtn'); b.disabled = true; b.innerHTML = '<span class="loader"></span> Sending...'; });
        document.getElementById('resetForm')?.addEventListener('submit', function () { const b = document.getElementById('resetBtn'); b.disabled = true; b.innerHTML = '<span class="loader"></span> Resetting...'; });
    </script>
</body>

</html>