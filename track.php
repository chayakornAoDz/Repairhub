<?php
require_once __DIR__ . '/inc/functions.php';
$pdo = db();
$t = $_GET['t'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM requests WHERE ticket_no = ?');
$stmt->execute([$t]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

$updates = [];
if ($req) {
  $u = $pdo->prepare('SELECT ru.*, a.display_name FROM request_updates ru LEFT JOIN admins a ON a.id = ru.updated_by WHERE request_id = ? ORDER BY ru.id DESC');
  $u->execute([$req['id']]);
  $updates = $u->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<title>ติดตามงาน</title>
</head><body>
<div class="container">
  <div class="card">
    <h1 style="margin-top:0">ติดตามงาน</h1>
    <?php if(!$req): ?>
      <p>ไม่พบหมายเลข Ticket นี้</p>
      <a class="btn" href="index.php">ย้อนกลับ</a>
    <?php else: ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <div class="stat"><div>หมายเลข</div><div class="num"><?= h($req['ticket_no']) ?></div></div>
        <div class="stat"><div>สถานะ</div><div class="badge"><?= h($req['status']) ?></div></div>
        <div class="stat"><div>อัพเดตล่าสุด</div><div><?= h(date('d/m/Y H:i', strtotime($req['updated_at']))) ?></div></div>
      </div>
      <div class="card" style="margin-top:12px">
        <h3 style="margin-top:0">รายละเอียด</h3>
        <p><b>ผู้แจ้ง:</b> <?= h($req['name']) ?> | <b>ติดต่อ:</b> <?= h($req['contact']) ?></p>
        <p><b>แผนก:</b> <?= h($req['department']) ?> | <b>สถานที่:</b> <?= h($req['location']) ?></p>
        <p><b>ประเภท:</b> <?= h($req['category']) ?> | <b>ความสำคัญ:</b> <?= h($req['priority']) ?></p>
        <p style="white-space:pre-line"><?= h($req['description']) ?></p>
        <?php if($req['attachment']): ?>
          <p><a class="btn" href="download.php?f=<?= urlencode($req['attachment']) ?>" target="_blank">เปิดไฟล์แนบ</a></p>
        <?php endif; ?>
      </div>
      <div class="card" style="margin-top:12px">
        <h3 style="margin-top:0">ประวัติการอัพเดต</h3>
        <?php if(!$updates): ?>
          <p>ยังไม่มีการอัพเดต</p>
        <?php else: ?>
          <table class="table">
            <tr><th>เวลา</th><th>สถานะ</th><th>หมายเหตุ</th><th>โดย</th></tr>
            <?php foreach($updates as $u): ?>
              <tr>
                <td><?= h(date('d/m/Y H:i', strtotime($u['created_at']))) ?></td>
                <td><?= h($u['status']) ?></td>
                <td><?= nl2br(h($u['note'])) ?></td>
                <td><?= h($u['display_name'] ?? 'ระบบ') ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<div style="margin-top:20px; text-align:center;">
  <a class="btn" href="index.php">← กลับหน้าแจ้งซ่อม</a>
</div>
</body></html>
