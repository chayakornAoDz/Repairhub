<?php
header('Content-Type: application/json; charset=utf-8');
$year  = intval($_GET['year'] ?? 0);
$month = intval($_GET['month'] ?? 0);
if (!$year || !$month) { echo json_encode(['ok'=>0,'data'=>null]); exit; }

$root = dirname(__DIR__, 2); // .../admin/cctv_bundle
$dir  = $root . '/data/hikvision';
$file = $dir . '/' . sprintf('%04d-%02d.json', $year, $month);

if (is_file($file)) {
  $json = json_decode(@file_get_contents($file), true);
  echo json_encode(['ok'=>1, 'data'=>$json['data'] ?? null], JSON_UNESCAPED_UNICODE);
} else {
  echo json_encode(['ok'=>1, 'data'=>null], JSON_UNESCAPED_UNICODE);
}
