<?php
$host = 'localhost';
$dbname = 'edusalone_db';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $fullname = 'System Admin';
    $email = 'admin@edusalone.sl';
    $plain_password = 'admin123';
    $role = 'admin';
    $district = 'Western Area Urban';

    $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        echo "Admin password updated successfully!<br>";
    } else {
        $stmt = $db->prepare("INSERT INTO users (fullname, email, role, password, district) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$fullname, $email, $role, $hashed_password, $district]);
        echo "Admin account created successfully!<br>";
    }

    echo "Email: admin@edusalone.sl<br>";
    echo "Password: admin123<br>";
    echo "<a href='auth.php'>Go to Login Page</a>";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>