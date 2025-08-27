<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo = db();

/* ---------- POST -> Redirect -> GET ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // คง query string เดิม
  $qs = [];
  if (isset($_GET['page'])) $qs['page'] = (int)$_GET['page'];
  if (isset($_GET['cat'])  && $_GET['cat']!=='') $qs['cat'] = $_GET['cat'];
  $qs = $qs ? ('?' . http_build_query($qs)) : '';

  try {
    $act = $_POST['action'] ?? '';

    if ($act === 'add_item') {
      $stmt = $pdo->prepare('
        INSERT INTO inventory_items (sku,name,category,unit,stock_qty,min_qty,location,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?)
      ');
      $stmt->execute([
        trim($_POST['sku'] ?? '') ?: null,
        trim($_POST['name'] ?? ''),
        trim($_POST['category'] ?? '') ?: 'อื่น ๆ',
        trim($_POST['unit'] ?? '') ?: 'ชิ้น',
        (float)($_POST['stock_qty'] ?? 0),
        (float)($_POST['min_qty'] ?? 0),
        trim($_POST['location'] ?? ''),
        date('c'), date('c'),
      ]);
      $_SESSION['flash'] = ['msg' => 'เพิ่มสินค้าแล้ว', 'err' => ''];
    }

    elseif ($act === 'move') {
      $item_id  = (int)($_POST['item_id'] ?? 0);
      $itemTerm = trim($_POST['item_term'] ?? '');
      if ($item_id <= 0 && $itemTerm !== '') {
        $q = $pdo->prepare("
          SELECT id FROM inventory_items
          WHERE sku LIKE :t OR name LIKE :t
          ORDER BY
            (CASE WHEN sku = :eq THEN 2 WHEN name = :eq THEN 1 ELSE 0 END) DESC,
            name
          LIMIT 1
        ");
        $like = '%'.$itemTerm.'%';
        $q->execute([':t'=>$like, ':eq'=>$itemTerm]);
        $item_id = (int)$q->fetchColumn();
      }
      if ($item_id <= 0) throw new Exception('กรุณาเลือก/พิมพ์ชื่อสินค้าที่มีในระบบ');

      $qty  = (float)($_POST['qty'] ?? 0);
      $type = $_POST['type'] ?? 'in';
      $ref  = trim($_POST['reference'] ?? '');

      $pdo->beginTransaction();

      $it = $pdo->prepare('SELECT * FROM inventory_items WHERE id=?');
      $it->execute([$item_id]);
      $item = $it->fetch(PDO::FETCH_ASSOC);
      if (!$item) throw new Exception('ไม่พบสินค้า');

      $new = (float)$item['stock_qty'];
      if ($type === 'in' || $type === 'return') $new += $qty;
      if ($type === 'out' || $type === 'issue')  $new -= $qty;
      if ($type === 'adjust')                    $new  = $qty;

      // (แนะนำ) กันติดลบโดยไม่ตั้งใจ
      if ($new < 0) throw new Exception('ยอดคงเหลือติดลบ ตรวจสอบจำนวนอีกครั้ง');

      $upd = $pdo->prepare('UPDATE inventory_items SET stock_qty=?, updated_at=? WHERE id=?');
      $upd->execute([$new, date('c'), $item_id]);

      $mov = $pdo->prepare('
        INSERT INTO stock_movements (item_id,qty,type,reference,created_by,created_at,balance_after)
        VALUES (?,?,?,?,?,?,?)
      ');
      $mov->execute([
        $item_id,
        $qty,
        $type,
        $ref,
        $_SESSION['admin_id'] ?? null,
        date('c'),
        $new
      ]);

      $pdo->commit();
      $_SESSION['flash'] = ['msg' => 'บันทึกการเคลื่อนไหวแล้ว', 'err' => ''];
    }

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['msg'=>'', 'err'=>'ผิดพลาด: '.$e->getMessage()];
  }

  // >>> เปลี่ยนปลายทางให้ตรงหน้านี้ <<<
  header('Location: inventory_add_edit.php' . $qs);
  exit;
}

/* ---------- ดึงรายการทั้งหมดสำหรับ datalist ---------- */
$itemsAll = $pdo->query('SELECT id, sku, name, category, unit, stock_qty FROM inventory_items ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ---------- flash ---------- */
$flash = $_SESSION['flash'] ?? ['msg'=>'','err'=>''];
$_SESSION['flash'] = ['msg'=>'','err'=>''];
?>
<link rel="stylesheet" href="../assets/css/style.css">

<div class="inv-page container">
  <div class="inv-head">
    <h1 class="inv-title">สต็อกสินค้า</h1>
    <?php if ($flash['msg']): ?><div class="inv-badge inv-good"><?= h($flash['msg']) ?></div><?php endif; ?>
    <?php if ($flash['err']): ?><div class="inv-badge inv-bad"><?= h($flash['err']) ?></div><?php endif; ?>
  </div>

  <div class="inv-row">
    <!-- การ์ดเพิ่มสินค้า -->
    <form class="inv-card" method="post" autocomplete="off">
      <h3 class="inv-card-title">เพิ่มสินค้า</h3>
      <input type="hidden" name="action" value="add_item">

      <label>ชื่อ</label>
      <input class="inv-input" name="name" required>

      <div class="inv-grid-2">
        <div>
          <label>รหัสทรัพย์สิน (ถ้ามี)</label>
          <input class="inv-input" name="sku">
        </div>
        <div>
          <label>หมวดหมู่</label>
          <select class="inv-input" name="category">
            <?php foreach (['หมึกพิมพ์','อุปกรณ์คอมพ์','CCTV','License Software','อะไหล่','เครื่องเขียน','โทรศัพท์ภายใน','อุปกรณ์ Network','อื่น ๆ'] as $c): ?>
              <option><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="inv-grid-2">
        <div><label>หน่วย</label><input class="inv-input" name="unit" placeholder="ชิ้น, กล่อง, ม้วน"></div>
        <div><label>จำนวนเริ่มต้น</label><input class="inv-input" type="number" step="0.01" name="stock_qty" value="0"></div>
      </div>

      <div class="inv-grid-2">
        <div><label>จุดสั่งซื้อขั้นต่ำ (Min)</label><input class="inv-input" type="number" step="0.01" name="min_qty" value="0"></div>
        <div><label>ที่เก็บ</label><input class="inv-input" name="location"></div>
      </div>

      <button class="inv-btn inv-primary" type="submit" onclick="this.disabled=true;this.form.submit();">บันทึก</button>
    </form>

    <!-- การ์ดรับเข้า/ตัดออก/ปรับยอด -->
    <form class="inv-card" method="post" autocomplete="off">
      <h3 class="inv-card-title">รับเข้า / ตัดออก / ปรับยอด</h3>
      <input type="hidden" name="action" value="move">
      <input type="hidden" name="item_id" id="inv-item-id">

      <label>สินค้า</label>
      <input class="inv-input" name="item_term" id="inv-item-term" list="inv-items" placeholder="พิมพ์ชื่อหรือรหัส…">
      <datalist id="inv-items">
        <?php foreach ($itemsAll as $i): ?>
          <option data-id="<?= (int)$i['id'] ?>" value="<?= h(($i['sku'] ? '['.$i['sku'].'] ' : '').$i['name']) ?>">
            <?= h(($i['category'] ?: 'อื่น ๆ').' • คงเหลือ '.$i['stock_qty'].' '.$i['unit']) ?>
          </option>
        <?php endforeach; ?>
      </datalist>

      <div class="inv-grid-2">
        <div>
          <label>ประเภท</label>
          <select class="inv-input" name="type" required>
            <option value="in">รับเข้า</option>
            <option value="out">ตัดออก</option>
            <option value="issue">เบิกใช้</option>
            <option value="return">คืนของ</option>
            <option value="adjust">ปรับยอดเป็น</option>
          </select>
        </div>
        <div>
          <label>จำนวน</label>
          <input class="inv-input" type="number" step="0.01" name="qty" required>
        </div>
      </div>

      <label>อ้างอิง/หมายเหตุ</label>
      <input class="inv-input" name="reference">

      <button class="inv-btn inv-primary" type="submit" onclick="this.disabled=true;this.form.submit();">บันทึก</button>

      <!-- (ตัดบล็อกปรับล่าสุดเดิมในการ์ดนี้ออกแล้ว) -->
    </form>
  </div>

  <!-- ปรับล่าสุด (ย้ายลงมาล่างสุด) -->
  <div class="card" style="margin-top:20px">
    <h3 style="margin:0 0 10px">ปรับล่าสุด</h3>
    <div id="inv-recent-box" style="min-height:180px">
      <div class="inv-recent-sub">กำลังโหลด...</div>
    </div>
  </div>
</div>

<script>
// map datalist -> hidden item_id
(function(){
  const term   = document.getElementById('inv-item-term');
  const hidden = document.getElementById('inv-item-id');
  const list   = document.getElementById('inv-items');
  if (!term || !hidden || !list) return;

  term.addEventListener('change', () => {
    hidden.value = '';
    const opt = Array.from(list.options).find(o => o.value === term.value);
    if (opt && opt.dataset.id) hidden.value = opt.dataset.id;
  });
})();
</script>

<script>
let recentTimer = null;

async function fetchRecentMoves(){
  try{
    const r = await fetch('api/inventory_recent.php?limit=5', { cache:'no-store' });
    const data = await r.json();
    const box  = document.getElementById('inv-recent-box');

    if (!Array.isArray(data) || data.length === 0) {
      box.innerHTML = '<div class="inv-recent-sub">ยังไม่มีข้อมูล</div>';
      return;
    }

    box.innerHTML = data.map(m => {
      const sku  = m.sku ? `[${m.sku}] ` : '';
      const qty  = (m.qty ?? '') + ' ' + (m.unit ?? '');
      const note = m.reference || '-';
      const bal  = (m.balance_after !== null && m.balance_after !== undefined)
                   ? `<div class="inv-recent-sub">คงเหลือหลังปรับ: ${m.balance_after}</div>` : '';

      return `
        <div style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.06)">
          <div style="display:flex;align-items:center;gap:8px;justify-content:space-between">
            <div style="min-width:0">
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <b>${sku}${m.name || '-'}</b>
              </div>
              <div class="inv-recent-sub">${m.created_at_th || ''} · ${note}</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex:0 0 auto">
              <span class="inv-chip inv-type-${m.type}">${m.type}</span>
              <span class="inv-qty">${qty}</span>
            </div>
          </div>
          ${bal}
        </div>
      `;
    }).join('');
  }catch(e){
    console.error('[recent]', e);
  }
}

function startRecentPolling(){
  if (recentTimer) clearInterval(recentTimer);
  fetchRecentMoves();                             // ดึงทันที 1 รอบ
  recentTimer = setInterval(fetchRecentMoves, 5000); // แล้วค่อยตั้ง interval
}

// หยุด/เริ่มเมื่อแท็บไม่โฟกัส เพื่อลดโหลดเซิร์ฟเวอร์
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    if (recentTimer) clearInterval(recentTimer);
  } else {
    startRecentPolling();
  }
});

document.addEventListener('DOMContentLoaded', startRecentPolling);
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
