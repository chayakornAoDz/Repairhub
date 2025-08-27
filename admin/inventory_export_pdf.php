<?php
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$cat  = $_GET['cat']  ?? '';
$type = $_GET['type'] ?? '';

$where = "WHERE date(m.created_at) BETWEEN ? AND ?";
$params = [$from,$to];
if ($cat!==''){ $where.=" AND COALESCE(i.category,'อื่น ๆ')=?"; $params[]=$cat; }
if ($type!==''){ $where.=" AND m.type=?";                    $params[]=$type; }

$sql = "
SELECT m.created_at, i.sku, i.name, COALESCE(i.category,'อื่น ๆ') AS category,
       m.type, m.qty, i.unit, m.reference
FROM stock_movements m
JOIN inventory_items i ON i.id=m.item_id
{$where}
ORDER BY m.created_at DESC, m.id DESC
";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<title>PDF รายงานสต็อก</title>
<style>
  body{ background:#fff; color:#000; }
  .wrap{ max-width:1000px; margin:0 auto; padding:16px; }
  h2{ margin:0 0 8px }
  table{ width:100%; border-collapse:collapse; font-size:13px }
  th,td{ border:1px solid #ccc; padding:6px 8px; text-align:left }
  th{ background:#f3f4f6 }
  .muted{ color:#6b7280 }
  @media print { .no-print{ display:none } }
</style>
</head><body>
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2>รายงานความเคลื่อนไหวสต็อก</h2>
    <button class="no-print" onclick="window.print()">พิมพ์/บันทึกเป็น PDF</button>
  </div>
  <div class="muted" style="margin:6px 0">
    ช่วงวันที่ <?= h($from) ?> ถึง <?= h($to) ?>
    <?php if($cat): ?> • หมวดหมู่: <?= h($cat) ?><?php endif; ?>
    <?php if($type): ?> • ประเภท: <?= h($type) ?><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>เวลา</th><th>SKU</th><th>ชื่อสินค้า</th><th>หมวดหมู่</th>
        <th>ประเภท</th><th style="text-align:right">จำนวน</th><th>หน่วย</th><th>อ้างอิง</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= h($r['sku']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['category']) ?></td>
          <td><?= h($r['type']) ?></td>
          <td style="text-align:right">
            <?php $sign = in_array($r['type'],['out','issue']) ? -1 : 1; echo number_format($sign*(float)$r['qty'],2); ?>
          </td>
          <td><?= h($r['unit']) ?></td>
          <td><?= h($r['reference']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="8" class="muted">ไม่พบข้อมูล</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body></html>
