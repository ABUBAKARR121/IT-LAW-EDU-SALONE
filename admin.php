<?php
require_once 'config.php';
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth.php?action=login');
}

$db = getDB();
$page = $_GET['page'] ?? 'dashboard';
$admin_id = $_SESSION['user_id'];

$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_content = $db->query("SELECT COUNT(*) FROM content")->fetchColumn();
$pending_count = $db->query("SELECT COUNT(*) FROM content WHERE status = 'pending'")->fetchColumn();
$approved_count = $db->query("SELECT COUNT(*) FROM content WHERE status = 'approved'")->fetchColumn();
$total_downloads = $db->query("SELECT SUM(downloads) FROM content")->fetchColumn();
$total_views = $db->query("SELECT SUM(views) FROM content")->fetchColumn();

$pending = $db->query("SELECT c.*, u.fullname as uploader_name, u.email as uploader_email, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.status = 'pending' ORDER BY c.created_at DESC")->fetchAll();
$approved = $db->query("SELECT c.*, u.fullname as uploader_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT 50")->fetchAll();
$rejected = $db->query("SELECT c.*, u.fullname as uploader_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.status = 'rejected' ORDER BY c.updated_at DESC LIMIT 20")->fetchAll();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$teachers = $db->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY fullname")->fetchAll();
$levels = $db->query("SELECT * FROM education_levels ORDER BY sort_order")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $stmt = $db->prepare("UPDATE content SET approved = 1, status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->execute([$admin_id, $_GET['approve']]);
    setFlash('success', 'Content approved successfully!');
    redirect('admin.php?page=pending');
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $reason = $_GET['reason'] ?? 'Not specified';
    $stmt = $db->prepare("UPDATE content SET approved = 0, status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->execute([$reason, $admin_id, $_GET['reject']]);
    setFlash('success', 'Content rejected.');
    redirect('admin.php?page=pending');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("SELECT file_path FROM content WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $c = $stmt->fetch();
    if ($c) {
        $fp = UPLOAD_DIR . $c['file_path'];
        if (file_exists($fp))
            unlink($fp);
    }
    $stmt = $db->prepare("DELETE FROM content WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    setFlash('success', 'Content deleted permanently.');
    redirect('admin.php?page=all_content');
}

if (isset($_GET['toggle_user']) && is_numeric($_GET['toggle_user'])) {
    $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_GET['toggle_user']]);
    $u = $stmt->fetch();
    $ns = $u['status'] === 'active' ? 'suspended' : 'active';
    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$ns, $_GET['toggle_user']]);
    setFlash('success', 'User status updated.');
    redirect('admin.php?page=users');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel -
        <?= SITE_NAME ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #e94560;
            --accent: #0f3460;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --light: #f5f6fa;
            --dark: #2c3e50;
            --white: #fff;
            --sidebar-width: 250px
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            min-height: 100vh
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary);
            color: white;
            z-index: 1000;
            overflow-y: auto
        }

        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1)
        }

        .sidebar-header h3 {
            font-size: 18px;
            margin-top: 10px
        }

        .admin-badge {
            display: inline-block;
            background: var(--secondary);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            margin-top: 5px
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
            transition: all 0.3s;
            font-size: 14px;
            border-left: 3px solid transparent
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--secondary)
        }

        .sidebar-nav a i {
            width: 20px;
            text-align: center
        }

        .sidebar-nav .badge {
            margin-left: auto;
            background: var(--secondary);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px
        }

        .top-bar h1 {
            font-size: 24px;
            color: var(--dark)
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white
        }

        .stat-icon.red {
            background: var(--danger)
        }

        .stat-icon.green {
            background: var(--success)
        }

        .stat-icon.blue {
            background: var(--info)
        }

        .stat-icon.orange {
            background: var(--warning)
        }

        .stat-icon.purple {
            background: #8e44ad
        }

        .stat-icon.teal {
            background: #1abc9c
        }

        .stat-info h3 {
            font-size: 22px;
            color: var(--dark)
        }

        .stat-info p {
            font-size: 13px;
            color: #999
        }

        .content-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08)
        }

        .content-table table {
            width: 100%;
            border-collapse: collapse
        }

        .content-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
            border-bottom: 2px solid #eee
        }

        .content-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 13px
        }

        .content-table tr:hover {
            background: #f8f9fa
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s
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

        .btn-warning {
            background: var(--warning);
            color: white
        }

        .btn-info {
            background: var(--info);
            color: white
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11px
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600
        }

        .status-pending {
            background: #fff3cd;
            color: #856404
        }

        .status-approved {
            background: #d4edda;
            color: #155724
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24
        }

        .status-active {
            background: #d4edda;
            color: #155724
        }

        .status-suspended {
            background: #f8d7da;
            color: #721c24
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success)
        }

        .section-title {
            font-size: 18px;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .reject-form {
            display: inline
        }

        .reject-form select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 12px
        }

        @media(max-width:768px) {
            .sidebar {
                width: 60px
            }

            .sidebar-header h3,
            .sidebar-header .admin-badge,
            .sidebar-nav a span,
            .sidebar-nav .badge {
                display: none
            }

            .sidebar-nav a {
                justify-content: center;
                padding: 15px
            }

            .main-content {
                margin-left: 60px
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr
            }
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-header"><i class="fas fa-shield-alt" style="font-size:40px"></i>
            <h3>Admin Panel</h3><span class="admin-badge">Administrator</span>
        </div>
        <nav class="sidebar-nav">
            <a href="admin.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><i
                    class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="admin.php?page=pending" class="<?= $page === 'pending' ? 'active' : '' ?>"><i class="fas fa-clock"></i>
                <span>Pending</span>
                <?php if ($pending_count > 0): ?><span class="badge">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="admin.php?page=all_content" class="<?= $page === 'all_content' ? 'active' : '' ?>"><i
                    class="fas fa-folder-open"></i> <span>All Content</span></a>
            <a href="admin.php?page=rejected" class="<?= $page === 'rejected' ? 'active' : '' ?>"><i class="fas fa-ban"></i>
                <span>Rejected</span></a>
            <a href="admin.php?page=users" class="<?= $page === 'users' ? 'active' : '' ?>"><i class="fas fa-users"></i>
                <span>Users</span></a>
            <a href="admin.php?page=teachers" class="<?= $page === 'teachers' ? 'active' : '' ?>"><i
                    class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a>
            <a href="report.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
            <a href="quiz.php"><i class="fas fa-brain"></i> <span>Quiz</span></a>
            <a href="system.php" target="_blank"><i class="fas fa-external-link-alt"></i> <span>View Site</span></a>
            <a href="logout.php" style="color:#e74c3c"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1>
                <?php switch ($page) {
                    case 'pending':
                        echo '<i class="fas fa-clock"></i> Pending Approval';
                        break;
                    case 'all_content':
                        echo '<i class="fas fa-folder-open"></i> All Content';
                        break;
                    case 'rejected':
                        echo '<i class="fas fa-ban"></i> Rejected';
                        break;
                    case 'users':
                        echo '<i class="fas fa-users"></i> Users';
                        break;
                    case 'teachers':
                        echo '<i class="fas fa-chalkboard-teacher"></i> Teachers';
                        break;
                    default:
                        echo '<i class="fas fa-tachometer-alt"></i> Dashboard';
                } ?>
            </h1><span style="color:#666">Welcome,
                <?= h($_SESSION['fullname']) ?>
            </span>
        </div>

        <?php if ($flash = getFlash()): ?>
            <div class="alert alert-success">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= $pending_count ?>
                        </h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= $approved_count ?>
                        </h3>
                        <p>Approved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= $total_users ?>
                        </h3>
                        <p>Users</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-book"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= $total_content ?>
                        </h3>
                        <p>Books</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-download"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= number_format($total_downloads) ?>
                        </h3>
                        <p>Downloads</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-eye"></i></div>
                    <div class="stat-info">
                        <h3>
                            <?= number_format($total_views) ?>
                        </h3>
                        <p>Views</p>
                    </div>
                </div>
            </div>
            <div
                style="background:white;padding:30px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.08)">
                <i class="fas fa-shield-alt" style="font-size:60px;color:#e94560"></i>
                <h3 style="margin-top:15px">Welcome to Admin Panel</h3>
                <p style="color:#666">Manage content approvals, users, and platform settings.</p>
                <?php if ($pending_count > 0): ?><a href="admin.php?page=pending" class="btn btn-warning"
                        style="margin-top:15px"><i class="fas fa-clock"></i> Review
                        <?= $pending_count ?> Pending
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'pending'): ?>
            <div class="content-table">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Uploaded By</th>
                            <th>Subject</th>
                            <th>Level</th>
                            <th>Type</th>
                            <th>File</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;color:#999">No pending content.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending as $item): ?>
                                <tr>
                                    <td><strong>
                                            <?= h($item['title']) ?>
                                        </strong></td>
                                    <td>
                                        <?= h($item['uploader_name']) ?><br><small>
                                            <?= h($item['uploader_email']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= h($item['subject_name']) ?>
                                    </td>
                                    <td>
                                        <?= h($item['level_name']) ?>
                                    </td>
                                    <td>
                                        <?= h($item['content_type']) ?>
                                    </td>
                                    <td><a href="review.php?id=<?= $item['id'] ?>" class="btn btn-info btn-sm"><i
                                                class="fas fa-eye"></i> Review</a></td>
                                    <td>
                                        <?= timeAgo($item['created_at']) ?>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <a href="admin.php?approve=<?= $item['id'] ?>&page=pending" class="btn btn-success btn-sm"
                                            onclick="return confirm('Approve?')"><i class="fas fa-check"></i> Approve</a>
                                        <form method="GET" action="admin.php" class="reject-form"
                                            onsubmit="return confirm('Reject?')">
                                            <input type="hidden" name="page" value="pending"><input type="hidden" name="reject"
                                                value="<?= $item['id'] ?>">
                                            <select name="reason">
                                                <option>Inappropriate</option>
                                                <option>Copyright</option>
                                                <option>Poor quality</option>
                                                <option>Wrong category</option>
                                                <option>Duplicate</option>
                                                <option>Other</option>
                                            </select>
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i>
                                                Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'all_content'): ?>
            <div class="content-table">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Uploader</th>
                            <th>Subject</th>
                            <th>Level</th>
                            <th>Downloads</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved as $item): ?>
                            <tr>
                                <td>
                                    <?= h($item['title']) ?>
                                </td>
                                <td>
                                    <?= h($item['uploader_name']) ?>
                                </td>
                                <td>
                                    <?= h($item['subject_name']) ?>
                                </td>
                                <td>
                                    <?= h($item['level_name']) ?>
                                </td>
                                <td>
                                    <?= $item['downloads'] ?>
                                </td>
                                <td>
                                    <?= number_format($item['rating'], 1) ?>/5
                                </td>
                                <td><a href="admin.php?delete=<?= $item['id'] ?>&page=all_content" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'rejected'): ?>
            <div class="content-table">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Uploader</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected as $item): ?>
                            <tr>
                                <td>
                                    <?= h($item['title']) ?>
                                </td>
                                <td>
                                    <?= h($item['uploader_name']) ?>
                                </td>
                                <td>
                                    <?= h($item['rejection_reason']) ?>
                                </td>
                                <td>
                                    <?= timeAgo($item['updated_at']) ?>
                                </td>
                                <td><a href="admin.php?approve=<?= $item['id'] ?>&page=rejected"
                                        class="btn btn-success btn-sm">Re-approve</a><a
                                        href="admin.php?delete=<?= $item['id'] ?>&page=rejected" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete?')">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'users'): ?>
            <div class="content-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <?= h($user['fullname']) ?>
                                </td>
                                <td>
                                    <?= h($user['email']) ?>
                                </td>
                                <td><span class="status-badge" style="background:#e8f4f8;color:#2980b9">
                                        <?= h($user['role']) ?>
                                    </span></td>
                                <td>
                                    <?= h($user['district']) ?>
                                </td>
                                <td><span class="status-<?= $user['status'] ?>">
                                        <?= h($user['status']) ?>
                                    </span></td>
                                <td>
                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($user['role'] !== 'admin'): ?><a
                                            href="admin.php?toggle_user=<?= $user['id'] ?>&page=users"
                                            class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $user['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'teachers'): ?>
            <div class="content-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>District</th>
                            <th>School</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $t): ?>
                            <tr>
                                <td>
                                    <?= h($t['fullname']) ?>
                                </td>
                                <td>
                                    <?= h($t['email']) ?>
                                </td>
                                <td>
                                    <?= h($t['district']) ?>
                                </td>
                                <td>
                                    <?= h($t['school_name']) ?>
                                </td>
                                <td><span class="status-<?= $t['status'] ?>">
                                        <?= h($t['status']) ?>
                                    </span></td>
                                <td>
                                    <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>