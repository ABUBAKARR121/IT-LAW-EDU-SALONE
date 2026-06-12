<?php
require_once 'config.php';

$db = getDB();

// Admin credentials - Strong but memorable
$fullname = 'System Administrator';
$email = 'admin@edusalone.sl';
$plain_password = 'EduSalone@2024#SL';  // Strong password: uppercase, lowercase, number, symbol
$role = 'admin';
$district = 'Western Area Urban';

$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => 12]);

// Check if admin exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    // Update existing admin
    $stmt = $db->prepare("UPDATE users SET password = ?, role = ?, fullname = ? WHERE email = ?");
    $stmt->execute([$hashed_password, $role, $fullname, $email]);
    $message = "Admin password updated successfully!";
} else {
    // Create new admin
    $stmt = $db->prepare("INSERT INTO users (fullname, email, role, password, district) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$fullname, $email, $role, $hashed_password, $district]);
    $message = "Admin account created successfully!";
}

// Also create a demo teacher account
$teacher_email = 'teacher@edusalone.sl';
$teacher_password = password_hash('Teacher@2024#SL', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$teacher_email]);

if (!$stmt->fetch()) {
    $stmt = $db->prepare("INSERT INTO users (fullname, email, role, password, district) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Demo Teacher', $teacher_email, 'teacher', $teacher_password, 'Western Area Urban']);
}

// Auto-approve any existing pending content (optional)
$stmt = $db->prepare("UPDATE content SET approved = 1, status = 'approved', reviewed_by = (SELECT id FROM users WHERE email = ? LIMIT 1), reviewed_at = NOW() WHERE status = 'pending'");
$stmt->execute([$email]);
$approved_count = $stmt->rowCount();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - EduSalone Share</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        h2 {
            color: #1a5276;
            margin-bottom: 10px;
        }

        .success {
            color: #27ae60;
            font-weight: bold;
        }

        .info-box {
            background: #f0f8ff;
            border: 2px solid #2980b9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .info-box p {
            margin: 8px 0;
            font-size: 14px;
            word-break: break-all;
        }

        .info-box strong {
            display: inline-block;
            width: 80px;
        }

        .btn {
            display: inline-block;
            padding: 14px 30px;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #1a5276;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">&#128640;</div>
        <h2>EduSalone Share Setup Complete!</h2>
        <p class="success">
            <?php echo $message; ?>
        </p>

        <div class="info-box">
            <h3 style="margin-top:0; color:#1a5276;">Admin Login Credentials</h3>
            <p><strong>Email:</strong> admin@edusalone.sl</p>
            <p><strong>Password:</strong> EduSalone@2024#SL</p>
            <hr>
            <h3 style="color:#1a5276;">Demo Teacher Account</h3>
            <p><strong>Email:</strong> teacher@edusalone.sl</p>
            <p><strong>Password:</strong> Teacher@2024#SL</p>
        </div>

        <p style="color:#666; font-size:13px;">
            <?php echo $approved_count; ?> pending resources were auto-approved.
        </p>

        <a href="auth.php" class="btn">Go to Login Page</a>

        <div class="warning">
            <strong>IMPORTANT:</strong> Delete this file (create_admin.php) after setup for security reasons.
        </div>
    </div>
</body>

</html>