<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$item = (int)($_GET['item'] ?? 0);
$type = $_GET['type'] ?? '';
$who  = (int)($_GET['who']  ?? 0);

$perPage = 5;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* dropdowns */
$items = $pdo->query('SELECT id, name FROM inventory_items ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query('SELECT id, COALESCE(display_name, username) AS name FROM admins ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

/* WHERE */
$where  = "REPLACE(sm.created_at,'T',' ') BETWEEN ? AND ?";
$params = [$from.' 00:00:00', $to.' 23:59:59'];
if ($item)       { $where .= ' AND sm.item_id = ?';    $params[] = $item; }
if ($type !== ''){ $where .= ' AND sm.type = ?';       $params[] = $type; }
if ($who)        { $where .= ' AND sm.created_by = ?'; $params[] = $who; }

/* count */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

/* list */
$sql = "
SELECT sm.*, ii.name AS item_name, ii.unit,
       a.display_name, a.username
FROM stock_movements sm
LEFT JOIN inventory_items ii ON ii.id = sm.item_id
LEFT JOIN admins a          ON a.id = sm.created_by
WHERE $where
ORDER BY sm.created_at DESC, sm.id DESC
LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $v) $stmt->bindValue($i+1, $v);
$stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* helpers */
function signQty($type, $qty){
  $n = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
  if ($type==='in' || $type==='return') return '+'.$n;
  if ($type==='out' || $type==='issue')  return '-'.$n;
  return $n;
}
function typeLabel($t){ return ['in'=>'รับเข้า','out'=>'ตัดออก','issue'=>'เบิกใช้','return'=>'คืนของ','adjust'=>'ปรับยอดเป็น'][$t] ?? $t; }
function statusColor($t){ return ['in'=>'good','return'=>'good','out'=>'bad','issue'=>'bad','adjust'=>'warn'][$t] ?? ''; }
?>
<div class="card">
  <h1 style="margin:0 0 12px">รายงานความเคลื่อนไหวทรัพย์สิน</h1>

  <form class="report-toolbar" method="get">
    <div><label>จาก</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div><label>ถึง</label><input type="date" name="to"   value="<?= h($to) ?>"></div>

    <div>
      <label>สินค้า</label>
      <select name="item">
        <option value="0">ทั้งหมด</option>
        <?php foreach($items as $i): $iid=(int)$i['id']; ?>
          <option value="<?= $iid ?>" <?= ($item === $iid ? 'selected' : '') ?>><?= h($i['name']) ?></option>
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
        <?php foreach($users as $u): $uid=(int)$u['id']; ?>
          <option value="<?= $uid ?>" <?= ($who === $uid ? 'selected' : '') ?>><?= h($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="align-self:end"><button class="btn btn-primary" style="width:100%">ดูรายงาน</button></div>
  </form>

  <div class="report-header">
    <h3 style="margin:8px 0 0">ช่วง <?= h($from) ?> – <?= h($to) ?> (ทั้งหมด <?= (int)$total ?> รายการ)</h3>
    <div class="report-actions">
      <a class="btn" target="_blank" href="inventory_movements_export_pdf.php?<?= http_build_query($_GET) ?>">ดาวน์โหลด PDF</a>
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
            <td><?= h(date('d/m/Y H:i', strtotime(str_replace('T',' ', $r['created_at'])))) ?></td>
            <td><?= h($r['item_name'] ?: '(ไม่พบสินค้า)') ?></td>
            <td><span class="badge <?= statusColor($r['type']) ?>"><?= h(typeLabel($r['type'])) ?></span></td>
            <td><?= h(signQty($r['type'], $r['qty'])) . ' ' . h($r['unit'] ?: '') ?></td>
            <td><b><?= h(rtrim(rtrim(number_format((float)($r['balance_after'] ?? 0), 2, '.', ''), '0'), '.')) . ' ' . h($r['unit'] ?: '') ?></b></td>
            <td><?= h($r['display_name'] ?: $r['username'] ?: 'ระบบ') ?></td>
            <td><?= h($r['reference'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="muted">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <?php
      // ใช้ rh_page_url เพื่อให้ลิงก์ชี้มาที่ไฟล์ปัจจุบันเสมอ
      $prevUrl = rh_page_url(max(1,$page-1));
      $nextUrl = rh_page_url(min($pages,$page+1));
    ?>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:12px">
      <a class="btn <?= $page<=1?'disabled':'' ?>" href="<?= h($prevUrl) ?>">← ก่อนหน้า</a>
      <span class="badge">หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
      <a class="btn <?= $page>=$pages?'disabled':'' ?>" href="<?= h($nextUrl) ?>">ถัดไป →</a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
