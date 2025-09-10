<?php
// issue.php — ระบบเบิกของ (สไตล์เดียวกับหน้าระบบยืมของ: การ์ดกึ่งกลาง 680px, คอลัมน์เดียว)

require_once __DIR__ . '/inc/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

require_once __DIR__ . '/inc/inventory_map.php';
$MAP     = rh_get_inventory_map(db());
$TB_INV  = $MAP['TB_INV'];   $C_INV  = $MAP['C_INV'];
$TB_MOVE = $MAP['TB_MOVE'];  $C_MOVE = $MAP['C_MOVE'];

/* ================== POST -> PRG ================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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
    $s = $pdo->prepare("SELECT {$C_INV['name']} name, {$C_INV['unit']} unit, {$C_INV['qty']} qty
                        FROM {$TB_INV} WHERE {$C_INV['id']}=?");
    $s->execute([$item_id]);
    $it = $s->fetch(PDO::FETCH_ASSOC);
    if (!$it) throw new Exception('ไม่พบสินค้า');
    if ((float)$it['qty'] < $qty_req) throw new Exception('สต็อคไม่พอ');

    $newBalance = (float)$it['qty'] - $qty_req;
    $pdo->prepare("UPDATE {$TB_INV} SET {$C_INV['qty']}=?, updated_at=? WHERE {$C_INV['id']}=?")
        ->execute([$newBalance, date('c'), $item_id]);

    $ref  = 'ISS-'.date('Ymd-His');
    $cols = "{$C_MOVE['item_id']},{$C_MOVE['change_qty']},{$C_MOVE['type']},{$C_MOVE['ref']},{$C_MOVE['by']},{$C_MOVE['at']},{$C_MOVE['balance']}";
    $pdo->prepare("INSERT INTO {$TB_MOVE} ($cols) VALUES (?,?,?,?,?,?,?)")
        ->execute([$item_id, $qty_req, 'issue', $requester . ($note ? ' • '.$note : ''), null, date('c'), $newBalance]);

    // แจ้ง LINE (link filter วันนี้ของ item นั้น)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $appUrl = $scheme.'://'.$host.$base;

    $today = date('Y-m-d');
    $movementsLink = $appUrl . '/admin/inventory_movements.php?' . http_build_query([
      'from'=>$today,'to'=>$today,'type'=>'issue','item'=>$item_id
    ]);

    $msg  = "📦 เบิกของ"
          . "\nผู้ขอ: {$requester}"
          . "\nรายการ: {$it['name']} x{$qty_req} {$it['unit']}"
          . "\nคงเหลือ: " . rtrim(rtrim(number_format($newBalance,2,'.',''),'0'),'.') . " {$it['unit']}"
          . "\nเวลา: " . date('d/m/Y H:i')
          . "\nอ้างอิง: {$ref}"
          . "\nดูความเคลื่อนไหววันนี้: {$movementsLink}";
    $line_ok = line_push_text($msg);
    if (!$line_ok) line_notify("⚠️ แจ้งเตือนสำรอง\n".$msg);

    $_SESSION['flash'] = ['type'=>'ok','text'=>'บันทึกเบิกของสำเร็จ! อ้างอิง: '.$ref.($line_ok?' • ส่ง LINE แล้ว':' • ส่ง LINE ไม่สำเร็จ')];
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['type'=>'error','text'=>'บันทึกไม่สำเร็จ: '.$e->getMessage()];
  }
  header('Location: issue.php'); exit;
}

/* ================== GET ================== */
$page = 'issue';
$page_title = 'ระบบเบิกของ';
require __DIR__ . '/inc/front_shell_open.php';

$alert = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pdo = db();
$items = $pdo->query("
  SELECT {$C_INV['id']} id, {$C_INV['name']} name, {$C_INV['sku']} sku, {$C_INV['unit']} unit, {$C_INV['qty']} qty
  FROM {$TB_INV}
  ORDER BY {$C_INV['name']} ASC
")->fetchAll(PDO::FETCH_ASSOC);

$items_json = json_encode($items, JSON_UNESCAPED_UNICODE);
?>
<style>
/* ===== แบบเดียวกับหน้า “ยืมของ” — การ์ดกึ่งกลาง 680px คอลัมน์เดียว ===== */
.issue-wrap{ width:100%; max-width:680px; margin:0 auto; padding:16px; }
.issue-card{ width:100%; max-width:680px; margin-inline:auto; }

/* ระยะห่างหัวข้อ/การ์ด */
.section-gap{ margin-top:16px; }

/* ฟอร์มภายใน */
#issue-form .form-row{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
#issue-form .form-row > div{ min-width:0; }
@media (max-width: 640px){
  #issue-form .form-row{ grid-template-columns:1fr; }
  #issue-form .btn{ width:100%; }
}

/* Autocomplete */
.ac-wrap{ position:relative; }
.ac-list{
  position:absolute; left:0; right:0; top:calc(100% + 6px); z-index:50;
  background:#0b132a; border:1px solid #283446; border-radius:10px;
  box-shadow:0 16px 40px rgba(0,0,0,.45);
  max-height:260px; overflow:auto; display:none; overscroll-behavior:contain;
}
@media (max-width:640px){ .ac-list{ max-height:40vh; } }
.ac-item{ display:flex; justify-content:space-between; gap:10px; padding:10px 12px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,.06); }
.ac-item:last-child{ border-bottom:0; }
.ac-item:hover, .ac-item.active{ background:#111b2c; }

/* กันตารางล้น */
.stack-table{ overflow:auto; max-height:420px; }

/* มือถือ: แสดงเฉพาะ 3 คอลัมน์แรก (ชื่อ, คงเหลือ, หน่วย) */
@media (max-width: 640px){
  .inv-table thead th:not(:nth-child(-n+3)),
  .inv-table tbody td:not(:nth-child(-n+3)){
    display: none;
  }
}

</style>

<div class="issue-wrap">
  <!-- Hero -->
  <div class="card hero shimmer issue-card">
    <div class="hero-left">
      <h1 class="hero-title">📦 ระบบเบิกของ</h1>
      <p class="hero-sub">เลือกสินค้าและจำนวนที่ต้องการ ระบบจะตัดสต็อค บันทึกประวัติ และส่งแจ้งเตือนเข้า LINE อัตโนมัติ</p>
    </div>
  </div>

  <?php if ($alert): ?>
    <?php $ok = (($alert['type'] ?? '') === 'ok'); ?>
    <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?> section-gap issue-card">
      <?= h($alert['text'] ?? '') ?>
    </div>
  <?php endif; ?>

  <!-- การ์ด: ฟอร์มเบิก -->
  <form class="card issue-card section-gap" method="post" action="issue.php" id="issue-form" autocomplete="off">
    <h2 style="margin-top:0">ทำรายการเบิก</h2>

    <div>
      <label>สินค้า</label>
      <div class="ac-wrap">
        <input type="text" id="itemSearch" placeholder="พิมพ์ชื่อหรือรหัส (SKU)">
        <input type="hidden" name="item_id" id="itemId">
        <div id="acList" class="ac-list"></div>
      </div>
    </div>

    <div class="form-row">
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

  <!-- การ์ด: ตารางสินค้าทั้งหมด -->
  <div class="card issue-card section-gap">
    <h2 style="margin-top:0">สินค้าทั้งหมด</h2>
    <div class="stack-table">
      <table class="table inv-table">
        <thead><tr><th>ชื่อ</th><th>คงเหลือ</th><th>หน่วย</th><th>รหัส</th></tr></thead>
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

<script>
// กันส่งซ้ำ + บังคับเลือกสินค้าจากรายการ
document.getElementById('issue-form')?.addEventListener('submit', function(e){
  if(!document.getElementById('itemId').value){
    e.preventDefault();
    alert('กรุณาเลือกสินค้าจากรายการ');
    document.getElementById('itemSearch').focus();
    return false;
  }
  const btn = document.getElementById('submit-btn');
  if (btn){ btn.disabled = true; btn.textContent = 'กำลังบันทึก...'; }
});

// Autocomplete (ชื่อหรือ SKU)
const ITEMS = <?= $items_json ?>;
const input = document.getElementById('itemSearch');
const hidden = document.getElementById('itemId');
const list = document.getElementById('acList');

function renderList(q){
  const s = (q||'').toLowerCase().trim();
  const found = ITEMS.filter(it =>
    it.name.toLowerCase().includes(s) || (it.sku||'').toLowerCase().includes(s)
  );
  list.innerHTML = '';
  if(!found.length){ list.style.display='none'; return; }
  found.slice(0,8).forEach(it=>{
    const div = document.createElement('div');
    div.className = 'ac-item';
    div.textContent = `${it.name} (คงเหลือ ${Number(it.qty).toFixed(2).replace(/\\.00$/,'')} ${it.unit}${it.sku? ' / '+it.sku:''})`;
    div.onclick = ()=>{
      input.value = it.name + (it.sku ? ' ('+it.sku+')' : '');
      hidden.value = it.id;
      list.style.display = 'none';
    };
    list.appendChild(div);
  });
  list.style.display='block';
}
input.addEventListener('input', ()=>{ hidden.value=''; renderList(input.value); });
input.addEventListener('focus', ()=> renderList(input.value));
document.addEventListener('click', e=>{
  if(!list.contains(e.target) && e.target!==input) list.style.display='none';
});
</script>

<?php require __DIR__ . '/inc/front_shell_close.php'; ?>
