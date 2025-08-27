<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo  = db();
$kRaw = $_GET['k'] ?? 'all'; // 'all' หรือเลข เช่น 10

// 6 เดือนล่าสุด (รวมเดือนนี้)
$months = [];
for ($i = 5; $i >= 0; $i--) {
  $months[] = date('Y-m', strtotime("-$i month"));
}

// ดึงยอดต่อเดือน/หมวด โดยไม่พึ่ง strftime/date (กัน format เพี้ยน)
$sql = "
  SELECT substr(created_at,1,7) AS ym,
         COALESCE(category,'ไม่ระบุ') AS category,
         COUNT(*) AS c
  FROM requests
  GROUP BY ym, category
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// รวมยอดทั้งช่วง เพื่อจัดลำดับหมวด
$agg = [];
foreach ($rows as $r) {
  $agg[$r['category']] = ($agg[$r['category']] ?? 0) + (int)$r['c'];
}
arsort($agg);

// ชุดหมวดที่จะแสดง
if ($kRaw === 'all') {
  $categories = array_keys($agg);             // ทุกหมวด (เรียงมาก→น้อย)
} else {
  $K = max(1, (int)$kRaw);
  $categories = array_slice(array_keys($agg), 0, $K);
}

// index helpers
$indexByMonth = array_flip($months);
$indexByCat   = array_flip($categories);

// สร้าง matrix เดือน x หมวด (ค่าเริ่มต้น 0)
$matrix = [];
foreach ($months as $m) $matrix[] = array_fill(0, count($categories), 0);

// ใส่ค่าลง matrix (นับเฉพาะ 6 เดือนล่าสุด และเฉพาะหมวดที่เลือก)
foreach ($rows as $r) {
  $ym  = $r['ym'];
  $cat = $r['category'];
  if (!isset($indexByMonth[$ym])) continue;
  if (!isset($indexByCat[$cat])) continue;
  $matrix[$indexByMonth[$ym]][$indexByCat[$cat]] += (int)$r['c'];
}

// ป้ายกำกับล่าง (m/y)
$labels = array_map(fn($ym)=>date('m/y', strtotime($ym.'-01')), $months);

// หา max สำหรับสเกลแกน Y
$max = 1;
foreach ($matrix as $row) foreach ($row as $v) if ($v > $max) $max = $v;

echo json_encode([
  'mode'       => 'grouped',
  'months'     => $months,
  'labels'     => $labels,
  'categories' => $categories,
  'matrix'     => $matrix,
  'max'        => $max
], JSON_UNESCAPED_UNICODE);
