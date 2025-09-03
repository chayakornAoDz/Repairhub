<?php
// --- ห้ามมีช่องว่างหรือ BOM ก่อนแท็ก PHP ---
require_once __DIR__ . '/inc/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// ===== ต้องใช้ mapping ตั้งแต่ขั้น POST =====
require_once __DIR__ . '/inc/inventory_map.php';
$MAP     = rh_get_inventory_map(db());
$TB_INV  = $MAP['TB_INV'];   $C_INV  = $MAP['C_INV'];
$TB_MOVE = $MAP['TB_MOVE'];  $C_MOVE = $MAP['C_MOVE'];
$TB_LOAN = $MAP['TB_LOAN'];  $C_LOAN = $MAP['C_LOAN'];

/* ========= PRG + one-time nonce (ทำก่อนมี output) ========= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $csrf_ok  = hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
  $nonce    = $_POST['nonce'] ?? '';
  $nonce_ok = ($nonce !== '') &&
              hash_equals($_SESSION['loan_nonce'] ?? '', $nonce) &&
              empty($_SESSION['loan_nonce_used'][$nonce]);

  if (!$csrf_ok || !$nonce_ok) {
    $_SESSION['flash'] = ['type'=>'error','text'=>'แบบฟอร์มหมดอายุ หรือส่งซ้ำ โปรดลองใหม่'];
    header('Location: user_loan.php'); exit;
  }
  $_SESSION['loan_nonce_used'][$nonce] = true;

  // ---- บันทึกการยืม ----
  $item_id   = (int)($_POST['item_id']??0);
  $qty       = (float)($_POST['qty']??0);
  $borrower  = trim($_POST['borrower']??'');
  $contact   = trim($_POST['contact']??'');
  $due_date  = trim($_POST['due_date']??'');

  if ($item_id<=0 || $qty<=0 || $borrower==='') {
    $_SESSION['flash'] = ['type'=>'error','text'=>'กรอกข้อมูลให้ครบถ้วน'];
    header('Location: user_loan.php'); exit;
  }

  $pdo = db();
  $pdo->beginTransaction();
  try{
    $s = $pdo->prepare("SELECT {$C_INV['name']} name, {$C_INV['unit']} unit, {$C_INV['qty']} qty
                        FROM {$TB_INV} WHERE {$C_INV['id']}=?");
    $s->execute([$item_id]);
    $it = $s->fetch(PDO::FETCH_ASSOC);
    if(!$it) throw new Exception('ไม่พบสินค้า');
    if($it['qty'] < $qty) throw new Exception('สต็อคไม่พอ');

    $newBalance = $it['qty'] - $qty;

    // อัปเดตคงเหลือ
    $pdo->prepare("UPDATE {$TB_INV} SET {$C_INV['qty']}=?, updated_at=? WHERE {$C_INV['id']}=?")
        ->execute([$newBalance, date('c'), $item_id]);

    // บันทึก loans (ถ้าระบบมีตาราง)
    if ($TB_LOAN && $C_LOAN) {
      $pdo->prepare("
        INSERT INTO {$TB_LOAN}
          ({$C_LOAN['borrower']},{$C_LOAN['contact']},{$C_LOAN['item_id']},{$C_LOAN['qty']},loan_date,{$C_LOAN['due']},{$C_LOAN['status']},note,created_by,{$C_LOAN['at']})
        VALUES (?,?,?,?,?,?,?,?,?,?)
      ")->execute([
        $borrower, $contact, $item_id, $qty, date('Y-m-d'), ($due_date?:null), 'ยืมอยู่', '', null, date('c')
      ]);
    }

    // ความเคลื่อนไหวสต็อค (out)
    $ref  = 'LOAN-'.date('Ymd-His');
    $cols = "{$C_MOVE['item_id']},{$C_MOVE['change_qty']},{$C_MOVE['type']},{$C_MOVE['ref']},{$C_MOVE['by']},{$C_MOVE['at']},{$C_MOVE['balance']}";
    $pdo->prepare("INSERT INTO {$TB_MOVE} ($cols) VALUES (?,?,?,?,?,?,?)")
        ->execute([$item_id, $qty, 'out', $borrower.($contact?' • '.$contact:''), null, date('c'), $newBalance]);

    $pdo->commit();

    // ===== แจ้งเตือนเข้า LINE (Messaging API Push) =====
    // สร้าง base URL ของแอป เช่น http://localhost/repairhub
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appUrl = $scheme.'://'.$host.$base;

    // ลิงก์ไปหน้ารายการยืม (ผู้ดูแล)
    $loanLink = $appUrl . '/admin/loans.php';

    $msg  = "🧾 ยืมอุปกรณ์"
          . "\nผู้ยืม: {$borrower}"
          . ($contact ? "\nติดต่อ: {$contact}" : '')
          . "\nรายการ: {$it['name']} x{$qty} {$it['unit']}"
          . "\nคงเหลือ: " . rtrim(rtrim(number_format($newBalance,2,'.',''),'0'),'.') . " {$it['unit']}"
          . "\nกำหนดคืน: " . ($due_date ?: '-')
          . "\nเวลา: " . date('d/m/Y H:i')
          . "\nอ้างอิง: {$ref}"
          . "\nดูรายการยืม: {$loanLink}";

    $sent = line_push_text($msg);  // true/false

    $_SESSION['flash'] = [
      'type'=>'ok',
      'text'=>'บันทึกการยืมสำเร็จ! อ้างอิง: '.$ref.($sent?' • ส่ง LINE แล้ว':' • ส่ง LINE ไม่สำเร็จ')
    ];
  }catch(Throwable $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['type'=>'error','text'=>'ไม่สามารถบันทึกได้: '.$e->getMessage()];
  }

  header('Location: user_loan.php');  // PRG
  exit;
}

/* ========= ส่วน GET ========= */
$alert = $_SESSION['flash'] ?? null;   // รับข้อความหลัง redirect
unset($_SESSION['flash']);

// nonce ใหม่สำหรับรอบถัดไป
$form_nonce = bin2hex(random_bytes(16));
$_SESSION['loan_nonce'] = $form_nonce;

// ข้อมูลสำหรับ dropdown
$pdo = db();
$items = $pdo->query("
  SELECT {$C_INV['id']} id, {$C_INV['name']} name, {$C_INV['unit']} unit, {$C_INV['qty']} qty
  FROM {$TB_INV}
  ORDER BY {$C_INV['name']} ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ========= ตั้งค่า meta และเริ่ม output ========= */
$page = 'loan';
$page_title = 'ระบบยืมของ';
$extra_head_html = '';
$extra_foot_html = '';

require __DIR__ . '/inc/front_shell_open.php';
?>
<div class="container">
  <div class="card hero shimmer">
    <div class="hero-left">
      <h1 class="hero-title">🧾 ระบบยืมของ</h1>
      <p class="hero-sub">บันทึกการยืมเพื่อติดตามของที่ถูกยืม และอัปเดตสต็อคอัตโนมัติ</p>
    </div>
  </div>

  <?php if($alert): ?>
    <div class="card" style="border-color:<?= ($alert['type']??'')==='ok'?'#1e5f49':'#5f1e27' ?>; color:#dbe3f3">
      <?= h($alert['text'] ?? '') ?>
    </div>
  <?php endif; ?>

  <div class="row" style="margin-top:16px">
    <form class="card" method="post" action="user_loan.php" id="loan-form">
      <h2 style="margin-top:0">ทำรายการยืม</h2>

      <div>
        <label>สินค้า</label>
        <select name="item_id" required>
          <option value="">-- เลือกสินค้า --</option>
          <?php foreach($items as $it): ?>
            <option value="<?= (int)$it['id'] ?>">
              <?= h($it['name']) ?> (คงเหลือ <?= rtrim(rtrim(number_format($it['qty'],2,'.',''),'0'),'.') ?> <?= h($it['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <div><label>จำนวน</label><input type="number" step="0.01" name="qty" required></div>
        <div><label>ผู้ยืม</label><input name="borrower" required></div>
      </div>

      <div class="row">
        <div><label>ติดต่อ</label><input name="contact"></div>
        <div><label>กำหนดคืน</label><input type="date" name="due_date"></div>
      </div>

      <input type="hidden" name="csrf"  value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="nonce" value="<?= h($form_nonce) ?>">

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button class="btn" type="reset">ล้างข้อมูล</button>
        <button class="btn btn-primary" type="submit" id="submit-btn">ยืนยันยืม ➜</button>
      </div>
    </form>

    <div class="card">
      <h2 style="margin-top:0">อ้างอิง</h2>
      <p class="muted">ดู “ยืมล่าสุด” และทำเครื่องหมายคืน ได้ที่ <a class="btn" href="admin/loans.php" target="_blank">admin/loans.php</a></p>
      <p class="muted">ดูความเคลื่อนไหวสต็อค ได้ที่ <a class="btn" href="admin/inventory_movements.php" target="_blank">admin/inventory_movements.php</a></p>
    </div>
  </div>
</div>

<script>
// กันดับเบิลคลิก
document.getElementById('loan-form')?.addEventListener('submit', function(){
  const btn = document.getElementById('submit-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'กำลังบันทึก...'; }
});
</script>

<?php require __DIR__ . '/inc/front_shell_close.php'; ?>
