
<?php
// ---- Logging setup (auto-create storage) ----
$root   = dirname(__DIR__);                 // .../repairhub
$logDir = $root . DIRECTORY_SEPARATOR . 'storage';

// สร้างโฟลเดอร์ถ้ายังไม่มี
if (!is_dir($logDir)) {
  @mkdir($logDir, 0775, true);
}

// ถ้าโฟลเดอร์ยังเขียนไม่ได้ ให้ fallback ไป temp
if (!is_dir($logDir) || !is_writable($logDir)) {
  $logDir = sys_get_temp_dir();             // เช่น C:\Windows\Temp
}

// กำหนดไฟล์ล็อก
$logFileReadable = $logDir . DIRECTORY_SEPARATOR . 'line_events_readable.log';
$logFileRaw      = $logDir . DIRECTORY_SEPARATOR . 'line_events_raw.log';

// ฟังก์ชันเขียนล็อก
function log_append($file, $text) {
  $dt = date('c');
  @file_put_contents($file, "[$dt] $text\n", FILE_APPEND | LOCK_EX);
}

// โหมดทดสอบ: เปิดในเบราว์เซอร์ด้วย ?ping=1 เพื่อตรวจว่าบันทึกล็อกได้
if (isset($_GET['ping'])) {
  log_append($logFileReadable, 'PING from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
  echo "wrote to: " . htmlspecialchars($logFileReadable);
  exit;
}

// line_webhook.php
file_put_contents(__DIR__.'/../storage/line_events.log',
  date('c')." RAW: ".file_get_contents('php://input')."\n",
  FILE_APPEND);

http_response_code(200);

// แตก JSON แล้ว log ค่า ID ที่สำคัญให้อ่านง่าย
$data = json_decode(file_get_contents('php://input'), true);
if (!empty($data['events'])) {
  foreach ($data['events'] as $ev) {
    $src = $ev['source'] ?? [];
    $uid = $src['userId']  ?? '';
    $gid = $src['groupId'] ?? '';
    $rid = $src['roomId']  ?? '';
    $type= $ev['type']     ?? '';
    $line = sprintf("[%s] type=%s userId=%s groupId=%s roomId=%s\n",
      date('c'), $type, $uid, $gid, $rid);
    file_put_contents(__DIR__.'/../storage/line_events_readable.log', $line, FILE_APPEND);
  }
}
