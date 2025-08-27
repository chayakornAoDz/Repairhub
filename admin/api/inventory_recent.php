<?php
// admin/api/inventory_recent.php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/functions.php';
// ถ้าต้องการให้เฉพาะแอดมินดูได้ ให้เปิดสองบรรทัดนี้
// require_once __DIR__ . '/../../inc/auth.php';
// require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = db();

    // จำกัดแถวแบบปลอดภัย
    $limit = max(1, min((int)($_GET['limit'] ?? 5), 20));

    $stmt = $pdo->prepare("
        SELECT
            m.id, m.item_id, m.qty, m.type, m.reference,
            m.created_at, m.balance_after,
            i.sku, i.name, i.unit
        FROM stock_movements m
        LEFT JOIN inventory_items i ON i.id = m.item_id
        ORDER BY m.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    date_default_timezone_set('Asia/Bangkok');
    foreach ($rows as &$r) {
        $ts = strtotime($r['created_at'] ?? '');
        $r['created_at_th'] = $ts ? date('d/m/Y H:i', $ts) : '';
        foreach (['sku','name','unit','reference'] as $k) {
            if (!isset($r[$k]) || $r[$k] === null) $r[$k] = '';
        }
        // บังคับชนิดข้อมูลสำหรับ front-end
        if (!isset($r['qty'])) $r['qty'] = 0;
        if (!isset($r['type'])) $r['type'] = '';
        if (!isset($r['balance_after'])) $r['balance_after'] = null;
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
