<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* ------------------- Utils ------------------- */
if (!function_exists('h')) {
  function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

/**
 * ลิงก์แบ่งหน้าแบบปลอดภัย: จะชี้ไป "ไฟล์ปัจจุบัน" เสมอ เช่น inventory.php?page=2
 * ใช้ในทุกหน้าที่มี pagination เพื่อลดปัญหา /admin/?page=2 กลายเป็น Directory Listing
 */
if (!function_exists('rh_page_url')) {
  function rh_page_url(int $page, array $extra = [], ?string $script = null): string {
    if (!$script) {
      $script = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '');
    }
    // เก็บ query string เดิม ยกเว้น 'page'
    $qs = $_GET ?? [];
    unset($qs['page']);
    $qs = array_merge($qs, $extra, ['page' => max(1, $page)]);
    return $script . '?' . http_build_query($qs);
  }
}

/**
 * เรนเดอร์ Pager ให้ใช้งานซ้ำได้ทุกหน้า
 * ตัวอย่างใช้:
 *   echo rh_render_pager($page, $totalPages);
 */
if (!function_exists('rh_render_pager')) {
  function rh_render_pager(int $page, int $totalPages, int $window = 2, array $extra = [], ?string $script = null): string {
    $mk = function($p) use ($extra, $script){ return rh_page_url($p, $extra, $script); };
    $page   = max(1, $page);
    $totalPages = max(1, $totalPages);

    ob_start(); ?>
    <nav class="pagination" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="<?= h($mk(1)) ?>">« หน้าแรก</a>
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="<?= h($mk(max(1,$page-1))) ?>">‹ ก่อนหน้า</a>

      <?php
        $start = max(1, $page - $window);
        $end   = min($totalPages, $page + $window);
        for ($p = $start; $p <= $end; $p++):
      ?>
        <a class="page-num <?= $p===$page?'active':'' ?>" href="<?= h($mk($p)) ?>"><?= (int)$p ?></a>
      <?php endfor; ?>

      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= h($mk(min($totalPages,$page+1))) ?>">ถัดไป ›</a>
      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= h($mk($totalPages)) ?>">สุดท้าย »</a>
    </nav>
    <?php
    return ob_get_clean();
  }
}

function gen_ticket_no(PDO $pdo) {
  $date = date('Ymd');
  $prefix = "RH-$date-";
  $stmt = $pdo->prepare('SELECT ticket_no FROM requests WHERE ticket_no LIKE ? ORDER BY id DESC LIMIT 1');
  $stmt->execute([$prefix . '%']);
  $last = $stmt->fetchColumn();
  $num = 1;
  if ($last) {
      $parts = explode('-', $last);
      $num = intval(end($parts)) + 1;
  }
  return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function save_upload($field, $targetDir) {
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
  $ext  = pathinfo($_FILES[$field]['name'] ?? '', PATHINFO_EXTENSION);
  $safe = uniqid('file_', true) . ($ext ? ('.' . strtolower($ext)) : '');
  $dest = rtrim($targetDir, '/').'/'.$safe;
  if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
  if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
      return $safe;
  }
  return null;
}

function redirect($url) {
  header("Location: $url");
  exit;
}

function parse_date($str) {
  $ts = strtotime($str ?? '');
  return $ts ? date('Y-m-d', $ts) : null;
}

function guard_csrf() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
          http_response_code(400);
          echo 'CSRF token invalid';
          exit;
      }
  }
}

/* ------------------- App Settings (key/value) ------------------- */
function ensure_settings_table(PDO $pdo){
  $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
    key   TEXT PRIMARY KEY,
    value TEXT
  )");
}

function set_setting($key, $value){
  $pdo = db();
  ensure_settings_table($pdo);
  $stmt = $pdo->prepare("REPLACE INTO app_settings(key,value) VALUES(?,?)");
  $stmt->execute([$key, $value]);
}

function get_setting($key, $default=null){
  $pdo = db();
  ensure_settings_table($pdo);
  $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE key=?");
  $stmt->execute([$key]);
  $v = $stmt->fetchColumn();
  return $v !== false ? $v : $default;
}

/* ------------------- Small logger to storage/ ------------------- */
function app_log($filename, $line){
  $dir = __DIR__ . '/../storage';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  @file_put_contents($dir . '/' . $filename, '['.date('c').'] '.$line."\n", FILE_APPEND);
}

/* ------------------- LINE Messaging API (Push) -------------------
   ต้องตั้งค่าใน app_settings:
   - line_channel_access_token : Channel access token (long-lived)
   - line_target_id            : U... (user) / C... (group) / R... (room)
------------------------------------------------------------------ */
function line_push_text(string $text, ?string $toOverride = null, ?string $tokenOverride = null): bool {
  $token = trim((string) ($tokenOverride ?: get_setting('line_channel_access_token', '')));
  $to    = trim((string) ($toOverride   ?: get_setting('line_target_id', '')));

  if ($token === '' || $to === '') {
    app_log('line_push.log', 'SKIP push: missing token/target');
    return false;
  }

  // ตรวจรูปแบบ target แบบหลวม ๆ (ขึ้นต้นด้วย U/C/R และความยาวประมาณ 21+ อักขระ)
  if (!preg_match('/^[UCR][0-9a-z]{20,}$/i', $to)) {
    app_log('line_push.log', 'SKIP push: invalid target format: '.$to);
    return false;
  }

  $url = 'https://api.line.me/v2/bot/message/push';
  $payload = json_encode([
    'to' => $to,
    'messages' => [[ 'type' => 'text', 'text' => $text ]]
  ], JSON_UNESCAPED_UNICODE);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 15,
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($http < 200 || $http >= 300){
    app_log('line_push.log', "FAIL HTTP:$http | RES:$res | ERR:$err | PAYLOAD:$payload");
    return false;
  }

  app_log('line_push.log', "OK HTTP:$http | RES:$res");
  return true;
}

/* ------------------- LINE Notify (legacy) -------------------
   ยังเก็บไว้เผื่อกลับไปใช้แจ้งเตือนแบบเดิมได้
   ใช้ setting key: line_notify_token
------------------------------------------------------------- */
function line_notify($message, $overrideToken=null){
  $token = $overrideToken ?: get_setting('line_notify_token');
  if (!$token) {
    app_log('line_notify.log', 'SKIP notify: missing token');
    return false;
  }

  $ch = curl_init('https://notify-api.line.me/api/notify');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['message' => $message]),
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$token],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($http !== 200){
    app_log('line_notify.log', "FAIL HTTP:$http | RES:$res | ERR:$err");
    return false;
  }

  app_log('line_notify.log', "OK HTTP:$http | RES:$res");
  return true;
}

/* ===========================================================
 *                URL helpers for LINE (ใหม่)
 *  - สร้างลิงก์ absolute พร้อมพารามิเตอร์ สำหรับแปะในข้อความ LINE
 *  - รองรับ proxy header (X-Forwarded-Proto/Host)
 *  - ใช้ path แบบ relative จากรากของแอป (root) เช่น:
 *      rh_url('admin/inventory_movements.php', ['from'=>'2025-01-01', ...])
 * =========================================================== */

/** scheme ที่ถูกต้อง (http/https) โดยให้ความสำคัญ X-Forwarded-Proto */
if (!function_exists('rh_http_scheme')) {
  function rh_http_scheme(): string {
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($xfp) return strtolower($xfp)==='https' ? 'https' : 'http';
    $https = $_SERVER['HTTPS'] ?? '';
    return ($https && strtolower($https) !== 'off') ? 'https' : 'http';
  }
}

/** host (อาจรวมพอร์ต) โดยให้ความสำคัญ X-Forwarded-Host */
if (!function_exists('rh_http_host')) {
  function rh_http_host(): string {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    // ความปลอดภัยเบื้องต้น
    $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', (string)$host);
    return $host ?: 'localhost';
  }
}

/** base path ของแอป (เช่น /app ถ้าใช้งานใต้โฟลเดอร์) */
if (!function_exists('rh_app_base_path')) {
  function rh_app_base_path(): string {
    $path = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    // ถ้า call จาก /admin/... ให้ถอยขึ้นหนึ่งชั้นเป็นรากโปรเจ็กต์
    if (preg_match('~/admin/?$~', $path)) {
      $path = dirname($path);
    }
    $path = rtrim(str_replace('\\','/',$path), '/');
    return $path === '' ? '' : $path;
  }
}

/** base URL (เช่น https://example.com หรือ https://example.com/app) */
if (!function_exists('rh_base_url')) {
  function rh_base_url(): string {
    $scheme = rh_http_scheme();
    $host   = rh_http_host();
    $base   = rh_app_base_path();
    return $scheme . '://' . $host . ($base ? $base : '');
  }
}

/** สร้าง absolute URL จาก path ภายในแอป + query string */
if (!function_exists('rh_url')) {
  function rh_url(string $relPath, array $qs = []): string {
    $relPath = ltrim($relPath, '/');
    $url = rtrim(rh_base_url(), '/') . '/' . $relPath;
    if (!empty($qs)) {
      $q = http_build_query($qs);
      if ($q !== '') $url .= '?' . $q;
    }
    return $url;
  }
}

/** ลิงก์ไปหน้าความเคลื่อนไหวสต็อก พร้อมตัวกรอง */
if (!function_exists('rh_link_movements')) {
  function rh_link_movements(array $filters = []): string {
    return rh_url('admin/inventory_movements.php', $filters);
  }
}

/** ลิงก์ไปหน้ารายการยืม (แอดมิน) */
if (!function_exists('rh_link_loans')) {
  function rh_link_loans(array $qs = []): string {
    return rh_url('admin/loans.php', $qs);
  }
}

/** ลิงก์ติดตามงานด้วยหมายเลข Ticket (สาธารณะ) */
if (!function_exists('rh_link_track')) {
  function rh_link_track(string $ticketNo): string {
    return rh_url('track.php', ['t' => $ticketNo]);
  }
}

/** ผู้ใช้ (สาธารณะ): หน้าเบิก/ยืม */
if (!function_exists('rh_link_issue_public')) {
  function rh_link_issue_public(): string { return rh_url('issue.php'); }
}
if (!function_exists('rh_link_loan_public')) {
  function rh_link_loan_public(): string { return rh_url('user_loan.php'); }
}

/** ช่วยสร้างลิงก์ความเคลื่อนไหว “วันนี้” ตามประเภทและสินค้า */
if (!function_exists('rh_link_movements_today')) {
  function rh_link_movements_today(string $type = '', int $itemId = 0, int $who = 0): string {
    $today = date('Y-m-d');
    $q = ['from'=>$today, 'to'=>$today];
    if ($type !== '') $q['type'] = $type;      // issue|in|out|return|adjust
    if ($itemId > 0) $q['item']  = $itemId;
    if ($who > 0)    $q['who']   = $who;
    return rh_link_movements($q);
  }
}
