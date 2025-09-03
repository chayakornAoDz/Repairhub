<?php
// issue.php  —  เบิกของ + แจ้งเตือนเข้า LINE
// NOTE: จัดการ POST ให้เสร็จก่อน แล้วค่อย render HTML (กัน header already sent)

require_once __DIR__ . '/inc/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// ----- mapping ตาราง/คอลัมน์ ให้เข้ากับฝั่ง admin -----
require_once __DIR__ . '/inc/inventory_map.php';
$MAP     = rh_get_inventory_map(db());
$TB_INV  = $MAP['TB_INV'];   $C_INV  = $MAP['C_INV'];
$TB_MOVE = $MAP['TB_MOVE'];  $C_MOVE = $MAP['C_MOVE'];

// ============== POST -> (บันทึก) -> Redirect (PRG) ==============
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  // ตรวจ CSRF
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = ['type'=>'error','text'=>'แบบฟอร์มหมดอายุ กรุณาลองใหม่'];
    header('Location: issue.php'); exit;
  }

  $item_id   = (int)($_POST['item_id'] ?? 0);
  $qty_req   = (float)($_POST['qty'] ?? 0);
  $requester = trim($_POST['requester'] ?? '');
  $note      = trim($_POST['note'] ?? '');

  if ($item_id <= 0 || $qty_req <= 0 || $requester === '') {
    $_SESSION['flash'] = ['type'=>'error','text'=>'กรุณาเลือกสินค้า ระบุจำนวน และผู้ขอเบิกให้ถูกต้อง'];
    header('Location: issue.php'); exit;
  }

  $pdo = db();
  $pdo->beginTransaction();
  try {
    // ดึงสินค้า
    $s = $pdo->prepare("SELECT {$C_INV['name']} name, {$C_INV['unit']} unit, {$C_INV['qty']} qty
                        FROM {$TB_INV} WHERE {$C_INV['id']}=?");
    $s->execute([$item_id]);
    $it = $s->fetch(PDO::FETCH_ASSOC);
    if (!$it) throw new Exception('ไม่พบสินค้า');
    if ((float)$it['qty'] < $qty_req) throw new Exception('สต็อคไม่พอ');

    // คำนวณคงเหลือใหม่
    $newBalance = (float)$it['qty'] - $qty_req;

    // อัปเดตคงเหลือ (คำนวณชื่อ table นอกสตริง หลีกเลี่ยง parse error)
    $invTable = $TB_INV;
    if (!empty($C_INV['table'])) { $invTable = $C_INV['table']; }

    $sqlUpdate = "UPDATE {$invTable} SET {$C_INV['qty']}=?, updated_at=? WHERE {$C_INV['id']}=?";
    $pdo->prepare($sqlUpdate)->execute([$newBalance, date('c'), $item_id]);

    // บันทึกความเคลื่อนไหว
    $ref  = 'ISS-'.date('Ymd-His');
    $cols = "{$C_MOVE['item_id']},{$C_MOVE['change_qty']},{$C_MOVE['type']},{$C_MOVE['ref']},{$C_MOVE['by']},{$C_MOVE['at']},{$C_MOVE['balance']}";
    $pdo->prepare("INSERT INTO {$TB_MOVE} ($cols) VALUES (?,?,?,?,?,?,?)")
        ->execute([$item_id, $qty_req, 'issue', $requester . ($note ? ' • '.$note : ''), null, date('c'), $newBalance]);

    $pdo->commit();

    // ===== แจ้งเตือนเข้า LINE (Messaging API Push) =====
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');   // e.g. /repairhub
    $appUrl = $scheme.'://'.$host.$base;

    $today = date('Y-m-d');
    $movementsLink = $appUrl . '/admin/inventory_movements.php?' . http_build_query([
      'from' => $today,
      'to'   => $today,
      'type' => 'issue',
      'item' => $item_id,
    ]);

    $msg  = "📦 เบิกของ"
          . "\nผู้ขอ: {$requester}"
          . "\nรายการ: {$it['name']} x{$qty_req} {$it['unit']}"
          . "\nคงเหลือ: " . rtrim(rtrim(number_format($newBalance,2,'.',''),'0'),'.') . " {$it['unit']}"
          . "\nเวลา: " . date('d/m/Y H:i')
          . "\nอ้างอิง: {$ref}"
          . "\nดูความเคลื่อนไหววันนี้: {$movementsLink}";

    // ส่ง LINE (ค่า token/target มาจาก app_settings)
    $line_ok = line_push_text($msg);
    if (!$line_ok) { line_notify("⚠️ แจ้งเตือนสำรอง\n".$msg); }

    $_SESSION['flash'] = [
      'type' => 'ok',
      'text' => 'บันทึกเบิกของสำเร็จ! อ้างอิง: '.$ref . ($line_ok ? ' • ส่ง LINE แล้ว' : ' • ส่ง LINE ไม่สำเร็จ')
    ];

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['type'=>'error','text'=>'บันทึกไม่สำเร็จ: '.$e->getMessage()];
  }

  header('Location: issue.php'); // PRG: กันรีเฟรชแล้วส่งซ้ำ
  exit;
}

// =================== GET: Render หน้า ===================
$page = 'issue';
$page_title = 'ระบบเบิกของ';
$extra_head_html = '';
$extra_foot_html = '';

require __DIR__ . '/inc/front_shell_open.php';

$alert = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// รายการสินค้า (สำหรับดรอปดาวน์ + ตาราง)
$pdo = db();
$items = $pdo->query("
  SELECT {$C_INV['id']}   id,
         {$C_INV['name']} name,
         {$C_INV['sku']}  sku,
         {$C_INV['unit']} unit,
         {$C_INV['qty']}  qty
  FROM {$TB_INV}
  ORDER BY {$C_INV['name']} ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ====== CSS เฉพาะหน้านี้: ปรับฟอร์ม/ตารางรองรับมือถือ ====== -->
<style>
  /* ฟอร์มทำรายการเบิก: 2 คอลัมน์บนจอใหญ่ / 1 คอลัมน์บนมือถือ */
  #issue-form .row{
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  #issue-form .row > div { min-width: 0; }
  @media (max-width: 640px){
    #issue-form .row{ grid-template-columns: 1fr; }
    #issue-form .btn{ width: 100%; }
  }

  /* ตารางสินค้าทั้งหมด -> การ์ดบนจอเล็ก */
  @media (max-width: 700px){
    .stack-table table,
    .stack-table thead,
    .stack-table tbody,
    .stack-table th,
    .stack-table td,
    .stack-table tr { display: block; width: 100%; }

    .stack-table thead { display: none; }

    .stack-table tbody{ padding: 0; margin: 0; }

    .stack-table tr{
      background: #0b1320;
      border: 1px solid #1f2a44;
      border-radius: 12px;
      padding: 8px 12px;
      margin: 10px 0;
    }

    .stack-table td{
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 12px;
      padding: 6px 0;
      border: 0;
    }

    .stack-table td::before{
      content: attr(data-label);
      color: #8aa0c6;               /* muted */
      font-size: 12px;
      flex: 0 0 auto;
      margin-right: 8px;
    }
  }
</style>

<div class="container">
  <div class="card hero shimmer">
    <div class="hero-left">
      <h1 class="hero-title">📦 ระบบเบิกของ</h1>
      <p class="hero-sub">เลือกสินค้าและจำนวนที่ต้องการ ระบบจะตัดสต็อค บันทึกประวัติ และส่งแจ้งเตือนเข้า LINE อัตโนมัติ</p>
    </div>
  </div>

  <?php if ($alert): ?>
    <div class="card" style="border-color:<?= ($alert['type']??'')==='ok' ? '#1e5f49' : '#5f1e27' ?>; color:#dbe3f3">
      <?= h($alert['text'] ?? '') ?>
    </div>
  <?php endif; ?>

  <div class="row" style="margin-top:16px">
    <form class="card" method="post" action="issue.php" id="issue-form" autocomplete="off">
      <h2 style="margin-top:0">ทำรายการเบิก</h2>

      <div>
        <label>สินค้า</label>
        <select name="item_id" required>
          <option value="">-- เลือกสินค้า --</option>
          <?php foreach($items as $it): ?>
            <option value="<?= (int)$it['id'] ?>">
              <?= h($it['name']) ?> (คงเหลือ <?= rtrim(rtrim(number_format($it['qty'],2,'.',''),'0'),'.') ?> <?= h($it['unit']) ?><?= $it['sku']? " / ".h($it['sku']):""; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <div><label>จำนวน</label><input type="number" step="0.01" name="qty" required></div>
        <div><label>ผู้ขอเบิก</label><input name="requester" required></div>
      </div>

      <div><label>หมายเหตุ</label><input name="note" placeholder="เช่น โครงการ/แผนก (ถ้ามี)"></div>

      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap">
        <button class="btn" type="reset">ล้างข้อมูล</button>
        <button class="btn btn-primary" type="submit" id="submit-btn">ยืนยันเบิก ➜</button>
      </div>
    </form>

    <div class="card">
      <h2 style="margin-top:0">สินค้าทั้งหมด</h2>

      <!-- .stack-table: ทำให้เป็นการ์ดบนจอเล็ก -->
      <div class="stack-table" style="max-height:420px;overflow:auto">
        <table class="table">
          <thead>
            <tr><th>ชื่อ</th><th>คงเหลือ</th><th>หน่วย</th><th>รหัส</th></tr>
          </thead>
          <tbody>
          <?php foreach($items as $it): ?>
            <tr>
              <td data-label="ชื่อ"><?= h($it['name']) ?></td>
              <td data-label="คงเหลือ"><?= rtrim(rtrim(number_format($it['qty'],2,'.',''),'0'),'.') ?></td>
              <td data-label="หน่วย"><?= h($it['unit']) ?></td>
              <td data-label="รหัส"><?= h($it['sku']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="muted" style="margin-top:8px">
        ดูความเคลื่อนไหวทั้งหมดที่
        <a class="btn" href="admin/inventory_movements.php" target="_blank">admin/inventory_movements.php</a>
      </p>
    </div>
  </div>
</div>

<script>
// กันดับเบิลคลิก / กันโพสต์ซ้ำระหว่างส่ง
document.getElementById('issue-form')?.addEventListener('submit', function(){
  const btn = document.getElementById('submit-btn');
  if (btn){ btn.disabled = true; btn.textContent = 'กำลังบันทึก...'; }
});
</script>

<?php require __DIR__ . '/inc/front_shell_close.php'; ?>
