<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('auth.php?action=login');
}

$content_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$content_id)
    die('Invalid ID');

$db = getDB();
$stmt = $db->prepare("SELECT c.*, u.fullname as author_name, s.name as subject_name, el.name as level_name FROM content c LEFT JOIN users u ON c.uploaded_by = u.id LEFT JOIN subjects s ON c.subject_id = s.id LEFT JOIN education_levels el ON c.education_level_id = el.id WHERE c.id = ? AND (c.approved = 1 OR c.uploaded_by = ?) LIMIT 1");
$stmt->execute([$content_id, $_SESSION['user_id']]);
$content = $stmt->fetch();
if (!$content)
    die('Not found.');

$filepath = UPLOAD_DIR . $content['file_path'];
if (!file_exists($filepath))
    die('File missing.');

$stmt = $db->prepare("UPDATE content SET views = views + 1 WHERE id = ?");
$stmt->execute([$content_id]);
$stmt = $db->prepare("INSERT INTO reading_logs (content_id, user_id) VALUES (?, ?)");
$stmt->execute([$content_id, $_SESSION['user_id']]);

$ext = strtolower($content['file_type']);
$is_pdf = ($ext === 'pdf');
$is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
$is_text = ($ext === 'txt');
$file_url = 'backend.php?action=serve&id=' . $content_id;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading: <?= h($content['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column
        }

        .reader-header {
            background: #0f3460;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px
        }

        .reader-header h3 {
            font-size: 16px;
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            color: white
        }

        .btn-success {
            background: #27ae60
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white
        }

        .reader-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden
        }

        .reader-container embed,
        .reader-container img {
            max-width: 100%;
            max-height: 100%
        }

        .reader-container embed {
            width: 100%;
            height: 100%;
            border: none
        }

        .reader-container img {
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5)
        }

        .reader-container .text-content {
            background: white;
            color: #333;
            padding: 40px;
            max-width: 900px;
            width: 95%;
            max-height: 90%;
            overflow-y: auto;
            border-radius: 12px;
            white-space: pre-wrap;
            font-size: 16px;
            line-height: 1.8
        }
    </style>
</head>

<body>
    <div class="reader-header">
        <div>
            <h3><?= h($content['title']) ?></h3><small><?= h($content['subject_name']) ?> |
                <?= h($content['level_name']) ?></small>
        </div>
        <div style="display:flex;gap:8px">
            <a href="backend.php?action=download&id=<?= $content_id ?>" class="btn btn-success"><i
                    class="fas fa-download"></i> Download</a>
            <a href="system.php?page=detail&view=<?= $content_id ?>" class="btn btn-outline"><i
                    class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <div class="reader-container">
        <?php if ($is_pdf): ?><embed src="<?= $file_url ?>" type="application/pdf">
        <?php elseif ($is_image): ?><img src="<?= $file_url ?>" alt="<?= h($content['title']) ?>">
        <?php elseif ($is_text): ?>
            <div class="text-content"><?= nl2br(h(file_get_contents($filepath))) ?></div>
        <?php else: ?>
            <div style="text-align:center"><i class="fas <?= getFileIcon($ext) ?>" style="font-size:80px;opacity:0.5"></i>
                <h3>Preview Not Available</h3>
                <p><?= strtoupper($ext) ?> files cannot be previewed.</p><a
                    href="backend.php?action=download&id=<?= $content_id ?>" class="btn btn-success"
                    style="margin-top:15px">Download to View</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>