<?php
require_once __DIR__ . '/inc/functions.php';
$pdo = db();

/* ====== รับพารามิเตอร์ ====== */
$type  = $_GET['type']  ?? 'daily';
$day   = $_GET['day']   ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year  = $_GET['year']  ?? date('Y');
$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-d');
$title = $_GET['title'] ?? 'รายงาน';

/* ====== เงื่อนไขเดียวแล้วค่อย query ====== */
$where = '';
$params = [];
if ($type === 'daily') {
  $where = 'date(r.created_at)=?';           $params = [$day];
} elseif ($type === 'monthly') {
  $where = 'substr(r.created_at,1,7)=?';     $params = [$month];
} elseif ($type === 'yearly') {
  $where = 'substr(r.created_at,1,4)=?';     $params = [$year];
} else {
  $where = 'date(r.created_at) BETWEEN ? AND ?'; $params = [$from,$to];
}

/* ====== ดึงหมายเหตุล่าสุดของแต่ละงาน ====== */
$sql = "
  SELECT r.*,
         (
           SELECT ru.note
           FROM request_updates ru
           WHERE ru.request_id = r.id
                 AND ru.note IS NOT NULL AND ru.note <> ''
           ORDER BY ru.id DESC
           LIMIT 1
         ) AS admin_note
  FROM requests r
  WHERE $where
  ORDER BY r.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>

<link rel="stylesheet" href="assets/css/style.css">

<style>
/* พิมพ์แนวนอน */
@page { size: A4 landscape; margin: 12mm; }

html, body { background:#fff; color:#000; }
.container { max-width:100%; padding:0; }

/* เอากรอบของการ์ดออก ไม่ให้ซ้อนกับขอบตาราง */
.card{ box-shadow:none; border:0; }

table.table{
  width:100%;
  border-collapse:collapse;   /* รวมเส้นให้เป็นเส้นเดียว */
  border-spacing:0;
  table-layout:fixed;
}
.table th,.table td{
  border:1px solid #ccc;      /* ให้เส้นตารางมาจาก cell เพียงชุดเดียว */
  padding:8px 10px;
  font-size:12px;
  vertical-align:top;
  color:#000;
}

thead { display: table-header-group; }
tfoot { display: table-footer-group; }
tr    { page-break-inside: avoid; }

/* แถบหัวรายงาน (ไม่ให้มีเส้นชนหัวคอลัมน์) */
.thead-title{
  background:#f3f4f6;
  border:0;                   /* <-- ตัดเส้นของหัวเรื่องออก */
  padding:10px 12px;
  font-weight:700;
  font-size:16px;
  position:relative;
}
.thead-title .created{ font-weight:400; font-size:12px; color:#333; margin-left:12px; }
.thead-title .doc-code{ position:absolute; right:12px; top:8px; font-size:12px; font-weight:600; }

/* หัวคอลัมน์: ใส่เส้นบนใหม่ให้ต่อเนื่องกับหัวเรื่อง */
.thead-cols th{
  background:#f8fafc;
  border-top:1px solid #ccc;
}

.note{ white-space:pre-wrap; word-break:break-word; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

</style>
</head>
<body onload="window.print()">
<div class="container">
  <div class="card" style="padding:0">
    <table class="table">
      <!-- คุมความกว้างคอลัมน์ -->
      <colgroup>
        <col style="width:140px">  <!-- เวลา -->
        <col style="width:170px">  <!-- Ticket -->
        <col style="width:180px">  <!-- ผู้แจ้ง -->
        <col style="width:160px">  <!-- ประเภท -->
        <col style="width:120px">  <!-- สำคัญ -->
        <col style="width:140px">  <!-- สถานะ -->
        <col>                      <!-- หมายเหตุ -->
      </colgroup>

      <thead>
        <!-- แถบหัวรายงาน (ซ้ำทุกหน้า) -->
        <tr>
          <th class="thead-title" colspan="7">
            <?= h($title) ?>
            <span class="created">สร้างเมื่อ: <?= date('Y-m-d H:i') ?></span>
            <span class="doc-code">QF-AD-04-08</span>
          </th>
        </tr>
        <!-- หัวคอลัมน์ (ซ้ำทุกหน้า) -->
        <tr class="thead-cols">
          <th>เวลา</th>
          <th>Ticket</th>
          <th>ผู้แจ้ง</th>
          <th>ประเภท</th>
          <th>สำคัญ</th>
          <th>สถานะ</th>
          <th>หมายเหตุ</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
            <td class="mono"><?= h($r['ticket_no']) ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['category']) ?></td>
            <td><?= h($r['priority']) ?></td>
            <td><?= h($r['status']) ?></td>
            <td class="note"><?= h($r['admin_note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?>
          <tr><td colspan="7" style="text-align:center">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
