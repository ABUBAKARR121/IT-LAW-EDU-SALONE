<?php
require_once 'config.php';
if (!isLoggedIn() || !isAdmin()) {
    redirect('auth.php?action=login');
}

$db = getDB();
$format = $_GET['format'] ?? 'html';

$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_students = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_teachers = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$total_content = $db->query("SELECT COUNT(*) FROM content")->fetchColumn();
$approved_content = $db->query("SELECT COUNT(*) FROM content WHERE status='approved'")->fetchColumn();
$pending_content = $db->query("SELECT COUNT(*) FROM content WHERE status='pending'")->fetchColumn();
$total_downloads = $db->query("SELECT SUM(downloads) FROM content")->fetchColumn() ?: 0;
$total_views = $db->query("SELECT SUM(views) FROM content")->fetchColumn() ?: 0;
$total_comments = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$avg_rating = round($db->query("SELECT AVG(rating) FROM content WHERE approved=1")->fetchColumn() ?: 0, 1);

$content_by_subject = $db->query("SELECT s.name, COUNT(c.id) as count FROM subjects s LEFT JOIN content c ON s.id = c.subject_id AND c.approved = 1 GROUP BY s.id ORDER BY count DESC")->fetchAll();
$content_by_level = $db->query("SELECT el.name, COUNT(c.id) as count FROM education_levels el LEFT JOIN content c ON el.id = c.education_level_id AND c.approved = 1 GROUP BY el.id ORDER BY el.sort_order")->fetchAll();
$top_uploaders = $db->query("SELECT u.fullname, u.email, COUNT(c.id) as uploads FROM users u LEFT JOIN content c ON u.id = c.uploaded_by WHERE u.role = 'teacher' GROUP BY u.id ORDER BY uploads DESC LIMIT 10")->fetchAll();

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="edusalone_report_' . date('Y-m-d') . '.xls"');
    echo "EduSalone Share - System Report\nGenerated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "OVERVIEW\nTotal Users\t$total_users\nStudents\t$total_students\nTeachers\t$total_teachers\nTotal Content\t$total_content\nApproved\t$approved_content\nPending\t$pending_content\nDownloads\t$total_downloads\nViews\t$total_views\nComments\t$total_comments\nAvg Rating\t$avg_rating\n\n";
    echo "CONTENT BY SUBJECT\n";
    foreach ($content_by_subject as $r)
        echo $r['name'] . "\t" . $r['count'] . "\n";
    echo "\nCONTENT BY LEVEL\n";
    foreach ($content_by_level as $r)
        echo $r['name'] . "\t" . $r['count'] . "\n";
    echo "\nTOP UPLOADERS\n";
    foreach ($top_uploaders as $r)
        echo $r['fullname'] . "\t" . $r['email'] . "\t" . $r['uploads'] . "\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa
        }

        .header {
            background: #1a1a2e;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px
        }

        .header a,
        .header button {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px
        }

        .btn-success {
            background: #27ae60
        }

        .btn-primary {
            background: #2980b9
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin-bottom: 30px
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08)
        }

        .stat-card h3 {
            font-size: 26px;
            color: #1a5276
        }

        .stat-card p {
            font-size: 13px;
            color: #999;
            margin-top: 5px
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08)
        }

        .section h3 {
            color: #1a5276;
            margin-bottom: 15px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px
        }

        th {
            background: #f8f9fa;
            font-weight: 600
        }

        @media print {
            .header {
                display: none
            }

            body {
                background: white
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h2><i class="fas fa-chart-bar"></i> System Report -
            <?= date('F j, Y') ?>
        </h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="report.php?format=excel" class="btn-success"><i class="fas fa-file-excel"></i> Download Excel</a>
            <button onclick="window.print()" style="background:white;color:#2980b9;"><i class="fas fa-print"></i> Print
                PDF</button>
            <a href="admin.php" style="background:white;color:#2980b9;"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>
                    <?= $total_users ?>
                </h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $total_students ?>
                </h3>
                <p>Students</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $total_teachers ?>
                </h3>
                <p>Teachers</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $total_content ?>
                </h3>
                <p>Total Books</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $approved_content ?>
                </h3>
                <p>Approved</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $pending_content ?>
                </h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= number_format($total_downloads) ?>
                </h3>
                <p>Downloads</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= number_format($total_views) ?>
                </h3>
                <p>Views</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $total_comments ?>
                </h3>
                <p>Comments</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?= $avg_rating ?>/5
                </h3>
                <p>Avg Rating</p>
            </div>
        </div>
        <div class="section">
            <h3>Content by Subject</h3>
            <table>
                <tr>
                    <th>Subject</th>
                    <th>Books</th>
                </tr>
                <?php foreach ($content_by_subject as $r): ?>
                    <tr>
                        <td>
                            <?= h($r['name']) ?>
                        </td>
                        <td>
                            <?= $r['count'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="section">
            <h3>Content by Level</h3>
            <table>
                <tr>
                    <th>Level</th>
                    <th>Books</th>
                </tr>
                <?php foreach ($content_by_level as $r): ?>
                    <tr>
                        <td>
                            <?= h($r['name']) ?>
                        </td>
                        <td>
                            <?= $r['count'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="section">
            <h3>Top Uploaders</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Uploads</th>
                </tr>
                <?php foreach ($top_uploaders as $r): ?>
                    <tr>
                        <td>
                            <?= h($r['fullname']) ?>
                        </td>
                        <td>
                            <?= h($r['email']) ?>
                        </td>
                        <td>
                            <?= $r['uploads'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>

</html>