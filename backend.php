<?php
require_once 'config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];
$db = getDB();
$user_id = $_SESSION['user_id'];

if ($action === 'serve' && isset($_GET['id'])) {
    $cid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $stmt = $db->prepare("SELECT * FROM content WHERE id = ? AND (approved = 1 OR uploaded_by = ?) LIMIT 1");
    $stmt->execute([$cid, $user_id]);
    $c = $stmt->fetch();
    if ($c) {
        $fp = UPLOAD_DIR . $c['file_path'];
        if (file_exists($fp)) {
            $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg', 'txt' => 'text/plain'];
            $ext = strtolower($c['file_type']);
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . basename($c['file_path']) . '"');
            header('Content-Length: ' . filesize($fp));
            readfile($fp);
            exit;
        }
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

if ($action === 'download' && isset($_GET['id'])) {
    $cid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$cid) {
        http_response_code(400);
        echo 'Invalid ID';
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM content WHERE id = ? AND (approved = 1 OR uploaded_by = ?) LIMIT 1");
    $stmt->execute([$cid, $user_id]);
    $c = $stmt->fetch();

    if (!$c) {
        http_response_code(404);
        echo 'Content not found or not authorized';
        exit;
    }

    $fp = UPLOAD_DIR . $c['file_path'];

    if (!file_exists($fp)) {
        http_response_code(404);
        echo 'File missing from server';
        exit;
    }

    $stmt = $db->prepare("INSERT INTO download_logs (content_id, user_id, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$cid, $user_id, $_SERVER['REMOTE_ADDR']]);

    $stmt = $db->prepare("UPDATE content SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$cid]);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($c['file_path']) . '"');
    header('Content-Length: ' . filesize($fp));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($fp);
    exit;
}

if ($action === 'upload_content' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $response = ['success' => false, 'message' => 'Invalid security token'];
    } else {
        $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING));
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        $education_level_id = filter_input(INPUT_POST, 'education_level_id', FILTER_VALIDATE_INT);
        $content_type = filter_input(INPUT_POST, 'content_type', FILTER_SANITIZE_STRING);
        $tags = trim(filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_STRING));
        $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));

        if (empty($title) || !$subject_id || !$education_level_id) {
            $response = ['success' => false, 'message' => 'Please fill all required fields'];
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response = ['success' => false, 'message' => 'File upload failed'];
        } else {
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                $response = ['success' => false, 'message' => 'File type not allowed'];
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $response = ['success' => false, 'message' => 'File too large'];
            } else {
                if (!is_dir(UPLOAD_DIR))
                    mkdir(UPLOAD_DIR, 0755, true);
                $new_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                $dest = UPLOAD_DIR . $new_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $is_admin = ($_SESSION['role'] === 'admin');
                    $approved = $is_admin ? 1 : 0;
                    $status = $is_admin ? 'approved' : 'pending';
                    $stmt = $db->prepare("INSERT INTO content (title, description, subject_id, education_level_id, uploaded_by, file_path, file_type, file_size, content_type, tags, approved, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$title, $description, $subject_id, $education_level_id, $user_id, $new_name, $ext, $file['size'], $content_type, $tags, $approved, $status])) {
                        $msg = $is_admin ? 'Book published immediately!' : 'Book uploaded! Pending admin approval.';
                        $response = ['success' => true, 'message' => $msg];
                    } else {
                        unlink($dest);
                        $response = ['success' => false, 'message' => 'Database error'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Failed to save file'];
                }
            }
        }
    }
} elseif ($action === 'delete_content' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = filter_input(INPUT_POST, 'content_id', FILTER_VALIDATE_INT);
    if ($cid) {
        $stmt = $db->prepare("SELECT file_path, uploaded_by FROM content WHERE id = ? LIMIT 1");
        $stmt->execute([$cid]);
        $c = $stmt->fetch();
        if ($c && ($c['uploaded_by'] == $user_id || $_SESSION['role'] === 'admin')) {
            $fp = UPLOAD_DIR . $c['file_path'];
            if (file_exists($fp))
                unlink($fp);
            $stmt = $db->prepare("DELETE FROM content WHERE id = ?");
            $stmt->execute([$cid]);
            $response = ['success' => true, 'message' => 'Deleted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Permission denied'];
        }
    }
} elseif ($action === 'rate_content' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = filter_input(INPUT_POST, 'content_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    if ($cid && $rating >= 1 && $rating <= 5) {
        $stmt = $db->prepare("INSERT INTO ratings (content_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
        $stmt->execute([$cid, $user_id, $rating, $rating]);
        $stmt = $db->prepare("UPDATE content SET rating = (SELECT AVG(rating) FROM ratings WHERE content_id = ?) WHERE id = ?");
        $stmt->execute([$cid, $cid]);
        $response = ['success' => true, 'message' => 'Rating submitted!'];
    }
} elseif ($action === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = filter_input(INPUT_POST, 'content_id', FILTER_VALIDATE_INT);
    $comment = trim(filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING));
    if ($cid && !empty($comment)) {
        $stmt = $db->prepare("INSERT INTO comments (content_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$cid, $user_id, $comment]);
        $response = ['success' => true, 'message' => 'Comment added!'];
    } else {
        $response = ['success' => false, 'message' => 'Comment cannot be empty'];
    }
} elseif ($action === 'like_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    if ($comment_id) {
        $stmt = $db->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$comment_id, $user_id]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$comment_id, $user_id]);
            $response = ['success' => true, 'action' => 'unliked'];
        } else {
            $stmt = $db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$comment_id, $user_id]);
            $response = ['success' => true, 'action' => 'liked'];
        }
    }
} elseif ($action === 'reply_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    $reply = trim(filter_input(INPUT_POST, 'reply', FILTER_SANITIZE_STRING));
    if ($comment_id && !empty($reply)) {
        $stmt = $db->prepare("INSERT INTO comment_replies (comment_id, user_id, reply) VALUES (?, ?, ?)");
        $stmt->execute([$comment_id, $user_id, $reply]);
        $response = ['success' => true, 'message' => 'Reply added!'];
    } else {
        $response = ['success' => false, 'message' => 'Reply cannot be empty'];
    }
} elseif ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim(filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $district = filter_input(INPUT_POST, 'district', FILTER_SANITIZE_STRING);
    $school = trim(filter_input(INPUT_POST, 'school', FILTER_SANITIZE_STRING));
    $bio = trim(filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING));
    if (!empty($fullname)) {
        $stmt = $db->prepare("UPDATE users SET fullname = ?, phone = ?, district = ?, school_name = ?, bio = ? WHERE id = ?");
        $stmt->execute([$fullname, $phone, $district, $school, $bio, $user_id]);
        $_SESSION['fullname'] = $fullname;
        $_SESSION['district'] = $district;
        $response = ['success' => true, 'message' => 'Profile updated!'];
    }
}

if (!in_array($action, ['download', 'serve'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>