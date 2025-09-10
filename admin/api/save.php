<?php
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$year  = intval($data['year'] ?? 0);
$month = intval($data['month'] ?? 0);
$payload = $data['data'] ?? null;

if (!$year || !$month || !is_array($payload)) {
  http_response_code(400); echo json_encode(['ok'=>0,'msg'=>'bad input']); exit;
}

$set = (strpos(__DIR__, 'hik') !== false) ? 'hikvision' : 'dahua'; // แยกโฟลเดอร์ตาม path
$dir = __DIR__ . "/../data/$set";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$file = sprintf("%s/%04d-%02d.json", $dir, $year, $month);
file_put_contents($file, json_encode(['data'=>$payload], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo json_encode(['ok'=>1]);
