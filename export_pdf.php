
<?php
require_once __DIR__ . '/inc/functions.php';
$pdo = db();
$type = $_GET['type'] ?? 'daily';
$day = $_GET['day'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$title = $_GET['title'] ?? 'รายงาน';

if($type==='daily'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at)=? ORDER BY id DESC');
  $stmt->execute([$day]);
} elseif($type==='monthly'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,7)=? ORDER BY id DESC');
  $stmt->execute([$month]);
} elseif($type==='yearly'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,4)=? ORDER BY id DESC');
  $stmt->execute([$year]);
} else {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at) BETWEEN ? AND ? ORDER BY id DESC');
  $stmt->execute([$from,$to]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<title><?= h($title) ?></title>
<style>
body{background:#fff;color:#000}
.card{box-shadow:none;border:1px solid #ccc}
.table th,.table td{border-color:#ccc;color:#000}
.badge{color:#000;border-color:#000}
</style>
</head><body onload="window.print()">
<div class="container">
  <div class="card">
    <h1 style="margin:0"><?= h($title) ?></h1>
    <p>สร้างเมื่อ: <?= date('Y-m-d H:i') ?></p>
    <table class="table">
      <tr><th>เวลา</th><th>Ticket</th><th>ผู้แจ้ง</th><th>ประเภท</th><th>สำคัญ</th><th>สถานะ</th></tr>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= h($r['ticket_no']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['category']) ?></td>
          <td><?= h($r['priority']) ?></td>
          <td><?= h($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if(!$rows): ?><p>ไม่พบข้อมูล</p><?php endif; ?>
  </div>
</div>
</body></html>
