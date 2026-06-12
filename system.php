<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('auth.php?action=login');
}

$db = getDB();
$page = $_GET['page'] ?? 'dashboard';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$stmt = $db->prepare("SELECT COUNT(*) FROM content WHERE uploaded_by = ?");
$stmt->execute([$user_id]);
$my_uploads = $stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM download_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_downloads = $stmt->fetchColumn();
$total_content = $db->query("SELECT COUNT(*) FROM content WHERE approved = 1")->fetchColumn();

$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$education_levels = $db->query("SELECT * FROM education_levels ORDER BY sort_order")->fetchAll();

$recent = $db->query("SELECT c.*, u.fullname as author_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.approved = 1 ORDER BY c.created_at DESC LIMIT 8")->fetchAll();
$popular = $db->query("SELECT c.*, u.fullname as author_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.approved = 1 ORDER BY c.downloads DESC LIMIT 8")->fetchAll();

$stmt = $db->prepare("SELECT c.*, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.uploaded_by = ? AND c.status = 'pending' ORDER BY c.created_at DESC");
$stmt->execute([$user_id]);
$pending_uploads = $stmt->fetchAll();
$stmt = $db->prepare("SELECT c.*, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.uploaded_by = ? AND c.status = 'approved' ORDER BY c.created_at DESC");
$stmt->execute([$user_id]);
$my_approved = $stmt->fetchAll();

$search_query = $_GET['q'] ?? '';
$filter_subject = $_GET['subject'] ?? '';
$filter_level = $_GET['level'] ?? '';
$browse_sql = "SELECT c.*, u.fullname as author_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.approved = 1";
$browse_params = [];
if ($search_query) {
    $browse_sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.tags LIKE ?)";
    $st = "%{$search_query}%";
    $browse_params = array_merge($browse_params, [$st, $st, $st]);
}
if ($filter_subject) {
    $browse_sql .= " AND c.subject_id = ?";
    $browse_params[] = $filter_subject;
}
if ($filter_level) {
    $browse_sql .= " AND c.education_level_id = ?";
    $browse_params[] = $filter_level;
}
$browse_sql .= " ORDER BY c.created_at DESC LIMIT 100";
$stmt = $db->prepare($browse_sql);
$stmt->execute($browse_params);
$all_content = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$content_detail = null;
if (isset($_GET['view'])) {
    $content_id = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT);
    $stmt = $db->prepare("SELECT c.*, u.fullname as author_name, u.district as author_district, u.school_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.id = ? AND c.approved = 1 LIMIT 1");
    $stmt->execute([$content_id]);
    $content_detail = $stmt->fetch();
    if ($content_detail) {
        $stmt = $db->prepare("UPDATE content SET views = views + 1 WHERE id = ?");
        $stmt->execute([$content_id]);
        $stmt = $db->prepare("SELECT cm.*, u.fullname, u.role, (SELECT COUNT(*) FROM comment_likes WHERE comment_id = cm.id) as like_count, (SELECT COUNT(*) FROM comment_likes WHERE comment_id = cm.id AND user_id = ?) as user_liked FROM comments cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.content_id = ? ORDER BY cm.created_at DESC");
        $stmt->execute([$user_id, $content_id]);
        $content_detail['comments'] = $stmt->fetchAll();
        foreach ($content_detail['comments'] as &$comment) {
            $stmt = $db->prepare("SELECT cr.*, u.fullname FROM comment_replies cr LEFT JOIN users u ON cr.user_id = u.id WHERE cr.comment_id = ? ORDER BY cr.created_at ASC");
            $stmt->execute([$comment['id']]);
            $comment['replies'] = $stmt->fetchAll();
        }
        $stmt = $db->prepare("SELECT rating FROM ratings WHERE content_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$content_id, $user_id]);
        $content_detail['user_rating'] = $stmt->fetchColumn() ?: 0;
    }
}

$total_pending = 0;
if ($role === 'admin') {
    $total_pending = $db->query("SELECT COUNT(*) FROM content WHERE status = 'pending'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= SITE_NAME ?> - Learning Platform
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a5276;
            --secondary: #2980b9;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --white: #fff;
            --sidebar-width: 260px;
            --shadow: 0 2px 15px rgba(0, 0, 0, 0.1)
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            min-height: 100vh
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: var(--white);
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s
        }

        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1)
        }

        .sidebar-header .logo {
            font-size: 32px;
            margin-bottom: 8px
        }

        .sidebar-header h3 {
            font-size: 18px;
            font-weight: 600
        }

        .sidebar-header small {
            font-size: 11px;
            opacity: 0.7
        }

        .sidebar-user {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1)
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0
        }

        .user-info {
            flex: 1;
            min-width: 0
        }

        .user-info strong {
            display: block;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .user-info span {
            font-size: 11px;
            opacity: 0.7;
            text-transform: capitalize
        }

        .sidebar-nav {
            padding: 15px 0
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            border-left: 3px solid transparent;
            transition: all 0.3s
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--secondary)
        }

        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: 16px
        }

        .sidebar-nav .badge {
            margin-left: auto;
            background: var(--danger);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1)
        }

        .sidebar-footer a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s
        }

        .sidebar-footer a:hover {
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
            gap: 10px
        }

        .top-bar h2 {
            font-size: 20px;
            color: var(--dark)
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--dark)
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15)
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #2980b9, #3498db)
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #27ae60, #2ecc71)
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #e67e22, #f39c12)
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8e44ad, #9b59b6)
        }

        .stat-info h4 {
            font-size: 24px;
            color: var(--dark)
        }

        .stat-info p {
            font-size: 13px;
            color: #999;
            margin-top: 2px
        }

        .section-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px
        }

        .content-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid #eee;
            transition: all 0.3s
        }

        .content-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15)
        }

        .card-header {
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #eee
        }

        .file-icon {
            font-size: 30px;
            color: var(--secondary)
        }

        .card-meta {
            flex: 1;
            min-width: 0
        }

        .subject-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f4f8;
            color: var(--secondary)
        }

        .class-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #fff3cd;
            color: #856404;
            margin-left: 5px
        }

        .card-body {
            padding: 20px
        }

        .card-body h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--dark)
        }

        .card-body p {
            font-size: 13px;
            color: #666;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden
        }

        .meta-info {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #999;
            flex-wrap: wrap
        }

        .meta-info span {
            display: flex;
            align-items: center;
            gap: 5px
        }

        .card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 8px;
            flex-wrap: wrap
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px
        }

        .btn-lg {
            padding: 14px 24px;
            font-size: 15px
        }

        .btn-primary {
            background: var(--secondary);
            color: white
        }

        .btn-primary:hover {
            background: #2471a3
        }

        .btn-success {
            background: var(--success);
            color: white
        }

        .btn-success:hover {
            background: #219a52
        }

        .btn-danger {
            background: var(--danger);
            color: white
        }

        .btn-danger:hover {
            background: #c0392b
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary)
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed
        }

        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 700px
        }

        .form-group {
            margin-bottom: 20px
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--dark)
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            background: #fafafa
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(41, 128, 185, 0.1);
            background: white
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success)
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger)
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8
        }

        .detail-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow)
        }

        .detail-header {
            padding: 30px;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            color: white
        }

        .detail-header h2 {
            margin-bottom: 5px
        }

        .detail-body {
            padding: 30px
        }

        .detail-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666
        }

        .rating-stars {
            display: flex;
            gap: 5px;
            font-size: 24px
        }

        .rating-stars i {
            cursor: pointer;
            color: #ddd;
            transition: all 0.3s
        }

        .rating-stars i.active,
        .rating-stars i:hover {
            color: #f39c12
        }

        .comments-section {
            margin-top: 30px
        }

        .comment {
            padding: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px
        }

        .comment:last-child {
            border-bottom: none
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px
        }

        .comment-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0
        }

        .comment-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 13px
        }

        .comment-actions a {
            color: #666;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s
        }

        .comment-actions a:hover {
            color: var(--secondary)
        }

        .comment-actions a.liked {
            color: #e74c3c
        }

        .reply-section {
            margin-left: 45px;
            margin-top: 10px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px
        }

        .reply-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px
        }

        .reply-item:last-child {
            border-bottom: none
        }

        .reply-form {
            margin-left: 45px;
            margin-top: 10px;
            display: none
        }

        .reply-form textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            resize: vertical
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 4000;
            animation: toastIn 0.3s ease, toastOut 0.3s ease 3s forwards;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2)
        }

        @keyframes toastIn {
            from {
                transform: translateX(100%);
                opacity: 0
            }

            to {
                transform: translateX(0);
                opacity: 1
            }
        }

        @keyframes toastOut {
            from {
                transform: translateX(0);
                opacity: 1
            }

            to {
                transform: translateX(100%);
                opacity: 0
            }
        }

        .toast-success {
            background: #27ae60
        }

        .toast-error {
            background: #e74c3c
        }

        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .admin-notice {
            background: #fff3cd;
            border: 2px solid #f39c12;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px
        }

        .teacher-notice {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px
        }

        @media(max-width:992px) {
            .sidebar {
                transform: translateX(-100%)
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .main-content {
                margin-left: 0
            }

            .mobile-menu-btn {
                display: block
            }

            .form-row {
                grid-template-columns: 1fr
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:576px) {
            .stats-grid {
                grid-template-columns: 1fr
            }

            .content-grid {
                grid-template-columns: 1fr
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start
            }
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999
        }
    </style>
</head>

<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo"><i class="fas fa-graduation-cap"></i></div>
            <h3>
                <?= SITE_NAME ?>
            </h3>
            <small>Digital Public Good | Sierra Leone</small>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['fullname'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <strong>
                    <?= h($_SESSION['fullname']) ?>
                </strong>
                <span>
                    <?= h($role) ?> |
                    <?= h($_SESSION['district'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="?page=browse" class="<?= $page === 'browse' ? 'active' : '' ?>">
                <i class="fas fa-book-open"></i> Browse Library
                <span class="badge">
                    <?= $total_content ?>
                </span>
            </a>
            <a href="?page=upload" class="<?= $page === 'upload' ? 'active' : '' ?>">
                <i class="fas fa-cloud-upload-alt"></i> Upload Book
            </a>
            <a href="?page=my_uploads" class="<?= $page === 'my_uploads' ? 'active' : '' ?>">
                <i class="fas fa-folder"></i> My Uploads
                <?php if ($pending_uploads): ?>
                    <span class="badge">
                        <?= count($pending_uploads) ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="quiz.php">
                <i class="fas fa-brain"></i> Quiz
            </a>
            <a href="?page=profile" class="<?= $page === 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user-cog"></i> My Profile
            </a>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php" style="color:#f39c12;">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                    <?php if ($total_pending > 0): ?>
                        <span class="badge">
                            <?= $total_pending ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div style="display:flex;align-items:center;gap:15px">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2>
                    <?php
                    switch ($page) {
                        case 'dashboard':
                            echo '<i class="fas fa-tachometer-alt"></i> Dashboard';
                            break;
                        case 'browse':
                            echo '<i class="fas fa-book-open"></i> Browse Library';
                            break;
                        case 'upload':
                            echo '<i class="fas fa-cloud-upload-alt"></i> Upload Book';
                            break;
                        case 'my_uploads':
                            echo '<i class="fas fa-folder"></i> My Uploads';
                            break;
                        case 'profile':
                            echo '<i class="fas fa-user-cog"></i> My Profile';
                            break;
                        case 'detail':
                            echo '<i class="fas fa-file-alt"></i> Book Details';
                            break;
                        default:
                            echo 'Dashboard';
                    }
                    ?>
                </h2>
            </div>
            <div style="font-size:13px;color:#999;">
                <i class="fas fa-clock"></i>
                <?= date('l, F j, Y') ?>
            </div>
        </div>

        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
                <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location='?page=browse'">
                    <div class="stat-icon blue"><i class="fas fa-database"></i></div>
                    <div class="stat-info">
                        <h4>
                            <?= number_format($total_content) ?>
                        </h4>
                        <p>Total Books Available</p>
                    </div>
                </div>
                <div class="stat-card" onclick="window.location='?page=my_uploads'">
                    <div class="stat-icon green"><i class="fas fa-upload"></i></div>
                    <div class="stat-info">
                        <h4>
                            <?= number_format($my_uploads) ?>
                        </h4>
                        <p>My Uploads</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-download"></i></div>
                    <div class="stat-info">
                        <h4>
                            <?= number_format($my_downloads) ?>
                        </h4>
                        <p>My Downloads</p>
                    </div>
                </div>
                <?php if ($role === 'admin'): ?>
                    <div class="stat-card" onclick="window.location='admin.php?page=pending'">
                        <div class="stat-icon purple"><i class="fas fa-clock"></i></div>
                        <div class="stat-info">
                            <h4>
                                <?= $total_pending ?>
                            </h4>
                            <p>Pending Approval</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="section-title"><i class="fas fa-clock"></i> Recently Added Books</h3>
            <div class="content-grid">
                <?php foreach ($recent as $item): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <div class="file-icon"><i class="fas <?= getFileIcon($item['file_type']) ?>"></i></div>
                            <div class="card-meta">
                                <span class="subject-badge">
                                    <?= h($item['subject_name']) ?>
                                </span>
                                <span class="class-badge">
                                    <?= h($item['level_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4>
                                <?= h($item['title']) ?>
                            </h4>
                            <p>
                                <?= h($item['description'] ?: 'No description available') ?>
                            </p>
                            <div class="meta-info">
                                <span><i class="fas fa-user"></i>
                                    <?= h($item['author_name']) ?>
                                </span>
                                <span><i class="fas fa-download"></i>
                                    <?= $item['downloads'] ?>
                                </span>
                                <span><i class="fas fa-star" style="color:#f39c12"></i>
                                    <?= number_format($item['rating'], 1) ?>
                                </span>
                                <span><i class="fas fa-clock"></i>
                                    <?= timeAgo($item['created_at']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="?page=detail&view=<?= $item['id'] ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="reader.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-book-reader"></i> Read
                            </a>
                            <a href="download.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                    <p style="grid-column:1/-1; text-align:center; padding:40px; color:#999;">
                        <i class="fas fa-inbox" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                        No books have been uploaded yet. Be the first to share educational content!
                    </p>
                <?php endif; ?>
            </div>

            <h3 class="section-title"><i class="fas fa-fire"></i> Most Downloaded</h3>
            <div class="content-grid">
                <?php foreach ($popular as $item): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <div class="file-icon"><i class="fas <?= getFileIcon($item['file_type']) ?>"></i></div>
                            <div class="card-meta">
                                <span class="subject-badge">
                                    <?= h($item['subject_name']) ?>
                                </span>
                                <span class="class-badge">
                                    <?= h($item['level_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4>
                                <?= h($item['title']) ?>
                            </h4>
                            <div class="meta-info">
                                <span><i class="fas fa-download"></i> <strong>
                                        <?= $item['downloads'] ?>
                                    </strong> downloads</span>
                                <span><i class="fas fa-star" style="color:#f39c12"></i>
                                    <?= number_format($item['rating'], 1) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="reader.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">Read</a>
                            <a href="backend.php?action=download&id=<?= $item['id'] ?>"
                                class="btn btn-primary btn-sm">Download</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($page === 'browse'): ?>
            <div class="form-container" style="max-width:100%; margin-bottom:20px;">
                <form method="GET" action="system.php"
                    style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-end;">
                    <input type="hidden" name="page" value="browse">
                    <div class="form-group" style="flex:2; min-width:200px; margin-bottom:0;">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="q" placeholder="Search by title, description, or tags..."
                            value="<?= h($search_query) ?>">
                    </div>
                    <div class="form-group" style="flex:1; min-width:150px; margin-bottom:0;">
                        <label><i class="fas fa-book"></i> Subject</label>
                        <select name="subject">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $filter_subject == $s['id'] ? 'selected' : '' ?>>
                                    <?= h($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1; min-width:150px; margin-bottom:0;">
                        <label><i class="fas fa-layer-group"></i> Level</label>
                        <select name="level">
                            <option value="">All Levels</option>
                            <?php foreach ($education_levels as $el): ?>
                                <option value="<?= $el['id'] ?>" <?= $filter_level == $el['id'] ? 'selected' : '' ?>>
                                    <?= h($el['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height:fit-content;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="?page=browse" class="btn btn-outline" style="height:fit-content;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </div>

            <h3 class="section-title">
                <i class="fas fa-list"></i>
                <?= count($all_content) ?> Book
                <?= count($all_content) !== 1 ? 's' : '' ?> Found
            </h3>

            <div class="content-grid">
                <?php foreach ($all_content as $item): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <div class="file-icon"><i class="fas <?= getFileIcon($item['file_type']) ?>"></i></div>
                            <div class="card-meta">
                                <span class="subject-badge">
                                    <?= h($item['subject_name']) ?>
                                </span>
                                <span class="class-badge">
                                    <?= h($item['level_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4>
                                <?= h($item['title']) ?>
                            </h4>
                            <p>
                                <?= h($item['description'] ?: 'No description') ?>
                            </p>
                            <div class="meta-info">
                                <span><i class="fas fa-user"></i>
                                    <?= h($item['author_name']) ?>
                                </span>
                                <span><i class="fas fa-eye"></i>
                                    <?= $item['views'] ?>
                                </span>
                                <span><i class="fas fa-download"></i>
                                    <?= $item['downloads'] ?>
                                </span>
                                <span><i class="fas fa-star" style="color:#f39c12"></i>
                                    <?= number_format($item['rating'], 1) ?>
                                </span>
                                <span><i class="fas fa-clock"></i>
                                    <?= timeAgo($item['created_at']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="?page=detail&view=<?= $item['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <a href="reader.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">Read</a>
                            <a href="backend.php?action=download&id=<?= $item['id'] ?>"
                                class="btn btn-primary btn-sm">Download</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($all_content)): ?>
                    <p style="grid-column:1/-1; text-align:center; padding:40px; color:#999;">
                        <i class="fas fa-search" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                        No books found. Try different search terms or upload new content.
                    </p>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'upload'): ?>
            <div class="form-container">
                <h3 class="section-title"><i class="fas fa-cloud-upload-alt"></i> Upload Educational Book</h3>

                <?php if ($role === 'admin'): ?>
                    <div class="admin-notice">
                        <strong><i class="fas fa-shield-alt"></i> Administrator Upload:</strong>
                        Books you upload are <strong>published immediately</strong> without requiring approval.
                        They will be visible to all users instantly.
                    </div>
                <?php else: ?>
                    <div class="teacher-notice">
                        <strong><i class="fas fa-info-circle"></i> Teacher Upload:</strong>
                        Your book will be submitted for <strong>administrator review</strong>.
                        Once approved, it will become visible to all users. You can track the status in My Uploads.
                    </div>
                <?php endif; ?>

                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> Book Title *</label>
                        <input type="text" name="title" id="uploadTitle"
                            placeholder="Enter a clear and descriptive title for this book" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" id="uploadDesc"
                            placeholder="Brief description of the content, topics covered, and intended audience..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Subject *</label>
                            <select name="subject_id" id="uploadSubject" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= h($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Education Level *</label>
                            <select name="education_level_id" id="uploadLevel" required>
                                <option value="">Select Level</option>
                                <?php foreach ($education_levels as $el): ?>
                                    <option value="<?= $el['id'] ?>">
                                        <?= h($el['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Content Type</label>
                            <select name="content_type" id="uploadType">
                                <option value="textbook">Textbook</option>
                                <option value="lesson_note">Lesson Note</option>
                                <option value="worksheet">Worksheet</option>
                                <option value="exam_paper">Exam Paper</option>
                                <option value="research_paper">Research Paper</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tags"></i> Tags</label>
                            <input type="text" name="tags" id="uploadTags"
                                placeholder="e.g., algebra, beginner, term1, mathematics">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-file-upload"></i> File * (Maximum 100MB)</label>
                        <input type="file" name="file" id="uploadFile" required>
                        <small style="color:#666; display:block; margin-top:5px;">
                            <i class="fas fa-check-circle"></i> Allowed formats: PDF, Word, PowerPoint, Excel, Images,
                            Videos, Audio, ZIP
                        </small>
                    </div>

                    <div style="margin:15px 0; padding:12px; background:#f0f8ff; border-radius:8px; font-size:13px;">
                        <i class="fas fa-balance-scale"></i>
                        <strong>License Agreement:</strong> By uploading this content, you agree to share it under the
                        <strong>
                            <?= CONTENT_LICENSE ?>
                        </strong> license. This allows others to use and adapt your
                        content while giving you proper attribution as the original creator.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload Book
                    </button>
                    <span id="uploadStatus" style="margin-left:15px; font-size:14px;"></span>
                </form>

                <div id="uploadProgress" style="display:none; margin-top:20px;">
                    <div style="background:#e0e0e0; border-radius:10px; overflow:hidden; height:12px;">
                        <div id="progressBar"
                            style="height:100%; background:var(--success); width:0%; transition:width 0.3s ease;"></div>
                    </div>
                    <p id="progressText" style="text-align:center; font-size:13px; color:#666; margin-top:8px;"></p>
                </div>
            </div>

        <?php elseif ($page === 'my_uploads'): ?>
            <h3 class="section-title"><i class="fas fa-clock"></i> Pending Approval (
                <?= count($pending_uploads) ?>)
            </h3>
            <?php if ($pending_uploads): ?>
                <div style="overflow-x:auto;">
                    <table
                        style="width:100%; border-collapse:collapse; background:white; border-radius:12px; overflow:hidden; box-shadow:var(--shadow);">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:14px; text-align:left;">Title</th>
                                <th style="padding:14px; text-align:left;">Subject</th>
                                <th style="padding:14px; text-align:left;">Level</th>
                                <th style="padding:14px; text-align:left;">Date</th>
                                <th style="padding:14px; text-align:left;">Status</th>
                                <th style="padding:14px; text-align:left;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_uploads as $item): ?>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:12px 14px;">
                                        <?= h($item['title']) ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?= h($item['subject_name']) ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?= h($item['level_name']) ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <?= timeAgo($item['created_at']) ?>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <span style="color:#f39c12; font-weight:600;">
                                            <i class="fas fa-hourglass-half"></i> Pending Review
                                        </span>
                                    </td>
                                    <td style="padding:12px 14px;">
                                        <button class="btn btn-danger btn-sm" onclick="deleteContent(<?= $item['id'] ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align:center; padding:30px; color:#999; background:white; border-radius:12px;">
                    <i class="fas fa-check-circle"
                        style="font-size:40px; display:block; margin-bottom:10px; color:#27ae60;"></i>
                    No pending uploads. All your submitted content has been processed.
                </p>
            <?php endif; ?>

            <h3 class="section-title" style="margin-top:30px;">
                <i class="fas fa-check-circle" style="color:#27ae60;"></i>
                Approved Uploads (
                <?= count($my_approved) ?>)
            </h3>
            <div class="content-grid">
                <?php foreach ($my_approved as $item): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <div class="file-icon"><i class="fas <?= getFileIcon($item['file_type']) ?>"></i></div>
                            <div class="card-meta">
                                <span class="subject-badge">
                                    <?= h($item['subject_name']) ?>
                                </span>
                                <span class="class-badge">
                                    <?= h($item['level_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4>
                                <?= h($item['title']) ?>
                            </h4>
                            <div class="meta-info">
                                <span><i class="fas fa-download"></i>
                                    <?= $item['downloads'] ?> downloads
                                </span>
                                <span><i class="fas fa-eye"></i>
                                    <?= $item['views'] ?> views
                                </span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="reader.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">Read</a>
                            <a href="backend.php?action=download&id=<?= $item['id'] ?>"
                                class="btn btn-primary btn-sm">Download</a>
                            <button class="btn btn-danger btn-sm" onclick="deleteContent(<?= $item['id'] ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($my_approved)): ?>
                    <p style="grid-column:1/-1; text-align:center; padding:30px; color:#999;">
                        No approved uploads yet. Start sharing educational content!
                    </p>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'detail' && $content_detail): ?>
            <div class="detail-container">
                <div class="detail-header">
                    <h2>
                        <?= h($content_detail['title']) ?>
                    </h2>
                    <p style="opacity:0.9;">
                        Uploaded by <strong>
                            <?= h($content_detail['author_name']) ?>
                        </strong>
                        <?php if ($content_detail['author_district']): ?>
                            from
                            <?= h($content_detail['author_district']) ?>
                        <?php endif; ?>
                        <?php if ($content_detail['school_name']): ?>
                            (
                            <?= h($content_detail['school_name']) ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="detail-body">
                    <div class="detail-meta">
                        <span class="meta-item"><i class="fas fa-book"></i> <strong>
                                <?= h($content_detail['subject_name']) ?>
                            </strong></span>
                        <span class="meta-item"><i class="fas fa-layer-group"></i>
                            <?= h($content_detail['level_name']) ?>
                        </span>
                        <span class="meta-item"><i class="fas fa-tag"></i>
                            <?= h($content_detail['content_type']) ?>
                        </span>
                        <span class="meta-item"><i class="fas fa-file"></i>
                            <?= strtoupper($content_detail['file_type']) ?>
                        </span>
                        <span class="meta-item"><i class="fas fa-weight-hanging"></i>
                            <?= formatFileSize($content_detail['file_size']) ?>
                        </span>
                        <span class="meta-item"><i class="fas fa-eye"></i>
                            <?= $content_detail['views'] + 1 ?> views
                        </span>
                        <span class="meta-item"><i class="fas fa-download"></i>
                            <?= $content_detail['downloads'] ?> downloads
                        </span>
                        <span class="meta-item"><i class="fas fa-clock"></i>
                            <?= timeAgo($content_detail['created_at']) ?>
                        </span>
                        <span class="meta-item"><i class="fas fa-balance-scale"></i>
                            <?= h($content_detail['license']) ?>
                        </span>
                    </div>

                    <?php if ($content_detail['description']): ?>
                        <div style="margin:20px 0; padding:20px; background:#f8f9fa; border-radius:8px;">
                            <h4 style="margin-bottom:10px;"><i class="fas fa-align-left"></i> Description</h4>
                            <p style="line-height:1.6;">
                                <?= nl2br(h($content_detail['description'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($content_detail['tags']): ?>
                        <div style="margin:15px 0;">
                            <?php foreach (explode(',', $content_detail['tags']) as $tag): ?>
                                <span
                                    style="display:inline-block; padding:5px 12px; background:#e8f4f8; border-radius:15px; font-size:12px; margin:3px; color:#2980b9;">
                                    <i class="fas fa-tag"></i>
                                    <?= h(trim($tag)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin:25px 0; padding:20px; background:#fafafa; border-radius:8px;">
                        <strong><i class="fas fa-star"></i> Rate this book:</strong>
                        <div class="rating-stars" style="margin-top:10px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $content_detail['user_rating'] ? 'active' : '' ?>"
                                    data-rating="<?= $i ?>" onclick="rateContent(<?= $content_detail['id'] ?>, <?= $i ?>)"
                                    title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <small style="color:#666; margin-top:5px; display:block;">
                            Average rating: <strong>
                                <?= number_format($content_detail['rating'], 1) ?>/5
                            </strong>
                            <?php if ($content_detail['user_rating'] > 0): ?>
                                (Your rating:
                                <?= $content_detail['user_rating'] ?>/5)
                            <?php endif; ?>
                        </small>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin:25px 0;">
                        <a href="reader.php?id=<?= $content_detail['id'] ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-book-reader"></i> Read Online
                        </a>
                        <a href="backend.php?action=download&id=<?= $content_detail['id'] ?>"
                            class="btn btn-primary btn-lg">
                            <i class="fas fa-download"></i> Download Now
                        </a>
                    </div>

                    <div class="comments-section">
                        <h4><i class="fas fa-comments"></i> Comments (
                            <?= count($content_detail['comments']) ?>)
                        </h4>

                        <form id="commentForm" style="margin:20px 0;">
                            <input type="hidden" name="content_id" value="<?= $content_detail['id'] ?>">
                            <textarea name="comment" rows="3" placeholder="Write your comment here..." required
                                style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-family:inherit; font-size:14px;"></textarea>
                            <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                                <i class="fas fa-paper-plane"></i> Post Comment
                            </button>
                        </form>

                        <div id="commentsList">
                            <?php foreach ($content_detail['comments'] as $comment): ?>
                                <div class="comment" id="comment-<?= $comment['id'] ?>">
                                    <div class="comment-header">
                                        <div class="comment-avatar">
                                            <?= strtoupper(substr($comment['fullname'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong>
                                                <?= h($comment['fullname']) ?>
                                            </strong>
                                            <span style="font-size:11px; color:#999; margin-left:5px;">
                                                (
                                                <?= h($comment['role']) ?>)
                                            </span>
                                            <br>
                                            <small style="color:#999;">
                                                <?= timeAgo($comment['created_at']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <p style="margin-left:45px;">
                                        <?= h($comment['comment']) ?>
                                    </p>
                                    <div class="comment-actions" style="margin-left:45px;">
                                        <a class="<?= $comment['user_liked'] ? 'liked' : '' ?>"
                                            onclick="likeComment(<?= $comment['id'] ?>)">
                                            <i class="fas fa-heart"></i>
                                            <span id="like-count-<?= $comment['id'] ?>">
                                                <?= $comment['like_count'] ?>
                                            </span> Like
                                        </a>
                                        <a onclick="showReplyForm(<?= $comment['id'] ?>)">
                                            <i class="fas fa-reply"></i> Reply
                                        </a>
                                    </div>

                                    <?php if ($comment['replies']): ?>
                                        <div class="reply-section">
                                            <?php foreach ($comment['replies'] as $reply): ?>
                                                <div class="reply-item">
                                                    <strong>
                                                        <?= h($reply['fullname']) ?>
                                                    </strong>:
                                                    <?= h($reply['reply']) ?>
                                                    <small style="color:#999;"> -
                                                        <?= timeAgo($reply['created_at']) ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div id="reply-form-<?= $comment['id'] ?>" class="reply-form">
                                        <textarea id="reply-text-<?= $comment['id'] ?>" rows="2"
                                            placeholder="Write a reply..."></textarea>
                                        <button class="btn btn-sm btn-primary" style="margin-top:5px;"
                                            onclick="submitReply(<?= $comment['id'] ?>)">
                                            <i class="fas fa-paper-plane"></i> Reply
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($content_detail['comments'])): ?>
                                <p style="text-align:center; padding:20px; color:#999;">
                                    No comments yet. Be the first to comment!
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'detail' && !$content_detail): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Content not found or has been removed.
                <a href="?page=browse" style="color:#721c24; font-weight:bold;">Browse Library</a>
            </div>

        <?php elseif ($page === 'profile'): ?>
            <div class="form-container">
                <h3 class="section-title"><i class="fas fa-user-edit"></i> Edit Profile</h3>
                <form id="profileForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="fullname" value="<?= h($profile['fullname']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" value="<?= h($profile['email']) ?>" disabled
                                style="background:#f0f0f0; cursor:not-allowed;">
                            <small style="color:#999;">Email cannot be changed</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" value="<?= h($profile['phone']) ?>"
                                placeholder="+232 XX XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> District</label>
                            <select name="district">
                                <option value="">Select District</option>
                                <?php
                                $districts = [
                                    'Western Area Urban',
                                    'Western Area Rural',
                                    'Bo',
                                    'Bombali',
                                    'Bonthe',
                                    'Falaba',
                                    'Kailahun',
                                    'Kambia',
                                    'Kenema',
                                    'Koinadugu',
                                    'Kono',
                                    'Moyamba',
                                    'Port Loko',
                                    'Pujehun',
                                    'Tonkolili'
                                ];
                                foreach ($districts as $d):
                                    ?>
                                    <option <?= $profile['district'] === $d ? 'selected' : '' ?>>
                                        <?= $d ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-school"></i> School / Institution</label>
                        <input type="text" name="school" value="<?= h($profile['school_name']) ?>"
                            placeholder="Enter your school or institution name">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Bio</label>
                        <textarea name="bio" rows="4"
                            placeholder="Tell us about yourself..."><?= h($profile['bio']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                    <span id="profileStatus" style="margin-left:15px; font-size:14px;"></span>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').style.display =
                document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').style.display = 'none';
        }
        function showToast(message, type) {
            var toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
            document.body.appendChild(toast);
            setTimeout(function () { toast.remove(); }, 3500);
        }

        var uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(this);
                var btn = document.getElementById('uploadBtn');
                var progressDiv = document.getElementById('uploadProgress');
                var progressBar = document.getElementById('progressBar');
                var progressText = document.getElementById('progressText');

                btn.disabled = true;
                btn.innerHTML = '<span class="loader"></span> Uploading...';
                progressDiv.style.display = 'block';

                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percent + '%';
                        progressText.textContent = percent + '% uploaded';
                    }
                });
                xhr.addEventListener('load', function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload"></i> Upload Book';
                    progressDiv.style.display = 'none';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        showToast(response.message, response.success ? 'success' : 'error');
                        if (response.success) {
                            uploadForm.reset();
                            setTimeout(function () { window.location = '?page=my_uploads'; }, 2000);
                        }
                    } catch (err) {
                        showToast('An error occurred. Please try again.', 'error');
                    }
                });
                xhr.addEventListener('error', function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload"></i> Upload Book';
                    progressDiv.style.display = 'none';
                    showToast('Network error. Please try again.', 'error');
                });
                xhr.open('POST', 'backend.php?action=upload_content');
                xhr.send(formData);
            });
        }

        var profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var statusEl = document.getElementById('profileStatus');
                statusEl.innerHTML = '<span class="loader" style="border-color:rgba(41,128,185,0.2);border-top-color:#2980b9;"></span> Saving...';
                fetch('backend.php?action=update_profile', { method: 'POST', body: new FormData(this) })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        statusEl.innerHTML = d.success
                            ? '<span style="color:#27ae60;"><i class="fas fa-check-circle"></i> ' + d.message + '</span>'
                            : '<span style="color:#e74c3c;"><i class="fas fa-times-circle"></i> ' + d.message + '</span>';
                        if (d.success) setTimeout(function () { location.reload(); }, 1500);
                    })
                    .catch(function () {
                        statusEl.innerHTML = '<span style="color:#e74c3c;">Error occurred</span>';
                    });
            });
        }

        var commentForm = document.getElementById('commentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(this);
                var textarea = this.querySelector('textarea');
                fetch('backend.php?action=add_comment', { method: 'POST', body: formData })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.success) { textarea.value = ''; location.reload(); }
                        else showToast(d.message, 'error');
                    });
            });
        }

        function rateContent(contentId, rating) {
            var formData = new FormData();
            formData.append('content_id', contentId);
            formData.append('rating', rating);
            fetch('backend.php?action=rate_content', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) { showToast('Rating submitted!', 'success'); setTimeout(function () { location.reload(); }, 1000); }
                });
        }

        function deleteContent(contentId) {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) return;
            var formData = new FormData();
            formData.append('content_id', contentId);
            fetch('backend.php?action=delete_content', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    showToast(d.message, d.success ? 'success' : 'error');
                    if (d.success) setTimeout(function () { location.reload(); }, 1000);
                });
        }

        function likeComment(commentId) {
            fetch('backend.php?action=like_comment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'comment_id=' + commentId
            })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.success) location.reload(); });
        }

        function showReplyForm(commentId) {
            var form = document.getElementById('reply-form-' + commentId);
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
            if (form.style.display === 'block') {
                document.getElementById('reply-text-' + commentId).focus();
            }
        }

        function submitReply(commentId) {
            var text = document.getElementById('reply-text-' + commentId).value;
            if (!text.trim()) return;
            fetch('backend.php?action=reply_comment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'comment_id=' + commentId + '&reply=' + encodeURIComponent(text)
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) location.reload();
                    else showToast(d.message, 'error');
                });
        }
    </script>
</body>

</html>