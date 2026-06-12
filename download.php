<?php
require_once 'config.php';

if (!isLoggedIn()) {
    die('Please login first.');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die('Invalid file ID.');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

// Admin can download any file, others can only download approved files or their own
if ($is_admin) {
    $stmt = $db->prepare("SELECT * FROM content WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("SELECT * FROM content WHERE id = ? AND (approved = 1 OR uploaded_by = ?) LIMIT 1");
    $stmt->execute([$id, $user_id]);
}

$content = $stmt->fetch();

if (!$content) {
    die('File not found or you do not have permission to access it.');
}

$file_path = UPLOAD_DIR . $content['file_path'];

if (!file_exists($file_path)) {
    die('The file is missing from the server. It may have been deleted.');
}

// Log the download
$stmt = $db->prepare("INSERT INTO download_logs (content_id, user_id, ip_address) VALUES (?, ?, ?)");
$stmt->execute([$id, $user_id, $_SERVER['REMOTE_ADDR']]);

// Update download count
$stmt = $db->prepare("UPDATE content SET downloads = downloads + 1 WHERE id = ?");
$stmt->execute([$id]);

// Get file info
$file_name = $content['file_path'];
$file_size = filesize($file_path);
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $file_size);
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($file_path);
exit;
?>