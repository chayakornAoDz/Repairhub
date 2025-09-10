<?php
// จงอย่าให้มี output ใด ๆ ก่อนบล็อกนี้ (รวมทั้งช่องว่างก่อน <?php)
// ทำงานส่วน POST ให้เสร็จก่อน แล้วจึงค่อย require header.php

require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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

  header('Location: inventory_add_edit.php' . $qs);
  exit;
}

// ------------ จากนี้ค่อยเริ่มเรนเดอร์หน้า ------------
require_once __DIR__ . '/header.php';

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
    <h1 class="inv-title">ทรัพย์สิน</h1>
    <?php if ($flash['msg']): ?><div class="inv-badge inv-good"><?= h($flash['msg']) ?></div><?php endif; ?>
    <?php if ($flash['err']): ?><div class="inv-badge inv-bad"><?= h($flash['err']) ?></div><?php endif; ?>
  </div>

  <div class="inv-row">
    <!-- การ์ดเพิ่มสินค้า -->
    <form class="inv-card" method="post" autocomplete="off">
      <h3 class="inv-card-title">เพิ่มทรัพย์สิน</h3>
      <input type="hidden" name="action" value="add_item">

      <label>ชื่อ</label>
      <input class="inv-input" name="name" required>

      <div class="inv-grid-2">
        <div>
          <label>รหัสทรัพย์สิน (ถ้ามีและไม่ซ้ำกัน)</label>
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

      <label>ทรัพย์สิน</label>
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
    </form>
  </div>

  <!-- ปรับล่าสุด -->
  <div class="card" style="margin-top:20px">
    <h3 style="margin:0 0 10px">ปรับล่าสุด</h3>
    <div id="inv-recent-box" style="min-height:180px">
      <div class="inv-recent-sub">กำลังโหลด...</div>
    </div>
    <div id="inv-recent-nav" style="margin-top:8px"></div>
  </div>
</div>

<script>
/* ===== ปรับล่าสุด: ปุ่มแบบ Users + อัปเดตอัตโนมัติเมื่ออยู่หน้า 1 ===== */
let recentTimer    = null;
let recentPage     = 1;
const perPage      = 5;
let totalRows      = 0;
let totalPages     = 1;
let recentLoading  = false;

function stopRecentPolling(){ if (recentTimer){ clearInterval(recentTimer); recentTimer = null; } }
function startRecentPolling(){
  stopRecentPolling();
  if (recentPage === 1){
    fetchRecentMoves();                       // หน้าแรก: อัปเดตอัตโนมัติ
    recentTimer = setInterval(fetchRecentMoves, 5000);
  }else{
    fetchRecentMoves();                       // หน้าอื่น: โหลดครั้งเดียว
  }
}

async function fetchRecentMoves(){
  if (recentLoading) return;
  recentLoading = true;

  try{
    const offset = (recentPage - 1) * perPage;
    const url    = `api/inventory_recent.php?meta=1&limit=${perPage}&offset=${offset}`;
    const res    = await fetch(url, { cache:'no-store' });
    const data   = await res.json();

    const rows = Array.isArray(data?.rows) ? data.rows : (Array.isArray(data) ? data : []);
    if (typeof data?.total === 'number'){
      totalRows  = data.total;
      totalPages = Math.max(1, Math.ceil(totalRows / perPage));
    }else{
      // เผื่อ fallback ถ้าไม่มี total (ไม่ควรเกิดถ้าใช้ meta=1)
      totalPages = (rows.length === perPage) ? recentPage + 1 : recentPage;
    }

    // ถ้าไปเกินหน้าสุดท้าย ให้ถอยกลับอัตโนมัติ
    if (recentPage > totalPages){
      recentPage = totalPages;
      return fetchRecentMoves();
    }

    const box = document.getElementById('inv-recent-box');
    if (!rows.length){
      box.innerHTML = '<div class="inv-recent-sub">ยังไม่มีข้อมูล</div>';
      renderRecentNav();
      return;
    }

    box.innerHTML = rows.map(m => {
      const sku  = m.sku ? `[${m.sku}] ` : '';
      const qty  = (m.qty ?? '') + ' ' + (m.unit ?? '');
      const note = m.reference || '-';
      const bal  = (m.balance_after ?? '') !== '' ? `<div class="inv-recent-sub">คงเหลือหลังปรับ: ${m.balance_after}</div>` : '';
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

    renderRecentNav();
  }catch(e){
    console.error('[recent]', e);
  }finally{
    recentLoading = false;
  }
}

function renderRecentNav(){
  const nav = document.getElementById('inv-recent-nav');
  const page = recentPage;

  // สร้างช่วงตัวเลขแบบ window = 2 (เช่น … 1 2 [3] 4 5 …)
  const windowSize = 2;
  const start = Math.max(1, page - windowSize);
  const end   = Math.min(totalPages, page + windowSize);

  let html = `
    <div class="pagination" style="flex-wrap:wrap">
      <a href="#" class="page-btn ${page<=1?'disabled':''}" data-goto="first">« หน้าแรก</a>
      <a href="#" class="page-btn ${page<=1?'disabled':''}" data-goto="prev">‹ ก่อนหน้า</a>
  `;

  for (let p = start; p <= end; p++){
    html += `<a href="#" class="page-num ${p===page?'active':''}" data-page="${p}">${p}</a>`;
  }

  html += `
      <a href="#" class="page-btn ${page>=totalPages?'disabled':''}" data-goto="next">ถัดไป ›</a>
      <a href="#" class="page-btn ${page>=totalPages?'disabled':''}" data-goto="last">หน้าสุดท้าย »</a>
    </div>
    <div class="small muted" style="margin-top:4px">
      ${page===1 ? 'อัปเดตอัตโนมัติทุก 5 วินาที' : 'กำลังดูหน้าก่อนหน้า (หยุดอัปเดตอัตโนมัติ)'}
      · หน้า ${page} / ${totalPages}
    </div>
  `;

  nav.innerHTML = html;

  // ผูกเหตุการณ์คลิกปุ่ม
  nav.querySelectorAll('a.page-btn, a.page-num').forEach(a => {
    a.addEventListener('click', (ev) => {
      ev.preventDefault();
      if (a.classList.contains('disabled')) return;

      const goto = a.dataset.goto;
      if (goto === 'first'){ recentPage = 1; }
      else if (goto === 'prev'){ recentPage = Math.max(1, recentPage - 1); }
      else if (goto === 'next'){ recentPage = Math.min(totalPages, recentPage + 1); }
      else if (goto === 'last'){ recentPage = totalPages; }
      else if (a.dataset.page){ recentPage = parseInt(a.dataset.page, 10) || 1; }

      // หน้า 1 ให้กลับไปโหมดอัปเดตอัตโนมัติ
      if (recentPage === 1) startRecentPolling();
      else { stopRecentPolling(); fetchRecentMoves(); }
    });
  });
}

// สลับโฟกัส -> หยุด/เริ่ม polling อัตโนมัติ
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopRecentPolling(); else startRecentPolling();
});

// เริ่มทำงาน
document.addEventListener('DOMContentLoaded', startRecentPolling);
</script>
