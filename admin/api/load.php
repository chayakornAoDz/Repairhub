<?php
header('Content-Type: application/json; charset=utf-8');
$year  = intval($_GET['year'] ?? 0);
$month = intval($_GET['month'] ?? 0);

$set = (strpos(__DIR__, 'hik') !== false) ? 'hikvision' : 'dahua';
$file = __DIR__ . "/../data/$set/" . sprintf("%04d-%02d.json", $year, $month);

if (is_file($file)) {
  $json = json_decode(file_get_contents($file), true);
  echo json_encode(['ok'=>1, 'data'=>$json['data'] ?? null], JSON_UNESCAPED_UNICODE);
} else {
  echo json_encode(['ok'=>1, 'data'=>null]); // ไม่มีไฟล์ = ยังไม่มีข้อมูล
}
