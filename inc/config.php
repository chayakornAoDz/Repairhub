
<?php
// ตั้งค่าพื้นฐาน
define('APP_NAME', 'RepairHub Lite');
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') === '' ? '' : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));
date_default_timezone_set('Asia/Bangkok');

// DEV: show PHP errors (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// การตั้งค่า Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// สถานะงานมาตรฐาน
$TICKET_STATUSES = ['ใหม่', 'กำลังดำเนินการ', 'รออะไหล่', 'เสร็จสิ้น', 'ยกเลิก'];
?>
