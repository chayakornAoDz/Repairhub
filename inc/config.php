<?php
// ตั้งค่าพื้นฐาน
define('APP_NAME', 'RepairHub Lite');

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
define('BASE_URL', $base === '/' ? '' : $base);

date_default_timezone_set('Asia/Bangkok');

// DEV: แสดง error (ปิดใน production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ---------------- Session config (เฉพาะตอนยังไม่เริ่ม session) ---------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    // ปลอดภัยขึ้นสำหรับ cookie ของ session
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');

    // ตั้งตามโปรโตคอลปัจจุบัน
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    // ป้องกัน CSRF ข้ามโดเมน (รองรับ PHP 7.3+)
    ini_set('session.cookie_samesite', 'Lax'); // หรือ 'Strict' ถ้าไม่ใช้ cross-site
}

// สถานะงานมาตรฐาน
$TICKET_STATUSES = ['ใหม่', 'กำลังดำเนินการ', 'รออะไหล่', 'เสร็จสิ้น', 'ยกเลิก'];
