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

/* ========= PRG + one-time nonce ========= */
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

    $pdo->prepare("UPDATE {$TB_INV} SET {$C_INV['qty']}=?, updated_at=? WHERE {$C_INV['id']}=?")
        ->execute([$newBalance, date('c'), $item_id]);

    if ($TB_LOAN && $C_LOAN) {
      $pdo->prepare("
        INSERT INTO {$TB_LOAN}
          ({$C_LOAN['borrower']},{$C_LOAN['contact']},{$C_LOAN['item_id']},{$C_LOAN['qty']},loan_date,{$C_LOAN['due']},{$C_LOAN['status']},note,created_by,{$C_LOAN['at']})
        VALUES (?,?,?,?,?,?,?,?,?,?)
      ")->execute([
        $borrower, $contact, $item_id, $qty, date('Y-m-d'), ($due_date?:null), 'ยืมอยู่', '', null, date('c')
      ]);
    }

    $ref  = 'LOAN-'.date('Ymd-His');
    $cols = "{$C_MOVE['item_id']},{$C_MOVE['change_qty']},{$C_MOVE['type']},{$C_MOVE['ref']},{$C_MOVE['by']},{$C_MOVE['at']},{$C_MOVE['balance']}";
    $pdo->prepare("INSERT INTO {$TB_MOVE} ($cols) VALUES (?,?,?,?,?,?,?)")
        ->execute([$item_id, $qty, 'out', $borrower.($contact?' • '.$contact:''), null, date('c'), $newBalance]);

    $pdo->commit();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appUrl = $scheme.'://'.$host.$base;
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

    line_push_text($msg);

    $_SESSION['flash'] = ['type'=>'ok','text'=>'บันทึกการยืมสำเร็จ! อ้างอิง: '.$ref];
  }catch(Throwable $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['type'=>'error','text'=>'ไม่สามารถบันทึกได้: '.$e->getMessage()];
  }

  header('Location: user_loan.php'); exit;
}

/* ========= GET ========= */
$alert = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$form_nonce = bin2hex(random_bytes(16));
$_SESSION['loan_nonce'] = $form_nonce;

$pdo = db();
$items = $pdo->query("
  SELECT {$C_INV['id']} id, {$C_INV['name']} name, {$C_INV['sku']} sku, {$C_INV['unit']} unit, {$C_INV['qty']} qty
  FROM {$TB_INV}
  ORDER BY {$C_INV['name']} ASC")->fetchAll(PDO::FETCH_ASSOC);

$items_json = json_encode($items, JSON_UNESCAPED_UNICODE);

$page = 'loan';
$page_title = 'ระบบยืมของ';
require __DIR__ . '/inc/front_shell_open.php';
?>
<style>
.ac-wrap{ position:relative; }
.ac-list{
  position:absolute; left:0; right:0; top:100%; z-index:50;
  background:#0b132a; border:1px solid #283446; border-radius:10px;
  margin-top:6px; box-shadow:0 16px 40px rgba(0,0,0,.45);
  max-height:260px; overflow:auto; display:none;
}
.ac-item{ padding:8px 12px; cursor:pointer; }
.ac-item:hover, .ac-item.active{ background:#111b2c; }
</style>

<div class="container">
  <div class="card hero shimmer"><div class="hero-left">
    <h1 class="hero-title">🧾 ระบบยืมของ</h1>
    <p class="hero-sub">บันทึกการยืมเพื่อติดตามของที่ถูกยืม และอัปเดตสต็อคอัตโนมัติ</p>
  </div></div>

  <?php if ($alert): ?>
  <?php $ok = (($alert['type'] ?? '') === 'ok'); ?>
  <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>" style="margin-top:12px">
    <?= h($alert['text'] ?? '') ?>
  </div>
  <?php endif; ?>

  <form class="card" method="post" action="user_loan.php" id="loan-form" style="margin-top:16px">
    <h2 style="margin-top:0">ทำรายการยืม</h2>

    <div>
      <label>สินค้า</label>
      <div class="ac-wrap">
        <input type="text" id="itemSearch" placeholder="พิมพ์ชื่อหรือรหัส" autocomplete="off">
        <input type="hidden" name="item_id" id="itemId">
        <div id="acList" class="ac-list"></div>
      </div>
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

    <div style="margin-top:12px;text-align:right">
      <button class="btn btn-primary" id="submit-btn">ยืนยันยืม ➜</button>
    </div>
  </form>
</div>

<script>
const ITEMS = <?= $items_json ?>;
const input = document.getElementById('itemSearch');
const hidden = document.getElementById('itemId');
const list = document.getElementById('acList');

function renderList(q){
  const s = q.toLowerCase();
  const found = ITEMS.filter(it=> it.name.toLowerCase().includes(s) || (it.sku||'').toLowerCase().includes(s));
  list.innerHTML = '';
  if(!found.length){ list.style.display='none'; return; }
  found.slice(0,8).forEach(it=>{
    const div=document.createElement('div');
    div.className='ac-item';
    div.textContent=`${it.name} (คงเหลือ ${it.qty} ${it.unit}${it.sku? ' / '+it.sku:''})`;
    div.onclick=()=>{ input.value=it.name+(it.sku?' ('+it.sku+')':''); hidden.value=it.id; list.style.display='none'; };
    list.appendChild(div);
  });
  list.style.display='block';
}
input.addEventListener('input', e=>{ hidden.value=''; renderList(input.value); });
input.addEventListener('focus', ()=>renderList(input.value));
document.addEventListener('click', e=>{ if(!list.contains(e.target) && e.target!==input) list.style.display='none'; });

document.getElementById('loan-form').addEventListener('submit', e=>{
  if(!hidden.value){ e.preventDefault(); alert('กรุณาเลือกสินค้าจากรายการ'); input.focus(); }
});
</script>

<?php require __DIR__ . '/inc/front_shell_close.php'; ?>
