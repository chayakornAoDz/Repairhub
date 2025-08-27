<?php
// download.php
$base = __DIR__ . '/uploads/';

// รับชื่อไฟล์จาก GET และ sanitize
$fname = basename($_GET['f'] ?? '');
$path  = $base . $fname;

if (!$fname || !is_file($path)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

// ตรวจสอบ MIME type
$mime = mime_content_type($path);
header("Content-Type: $mime");
header("Content-Length: " . filesize($path));
header("Content-Disposition: inline; filename=\"$fname\""); // inline = เปิดใน browser, attachment = โหลด

readfile($path);
exit;
