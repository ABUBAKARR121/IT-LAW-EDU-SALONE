<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('auth.php?action=login');
}

$content_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$content_id) {
    die('Invalid content ID.');
}

$db = getDB();
$stmt = $db->prepare("
    SELECT c.*, u.fullname as uploader_name, u.email as uploader_email, 
           u.district, u.school_name, s.name as subject_name, el.name as level_name 
    FROM content c 
    LEFT JOIN users u ON c.uploaded_by = u.id 
    LEFT JOIN subjects s ON c.subject_id = s.id 
    LEFT JOIN education_levels el ON c.education_level_id = el.id 
    WHERE c.id = ? 
    LIMIT 1
");
$stmt->execute([$content_id]);
$content = $stmt->fetch();

if (!$content) {
    die('Content not found.');
}

$filepath = UPLOAD_DIR . $content['file_path'];
$file_exists = file_exists($filepath);
$ext = strtolower($content['file_type']);
$is_pdf = ($ext === 'pdf');
$is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
$is_text = ($ext === 'txt');
$file_url = 'backend.php?action=serve&id=' . $content_id;
$download_url = 'download.php?id=' . $content_id;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review:
        <?= h($content['title']) ?> - EduSalone Share
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .top-bar {
            background: #1a1a2e;
            color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-bar h2 {
            font-size: 18px;
        }

        .top-bar a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            transition: all 0.3s;
        }

        .top-bar a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            max-width: 1300px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .review-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 25px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .info-card h3 {
            color: #1a5276;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .info-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item .label {
            width: 130px;
            color: #666;
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }

        .info-item .value {
            flex: 1;
            font-size: 14px;
            word-break: break-word;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 700;
            background: #fff3cd;
            color: #856404;
        }

        .preview-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
        }

        .preview-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-header h4 {
            font-size: 14px;
            color: #555;
        }

        .preview-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            background: #fafafa;
        }

        .preview-body embed {
            width: 100%;
            height: 600px;
            border: none;
        }

        .preview-body img {
            max-width: 100%;
            max-height: 600px;
            object-fit: contain;
            padding: 20px;
        }

        .preview-body .text-content {
            width: 100%;
            height: 600px;
            overflow-y: auto;
            padding: 30px;
            white-space: pre-wrap;
            font-size: 15px;
            line-height: 1.8;
            background: white;
        }

        .preview-fallback {
            text-align: center;
            padding: 60px 30px;
        }

        .preview-fallback .big-icon {
            font-size: 90px;
            color: #3498db;
            margin-bottom: 25px;
            display: block;
        }

        .preview-fallback h3 {
            margin-bottom: 12px;
            color: #2c3e50;
        }

        .preview-fallback p {
            color: #777;
            margin-bottom: 25px;
            font-size: 15px;
        }

        .preview-fallback .note {
            font-size: 13px;
            color: #999;
            margin-top: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            color: white;
        }

        .btn-download {
            background: #3498db;
        }

        .btn-download:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-approve {
            background: #27ae60;
            font-size: 15px;
            padding: 14px 30px;
        }

        .btn-approve:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
        }

        .btn-reject {
            background: #e74c3c;
            font-size: 15px;
            padding: 14px 30px;
        }

        .btn-reject:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-back {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-back:hover {
            background: white;
            color: #1a1a2e;
        }

        .actions-bar {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .actions-bar .divider {
            color: #ccc;
            font-size: 20px;
        }

        .reject-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .reject-form select {
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            min-width: 220px;
            background: white;
        }

        .reject-form select:focus {
            outline: none;
            border-color: #e74c3c;
        }

        @media (max-width: 900px) {
            .review-grid {
                grid-template-columns: 1fr;
            }

            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .actions-bar .divider {
                display: none;
            }

            .reject-form {
                flex-direction: column;
            }

            .reject-form select {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="top-bar">
        <h2>
            <i class="fas fa-eye" style="margin-right:10px;"></i>
            Reviewing:
            <?= h($content['title']) ?>
        </h2>
        <a href="admin.php?page=pending" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Pending
        </a>
    </div>

    <div class="container">
        <div class="review-grid">

            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Book Information</h3>

                <div class="info-item">
                    <span class="label">Status</span>
                    <span class="value"><span class="status-badge">Pending Review</span></span>
                </div>
                <div class="info-item">
                    <span class="label">Title</span>
                    <span class="value"><strong>
                            <?= h($content['title']) ?>
                        </strong></span>
                </div>
                <div class="info-item">
                    <span class="label">Subject</span>
                    <span class="value">
                        <?= h($content['subject_name']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Level</span>
                    <span class="value">
                        <?= h($content['level_name']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Type</span>
                    <span class="value">
                        <?= h(ucfirst(str_replace('_', ' ', $content['content_type']))) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">File Type</span>
                    <span class="value">
                        <?= strtoupper($ext) ?> (
                        <?= formatFileSize($content['file_size']) ?>)
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">File Status</span>
                    <span class="value">
                        <?php if ($file_exists): ?>
                            <span style="color:#27ae60;"><i class="fas fa-check-circle"></i> File exists on server</span>
                        <?php else: ?>
                            <span style="color:#e74c3c;"><i class="fas fa-times-circle"></i> File missing</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Tags</span>
                    <span class="value">
                        <?= h($content['tags'] ?: 'None') ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Uploaded By</span>
                    <span class="value">
                        <?= h($content['uploader_name']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Email</span>
                    <span class="value">
                        <?= h($content['uploader_email']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">District</span>
                    <span class="value">
                        <?= h($content['district'] ?: 'N/A') ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">School</span>
                    <span class="value">
                        <?= h($content['school_name'] ?: 'N/A') ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Upload Date</span>
                    <span class="value">
                        <?= date('F j, Y - h:i A', strtotime($content['created_at'])) ?>
                    </span>
                </div>
                <div class="info-item" style="flex-direction:column;">
                    <span class="label" style="margin-bottom:8px;">Description</span>
                    <span class="value" style="line-height:1.7;">
                        <?= nl2br(h($content['description'] ?: 'No description provided.')) ?>
                    </span>
                </div>
            </div>

            <div class="preview-card">
                <div class="preview-header">
                    <h4><i class="fas fa-file"></i> File Preview</h4>
                    <a href="<?= $download_url ?>" class="btn btn-download btn-sm">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
                <div class="preview-body">
                    <?php if (!$file_exists): ?>
                        <div class="preview-fallback">
                            <i class="fas fa-exclamation-triangle big-icon" style="color:#e74c3c;"></i>
                            <h3>File Missing</h3>
                            <p>The uploaded file could not be found on the server. It may have been deleted or moved.</p>
                        </div>
                    <?php elseif ($is_pdf): ?>
                        <embed src="<?= $file_url ?>" type="application/pdf">
                    <?php elseif ($is_image): ?>
                        <img src="<?= $file_url ?>" alt="Preview">
                    <?php elseif ($is_text): ?>
                        <div class="text-content">
                            <?= nl2br(h(file_get_contents($filepath))) ?>
                        </div>
                    <?php else: ?>
                        <div class="preview-fallback">
                            <i class="fas <?= getFileIcon($ext) ?> big-icon"></i>
                            <h3>Preview Not Available</h3>
                            <p>
                                This file type (<strong>
                                    <?= strtoupper($ext) ?>
                                </strong>) cannot be previewed directly in the browser.<br>
                                Please download the file to review its content before approving or rejecting.
                            </p>
                            <a href="<?= $download_url ?>" class="btn btn-download"
                                style="font-size:16px; padding:16px 32px;">
                                <i class="fas fa-download"></i> Download to Review
                            </a>
                            <p class="note">
                                <i class="fas fa-info-circle"></i>
                                After downloading, you can open this
                                <?= strtoupper($ext) ?> file with Microsoft Word,
                                LibreOffice, or any compatible application on your computer.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="actions-bar">
            <a href="admin.php?approve=<?= $content_id ?>&page=pending" class="btn btn-approve"
                onclick="return confirm('APPROVE this book?\n\nIt will become visible to all users immediately.\n\nThis action cannot be undone.');">
                <i class="fas fa-check-circle"></i> Approve This Book
            </a>

            <span class="divider">|</span>

            <form method="GET" action="admin.php" class="reject-form"
                onsubmit="return confirm('REJECT this book?\n\nThe teacher will be notified of your decision.\n\nThis action cannot be undone.');">
                <input type="hidden" name="page" value="pending">
                <input type="hidden" name="reject" value="<?= $content_id ?>">
                <select name="reason" required>
                    <option value="">-- Select Rejection Reason --</option>
                    <option value="Inappropriate content">Inappropriate content</option>
                    <option value="Copyright violation">Copyright violation</option>
                    <option value="Poor quality">Poor quality - not suitable for learning</option>
                    <option value="Wrong category or level">Wrong subject category or education level</option>
                    <option value="Duplicate content">Duplicate of existing content</option>
                    <option value="Incomplete or corrupted file">File is incomplete or corrupted</option>
                    <option value="Not aligned with curriculum">Not aligned with national curriculum</option>
                    <option value="Insufficient description">Insufficient description or metadata</option>
                    <option value="Other">Other reason</option>
                </select>
                <button type="submit" class="btn btn-reject">
                    <i class="fas fa-times-circle"></i> Reject This Book
                </button>
            </form>
        </div>
    </div>

</body>

</html>