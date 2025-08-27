
<?php
require_once __DIR__ . '/inc/functions.php';
guard_csrf();
if (!empty($_POST['website'])) { die('Bot detected'); }

$pdo = db();
$ticket = gen_ticket_no($pdo);
$attachment = save_upload('attachment', __DIR__ . '/uploads');

$stmt = $pdo->prepare('INSERT INTO requests (ticket_no,name,contact,department,location,category,priority,description,attachment,status,created_at,updated_at)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
$now = date('c');
$stmt->execute([$ticket, $_POST['name']??'', $_POST['contact']??'', $_POST['department']??'', $_POST['location']??'', $_POST['category']??'', $_POST['priority']??'ปกติ', $_POST['description']??'', $attachment, 'ใหม่', $now, $now]);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<title>ส่งคำขอสำเร็จ</title>
</head><body>
<div class="container">
  <div class="card">
    <h1 style="margin-top:0">✅ ส่งคำขอสำเร็จ</h1>
    <p>หมายเลข Ticket ของคุณคือ <span class="badge" style="font-size:18px"><?= h($ticket) ?></span></p>
    <p>สามารถใช้หมายเลขนี้ติดตามสถานะงานได้ที่หน้าแรก</p>
    <div style="display:flex;gap:8px;margin-top:12px">
      <a class="btn" href="index.php">ย้อนกลับหน้าแรก</a>
      <a class="btn btn-primary" href="track.php?t=<?= urlencode($ticket) ?>">ดูสถานะงาน</a>
    </div>
  </div>
</div>
</body></html>
