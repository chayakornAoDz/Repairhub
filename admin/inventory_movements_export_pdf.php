<?php
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

/* ===== รับพารามิเตอร์ฟิลเตอร์ ===== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$item = (int)($_GET['item'] ?? 0);
$type = $_GET['type'] ?? '';
$who  = (int)($_GET['who']  ?? 0);

/* ===== WHERE: รองรับวันที่ที่เก็บแบบ ISO8601 มีตัว T ===== */
$where  = "REPLACE(sm.created_at,'T',' ') BETWEEN ? AND ?";
$params = [$from.' 00:00:00', $to.' 23:59:59'];
if ($item)      { $where .= ' AND sm.item_id = ?';   $params[] = $item; }
if ($type !== ''){ $where .= ' AND sm.type = ?';      $params[] = $type; }
if ($who)       { $where .= ' AND sm.created_by = ?'; $params[] = $who; }

/* ===== ดึงข้อมูล ===== */
$sql = "
SELECT sm.*, ii.name AS item_name, ii.unit,
       a.display_name, a.username
FROM stock_movements sm
LEFT JOIN inventory_items ii ON ii.id = sm.item_id
LEFT JOIN admins a          ON a.id = sm.created_by
WHERE $where
ORDER BY sm.created_at DESC, sm.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== helper ===== */
function signQty($type,$qty){
  $n = rtrim(rtrim(number_format($qty,2,'.',''), '0'), '.');
  if ($type==='in' || $type==='return') return '+'.$n;
  if ($type==='out' || $type==='issue')  return '-'.$n;
  return $n;
}
function typeLabel($t){
  return ['in'=>'รับเข้า','out'=>'ตัดออก','issue'=>'เบิกใช้','return'=>'คืนของ','adjust'=>'ปรับยอดเป็น'][$t] ?? $t;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายงานความเคลื่อนไหวทรัพย์สิน (<?= h($from) ?> ถึง <?= h($to) ?>)</title>
<style>
  /* พื้นฐาน */
  body{
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Inter, Arial, sans-serif;
    margin: 24px;
    color: #111;
  }
  h1{ margin: 0 0 6px; }
  .muted{ color:#666; font-size:12px; }

  /* ตาราง */
  table{ width:100%; border-collapse:collapse; font-size:12px; table-layout:fixed; }
  th,td{ border:1px solid #cfcfcf; padding:6px 8px; text-align:left; vertical-align:top; }
  th{ background:#f3f4f6; }
  .right{ text-align:right; white-space:nowrap; }

  /* จัดสัดส่วนคอลัมน์สำหรับแนวนอน (A4 landscape) */
  col.col-time   { width: 14%; }  /* เวลา */
  col.col-item   { width: 28%; }  /* สินค้า */
  col.col-type   { width: 10%; }  /* ประเภท */
  col.col-qty    { width: 12%; }  /* จำนวน */
  col.col-bal    { width: 12%; }  /* คงเหลือหลังทำ */
  col.col-by     { width: 12%; }  /* โดย */
  col.col-ref    { width: 12%; }  /* อ้างอิง */

  /* โหมดพิมพ์แนวนอน */
  @media print{
    @page {
      size: A4 landscape;         /* ← สำคัญ: แนวนอน */
      margin: 12mm 10mm;          /* margin สำหรับ A4 แนวนอน */
    }
    body{ margin: 0; }            /* เมื่อพิมพ์ ใช้ margin ของ @page */
  }
</style>
</head>
<body>
  <h1>รายงานความเคลื่อนไหวทรัพย์สิน</h1>
  <div class="muted">ช่วง <?= h($from) ?> – <?= h($to) ?></div>
  <br>

  <table>
    <colgroup>
      <col class="col-time">
      <col class="col-item">
      <col class="col-type">
      <col class="col-qty">
      <col class="col-bal">
      <col class="col-by">
      <col class="col-ref">
    </colgroup>
    <thead>
      <tr>
        <th>เวลา</th>
        <th>ทรัพย์สิน</th>
        <th>ประเภท</th>
        <th class="right">จำนวน</th>
        <th class="right">คงเหลือหลังทำ</th>
        <th>โดย</th>
        <th>อ้างอิง/หมายเหตุ</th>
      </tr>
    </thead>
    <tbody>
      <?php if($rows): foreach($rows as $r): ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime(str_replace('T',' ', $r['created_at'])))) ?></td>
          <td><?= h($r['item_name'] ?: '(ไม่พบสินค้า)') ?></td>
          <td><?= h(typeLabel($r['type'])) ?></td>
          <td class="right"><?= h(signQty($r['type'], $r['qty'])) . ' ' . h($r['unit'] ?: '') ?></td>
          <td class="right"><?= h(rtrim(rtrim(number_format((float)($r['balance_after'] ?? 0),2,'.',''), '0'), '.')) . ' ' . h($r['unit'] ?: '') ?></td>
          <td><?= h($r['display_name'] ?: $r['username'] ?: 'ระบบ') ?></td>
          <td><?= h($r['reference'] ?: '-') ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7" class="muted">ไม่พบข้อมูล</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    // เปิดหน้าแล้วพร้อมสำหรับสั่งพิมพ์เป็น PDF ทันที (ถ้าไม่ต้องการให้แจ้งเตือน สามารถลบสคริปต์นี้)
    window.addEventListener('load', () => { window.print(); });
  </script>
</body>
</html>
