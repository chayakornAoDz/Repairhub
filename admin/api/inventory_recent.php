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

    // พารามิเตอร์
    $limit   = max(1, min((int)($_GET['limit']  ?? 5), 20)); // จำกัดสูงสุด 20 ตามเดิม
    $offset  = max(0, (int)($_GET['offset'] ?? 0));
    $withMeta = isset($_GET['meta']); // ถ้าส่ง meta=1 จะคืน total ด้วย

    // นับ total เฉพาะเมื่อขอ meta เพื่อลดภาระฐานข้อมูล
    $total = null;
    if ($withMeta) {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
    }

    // ดึงข้อมูลหน้า/ออฟเซ็ต
    $stmt = $pdo->prepare("
        SELECT
            m.id, m.item_id, m.qty, m.type, m.reference,
            m.created_at, m.balance_after,
            i.sku, i.name, i.unit
        FROM stock_movements m
        LEFT JOIN inventory_items i ON i.id = m.item_id
        ORDER BY m.created_at DESC, m.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // จัดรูปเวลา + ค่าดีฟอลต์ปลอดภัย
    // (ตั้ง timezone ในสคริปต์นี้เท่านั้น เพื่อไม่ไปรบกวนที่อื่น)
    date_default_timezone_set('Asia/Bangkok');

    foreach ($rows as &$r) {
        // รองรับรูปแบบ ISO8601 ที่มี 'T'
        $ts = strtotime(str_replace('T', ' ', $r['created_at'] ?? ''));
        $r['created_at_th'] = $ts ? date('d/m/Y H:i', $ts) : '';

        // ค่าดีฟอลต์
        foreach (['sku','name','unit','reference'] as $k) {
            if (!isset($r[$k]) || $r[$k] === null) $r[$k] = '';
        }
        if (!isset($r['qty']))           $r['qty'] = 0;
        if (!isset($r['type']))          $r['type'] = '';
        if (!array_key_exists('balance_after', $r)) $r['balance_after'] = null;
    }
    unset($r);

    // ตอบกลับ (คง backward compatibility)
    if ($withMeta) {
        echo json_encode(['rows' => $rows, 'total' => $total], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
