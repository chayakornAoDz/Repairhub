<?php
// submit.php (robust columns + logging + LINE fallback)
require_once __DIR__ . '/inc/functions.php';

guard_csrf();
if (!empty($_POST['website'])) { redirect('index.php'); } // honeypot

$pdo = db();
date_default_timezone_set('Asia/Bangkok');

/* ---- helper: อ่านรายชื่อคอลัมน์ในตาราง ---- */
function table_columns(PDO $pdo, string $table): array {
  try {
    // SQLite
    $out = [];
    $rs = $pdo->query("PRAGMA table_info($table)");
    if ($rs) foreach ($rs as $r) $out[] = $r['name'];
    if ($out) return $out;
  } catch (Throwable $e) {}

  try {
    // MySQL
    $out = [];
    $rs = $pdo->query("DESCRIBE `$table`");
    if ($rs) foreach ($rs as $r) $out[] = $r['Field'];
    if ($out) return $out;
  } catch (Throwable $e) {}

  return [];
}

/* ---- helper: absolute URL (fallback ถ้าไม่มี rh_url/rh_link_track) ---- */
function rh_abs_url(string $path, array $qs = []): string {
  $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
           ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
  $host  = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  $root  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'); // เช่น /repairhub
  $url   = $proto . '://' . $host . $root . '/' . ltrim($path, '/');
  if ($qs) $url .= '?' . http_build_query($qs);
  return $url;
}

$cols = table_columns($pdo, 'requests');
$has  = fn($name)        => in_array($name, $cols, true);
$pick = function(array $candidates) use ($has) {
  foreach ($candidates as $c) if ($has($c)) return $c;
  return null;
};

/* ---- map คอลัมน์จริงใน DB ---- */
$colTicket      = $pick(['ticket_no','ticket','ticket_number']);
$colName        = $pick(['requester_name','requester','name','requestor']);
$colContact     = $pick(['contact','contact_info','phone','email']);
$colDepartment  = $pick(['department','dept']);
$colLocation    = $pick(['location','place','room']);
$colCategory    = $pick(['category','type']);
$colPriority    = $pick(['priority','importance','level']);
$colDescription = $pick(['description','details','detail','content']);
$colAttachment  = $pick(['attachment','file','file_path','attachment_path']);
$colStatus      = $pick(['status','state']);
$colCreatedAt   = $pick(['created_at','created','date','createdAt']);
$colUpdatedAt   = $pick(['updated_at','updated','modified_at','modifiedAt']);

/* ---- อ่านค่าจากฟอร์ม ---- */
$name        = trim($_POST['name'] ?? '');
$contact     = trim($_POST['contact'] ?? '');
$department  = trim($_POST['department'] ?? '');
$location    = trim($_POST['location'] ?? '');
$category    = trim($_POST['category'] ?? '');
$priority    = trim($_POST['priority'] ?? 'ปกติ');
$description = trim($_POST['description'] ?? '');
$attachment  = save_upload('attachment', __DIR__ . '/uploads');

/* ---- สร้าง Ticket ---- */
$ticket = 'RH-'.date('Ymd').'-0001';
if ($colTicket) {
  try { $ticket = gen_ticket_no($pdo); } catch (Throwable $e) {}
}

/* ---- เตรียมข้อมูล INSERT ตามคอลัมน์ที่มีจริง ---- */
$insertMap = [];
if ($colTicket)      $insertMap[$colTicket]      = $ticket;
if ($colName)        $insertMap[$colName]        = $name;
if ($colContact)     $insertMap[$colContact]     = $contact;
if ($colDepartment)  $insertMap[$colDepartment]  = $department;
if ($colLocation)    $insertMap[$colLocation]    = $location;
if ($colCategory)    $insertMap[$colCategory]    = $category;
if ($colPriority)    $insertMap[$colPriority]    = $priority;
if ($colDescription) $insertMap[$colDescription] = $description;
if ($colAttachment)  $insertMap[$colAttachment]  = $attachment;
if ($colStatus)      $insertMap[$colStatus]      = 'ใหม่';
$nowIso = date('c');
if ($colCreatedAt)   $insertMap[$colCreatedAt]   = $nowIso;
if ($colUpdatedAt)   $insertMap[$colUpdatedAt]   = $nowIso;

/* กันพัง: ต้องมีอย่างน้อย 1 คอลัมน์ */
if (!$insertMap) {
  app_log('submit.log','no columns match in requests table');
  http_response_code(500);
  exit("INSERT failed: requests table has no expected columns.");
}

/* ---- INSERT ---- */
try {
  $fields    = array_keys($insertMap);
  $marks     = implode(',', array_fill(0, count($fields), '?'));
  $fieldsEsc = array_map(fn($f)=>"`$f`", $fields);
  $sql       = 'INSERT INTO `requests` ('.implode(',', $fieldsEsc).") VALUES ($marks)";
  $stmt      = $pdo->prepare($sql);
  $stmt->execute(array_values($insertMap));
  $newId     = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
  app_log('submit.log','DB insert error: '.$e->getMessage().' | SQL='.$sql);
  http_response_code(500);
  exit('บันทึกไม่สำเร็จ');
}

/* ---- ลิงก์แบบ absolute สำหรับแนบใน LINE ---- */
if (function_exists('rh_link_track')) {
  $linkPublic = rh_link_track($ticket);
} else {
  $linkPublic = rh_abs_url('track.php', ['t'=>$ticket]);
}
if (function_exists('rh_url')) {
  $linkAdmin  = rh_url('admin/request_view.php', ['id'=>$newId]);
} else {
  $linkAdmin  = rh_abs_url('admin/request_view.php', ['id'=>$newId]);
}

/* ---- เตรียมข้อความแจ้งเตือน ---- */
$dispTicket = $ticket;
$dispName   = $name !== '' ? $name : '-';
$dispCat    = $category !== '' ? $category : '-';
$dispPri    = $priority !== '' ? $priority : '-';

$msg  = "🆕 มีงานใหม่เข้าระบบ\n";
$msg .= "Ticket: {$dispTicket}\n";
$msg .= "ผู้แจ้ง: {$dispName}\n";
$msg .= "ประเภท: {$dispCat}\n";
$msg .= "สำคัญ: {$dispPri}\n";
if ($department !== '') $msg .= "แผนก: {$department}\n";
if ($location   !== '') $msg .= "สถานที่: {$location}\n";
$msg .= "เวลา: ".date('d/m H:i')."\n";
$msg .= "🔗 ติดตามสถานะ: {$linkPublic}\n";
$msg .= "🔗 เปิดในแอดมิน: {$linkAdmin}";

/* ---- LOG ก่อนส่ง (ช่วยดีบัก) ---- */
app_log('line_push.log', '[submit] compose -> '.str_replace(["\r","\n"], ' | ', $msg));

/* ---- ส่ง LINE (Messaging API) ---- */
$token = get_setting('line_channel_access_token', '');
$to    = get_setting('line_target_id', '');

app_log('line_push.log', '[submit] settings -> tokenLen=' . strlen(trim((string)$token)) . ' to=' . trim((string)$to));

$ok = line_push_text($msg, $to, $token);
if (!$ok) {
  app_log('line_push.log', '[submit] push FAILED -> try LINE Notify');
  line_notify("⚠️ แจ้งเตือนสำรอง\n".$msg);
} else {
  app_log('line_push.log', '[submit] push OK');
}

/* ---- เปลี่ยนหน้าไปติดตามงาน ---- */
$trackTicket = $dispTicket ?: ('RH-'.date('Ymd').'-'.str_pad($newId,4,'0',STR_PAD_LEFT));
redirect('track.php?t=' . urlencode($trackTicket));
