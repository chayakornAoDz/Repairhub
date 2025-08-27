<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

/* --------------------- Utils --------------------- */
function qs_keep($ov = []) {
  $q = $_GET;
  $q = array_merge($q, $ov);
  return http_build_query($q);
}
function signQty($type, $qty){
  if ($type==='in' || $type==='return') return '+'.rtrim(rtrim(number_format($qty,2,'.',''), '0'), '.');
  if ($type==='out' || $type==='issue')  return '-'.rtrim(rtrim(number_format($qty,2,'.',''), '0'), '.');
  return rtrim(rtrim(number_format($qty,2,'.',''), '0'), '.' ); // adjust
}
function typeLabel($t){
  return ['in'=>'รับเข้า','out'=>'ตัดออก','issue'=>'เบิกใช้','return'=>'คืนของ','adjust'=>'ปรับยอดเป็น'][$t] ?? $t;
}
function typeColor($t){
  return ['in'=>'good','return'=>'good','out'=>'bad','issue'=>'bad','adjust'=>'warn'][$t] ?? '';
}

/* --------------------- Tabs ---------------------- */
$tab = $_GET['tab'] ?? 'summary'; // summary|mov

/* --------------------- Common dropdown data ------- */
$items = $pdo->query('SELECT id,name,unit FROM inventory_items ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query('SELECT id, COALESCE(display_name,username) AS name FROM admins ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

/* ==================================================
 *  TAB 1: SUMMARY (สรุปรายการสินค้า)
 * ==================================================*/
if ($tab === 'summary') {
  // filter
  $kw  = trim($_GET['kw'] ?? '');
  $cat = $_GET['cat'] ?? '';
  $low = isset($_GET['low']) ? (int)$_GET['low'] : 0; // เฉพาะต่ำกว่า min

  // ดึงหมวดหมู่ทั้งหมด
  $cats = $pdo->query("SELECT COALESCE(category,'อื่น ๆ') AS c FROM inventory_items GROUP BY c ORDER BY c")
              ->fetchAll(PDO::FETCH_COLUMN);

  $where  = '1=1';
  $params = [];
  if ($kw !== '') {
    $where .= " AND (name LIKE ? OR sku LIKE ?)";
    $params[] = "%$kw%"; $params[] = "%$kw%";
  }
  if ($cat !== '') {
    $where .= " AND COALESCE(category,'อื่น ๆ') = ?";
    $params[] = $cat;
  }
  if ($low === 1) {
    $where .= " AND stock_qty < min_qty";
  }

  $sql = "
    SELECT id, sku, name, category, stock_qty, min_qty, unit, location
    FROM inventory_items
    WHERE $where
    ORDER BY COALESCE(category,'อื่น ๆ'), name
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="card">
    <h1 style="margin:0 0 12px">รายงานสรุปสต็อก</h1>

    <form class="report-toolbar" method="get">
      <input type="hidden" name="tab" value="summary">
      <div>
        <label>ค้นหา</label>
        <input name="kw" value="<?= h($kw) ?>" placeholder="ชื่อ/รหัสสินค้า (SKU)">
      </div>
      <div>
        <label>หมวดหมู่</label>
        <select name="cat">
          <option value="">ทุกหมวดหมู่</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ตัวกรอง</label>
        <select name="low">
          <option value="0" <?= $low===0?'selected':'' ?>>ทั้งหมด</option>
          <option value="1" <?= $low===1?'selected':'' ?>>ต่ำกว่าจุดสั่งซื้อ (Min)</option>
        </select>
      </div>
      <div style="align-self:end"><button class="btn btn-primary" style="width:100%">กรอง</button></div>
    </form>

    <div class="report-header">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <h3 style="margin:8px 0 0">พบทั้งหมด <?= count($rows) ?> รายการ</h3>
        <a class="btn" href="inventory_export_pdf.php?<?= qs_keep() ?>">ดาวน์โหลด PDF</a>
        <!-- ถ้ามี XLSX แล้ว เปลี่ยนเป็น inventory_export_xlsx.php -->
      </div>
      <div class="report-actions">
        <a class="btn" href="?<?= qs_keep(['tab'=>'mov']) ?>">ดูรายงานความเคลื่อนไหว →</a>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table sticky zebra">
        <thead>
          <tr>
            <th>รหัสทรัพย์สิน</th>
            <th>ชื่อ</th>
            <th>คงเหลือ</th>
            <th>หน่วย</th>
            <th>Min</th>
            <th>ที่เก็บ</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $currentCat = null;
          foreach($rows as $r):
            $catRow = $r['category'] ?: 'อื่น ๆ';
            if ($catRow !== $currentCat):
              $currentCat = $catRow; ?>
              <tr><td colspan="6" style="color:#93c5fd;border-bottom:1px dashed #1f2937;font-weight:600"><?= h($currentCat) ?></td></tr>
        <?php endif; ?>
          <tr>
            <td><?= h($r['sku'] ?: '-') ?></td>
            <td><?= h($r['name']) ?></td>
            <td>
              <?php if ($r['stock_qty'] < $r['min_qty']): ?>
                <span class="badge bad"><?= h($r['stock_qty']) ?></span>
              <?php else: ?>
                <?= h($r['stock_qty']) ?>
              <?php endif; ?>
            </td>
            <td><?= h($r['unit']) ?></td>
            <td><?= h($r['min_qty']) ?></td>
            <td><?= h($r['location']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="6" class="muted">ไม่พบข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php
/* ==================================================
 *  TAB 2: MOVEMENTS (ความเคลื่อนไหว)
 * ==================================================*/
} else {
  // filter
  $from = $_GET['from'] ?? date('Y-m-01');
  $to   = $_GET['to']   ?? date('Y-m-d');
  $itemId = (int)($_GET['item'] ?? 0);
  $type = $_GET['type'] ?? '';
  $who  = (int)($_GET['who']  ?? 0);

  $perPage = 10;
  $page    = max(1, (int)($_GET['page'] ?? 1));
  $offset  = ($page-1)*$perPage;

  $where  = 'sm.created_at BETWEEN ? AND ?';
  $params = [$from.' 00:00:00', $to.' 23:59:59'];
  if ($itemId) { $where .= ' AND sm.item_id = ?';   $params[] = $itemId; }
  if ($type  !== '') { $where .= ' AND sm.type = ?';   $params[] = $type; }
  if ($who)       { $where .= ' AND sm.created_by = ?'; $params[] = $who; }

  $cnt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE $where");
  $cnt->execute($params);
  $total = (int)$cnt->fetchColumn();
  $pages = max(1, (int)ceil($total/$perPage));
  if ($page > $pages) $page = $pages; $offset = ($page-1)*$perPage;

  $sql = "
    SELECT sm.*, ii.name AS item_name, ii.unit,
           a.display_name, a.username
    FROM stock_movements sm
    JOIN inventory_items ii ON ii.id = sm.item_id
    LEFT JOIN admins a ON a.id = sm.created_by
    WHERE $where
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT ? OFFSET ?
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $i=>$v) $stmt->bindValue($i+1, $v);
  $stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
  $stmt->bindValue(count($params)+2, $offset,  PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="card">
    <h1 style="margin:0 0 12px">รายงานความเคลื่อนไหวสต็อก</h1>

    <form class="report-toolbar" method="get">
      <input type="hidden" name="tab" value="mov">
      <div><label>จาก</label><input type="date" name="from" value="<?= h($from) ?>"></div>
      <div><label>ถึง</label><input type="date" name="to" value="<?= h($to) ?>"></div>
      <div>
        <label>สินค้า</label>
        <select name="item">
          <option value="0">ทั้งหมด</option>
          <?php foreach($items as $i): ?>
            <option value="<?= (int)$i['id'] ?>" <?= $itemId===$i['id']?'selected':'' ?>><?= h($i['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ประเภท</label>
        <select name="type">
          <option value="">ทั้งหมด</option>
          <option value="in"     <?= $type==='in'?'selected':'' ?>>รับเข้า</option>
          <option value="out"    <?= $type==='out'?'selected':'' ?>>ตัดออก</option>
          <option value="issue"  <?= $type==='issue'?'selected':'' ?>>เบิกใช้</option>
          <option value="return" <?= $type==='return'?'selected':'' ?>>คืนของ</option>
          <option value="adjust" <?= $type==='adjust'?'selected':'' ?>>ปรับยอดเป็น</option>
        </select>
      </div>
      <div>
        <label>ผู้ทำรายการ</label>
        <select name="who">
          <option value="0">ทั้งหมด</option>
          <?php foreach($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $who===$u['id']?'selected':'' ?>><?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="align-self:end"><button class="btn btn-primary" style="width:100%">ดูรายงาน</button></div>
    </form>

    <div class="report-header">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <h3 style="margin:8px 0 0">ช่วง <?= h($from) ?> – <?= h($to) ?> (ทั้งหมด <?= (int)$total ?> รายการ)</h3>
        <a class="btn" href="inventory_movements_export_pdf.php?<?= qs_keep() ?>">ดาวน์โหลด CSV</a>
        <!-- ถ้ามี XLSX แล้ว เพิ่มลิงก์ inventory_movements_export_xlsx.php -->
      </div>
      <div class="report-actions">
        <a class="btn" href="?<?= qs_keep(['tab'=>'summary']) ?>">← กลับสรุปสต็อก</a>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table sticky zebra">
        <thead>
        <tr>
          <th>เวลา</th>
          <th>สินค้า</th>
          <th>ประเภท</th>
          <th>จำนวน</th>
          <th>คงเหลือหลังทำ</th>
          <th>โดย</th>
          <th>อ้างอิง/หมายเหตุ</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
            <td><?= h($r['item_name']) ?></td>
            <td><span class="badge <?= typeColor($r['type']) ?>"><?= h(typeLabel($r['type'])) ?></span></td>
            <td><?= h(signQty($r['type'],$r['qty'])) . ' ' . h($r['unit']) ?></td>
            <td><b><?= h(rtrim(rtrim(number_format((float)($r['balance_after'] ?? 0), 2, '.', ''), '0'), '.')) . ' ' . h($r['unit']) ?></b></td>
            <td><?= h($r['display_name'] ?: $r['username'] ?: 'ระบบ') ?></td>
            <td><?= h($r['reference'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?><tr><td colspan="7" class="muted">ไม่พบข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages>1): ?>
      <div style="display:flex;justify-content:center;gap:8px;margin-top:12px">
        <a class="btn <?= $page<=1?'disabled':'' ?>" href="?<?= qs_keep(['tab'=>'mov','page'=>max(1,$page-1)]) ?>">← ก่อนหน้า</a>
        <span class="badge">หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
        <a class="btn <?= $page>=$pages?'disabled':'' ?>" href="?<?= qs_keep(['tab'=>'mov','page'=>min($pages,$page+1)]) ?>">ถัดไป →</a>
      </div>
    <?php endif; ?>
  </div>
<?php } // end tab
require_once __DIR__ . '/footer.php';
