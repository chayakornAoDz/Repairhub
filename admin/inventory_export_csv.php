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
SELECT m.created_at AS time, i.sku, i.name, COALESCE(i.category,'อื่น ๆ') AS category,
       m.type, m.qty, i.unit, m.reference
FROM stock_movements m
JOIN inventory_items i ON i.id=m.item_id
{$where}
ORDER BY m.created_at DESC, m.id DESC
";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ส่ง header เป็น CSV */
$fname = "inventory_{$from}_to_{$to}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
$fp = fopen('php://output','w');

/* BOM สำหรับ Excel ภาษาไทย */
fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($fp, ['เวลา','SKU','ชื่อสินค้า','หมวดหมู่','ประเภท','จำนวน(+/-)','หน่วย','อ้างอิง']);
foreach($rows as $r){
  $sign = in_array($r['type'],['out','issue']) ? -1 : 1;
  fputcsv($fp, [
    date('Y-m-d H:i', strtotime($r['time'])),
    $r['sku'], $r['name'], $r['category'], $r['type'],
    $sign * (float)$r['qty'], $r['unit'], $r['reference']
  ]);
}
fclose($fp);
