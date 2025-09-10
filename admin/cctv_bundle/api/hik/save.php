<?php
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$year  = intval($input['year'] ?? 0);
$month = intval($input['month'] ?? 0);
$data  = $input['data'] ?? null;
if (!$year || !$month || !is_array($data)) { http_response_code(400); echo json_encode(['ok'=>0,'msg'=>'bad input']); exit; }

$root = dirname(__DIR__, 2);
$dir  = $root . '/data/hikvision';
if (!is_dir($dir)) @mkdir($dir, 0777, true);
$file = $dir . '/' . sprintf('%04d-%02d.json', $year, $month);
@file_put_contents($file, json_encode(['data'=>$data], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo json_encode(['ok'=>1]);
