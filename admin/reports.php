<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

$type  = $_GET['type']  ?? 'daily'; // daily|monthly|yearly|range
$day   = $_GET['day']   ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year  = $_GET['year']  ?? date('Y');
$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-d');

$map = [
  'daily'   => 'รายวัน',
  'monthly' => 'รายเดือน',
  'yearly'  => 'รายปี',
  'range'   => 'ช่วงวันที่กำหนด'
];

// ---------------- Query ----------------
if ($type==='daily') {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at)=? ORDER BY id DESC');
  $stmt->execute([$day]);
  $title = "รายงานประจำวัน $day";
} elseif ($type==='monthly') {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,7)=? ORDER BY id DESC');
  $stmt->execute([$month]);
  $title = "รายงานประจำเดือน $month";
} elseif ($type==='yearly') {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,4)=? ORDER BY id DESC');
  $stmt->execute([$year]);
  $title = "รายงานประจำปี $year";
} else {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at) BETWEEN ? AND ? ORDER BY id DESC');
  $stmt->execute([$from,$to]);
  $title = "รายงานช่วงวันที่ $from ถึง $to";
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// util: สร้าง class สำหรับ badge จากข้อความไทย (ตัดเว้นวรรค)
function th_class($prefix, $text){
  $t = preg_replace('/\s+/', '', trim((string)$text));
  return $prefix . '-' . $t;
}
?>
<div class="card">
  <h1 style="margin-top:0">รายงาน</h1>

  <form class="report-toolbar no-print" method="get">
    <div>
      <label>ประเภท</label>
      <select name="type" onchange="this.form.submit()">
        <?php foreach($map as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $k===$type?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if($type==='daily'): ?>
      <div><label>เลือกวัน</label><input type="date" name="day" value="<?= h($day) ?>"></div>
    <?php elseif($type==='monthly'): ?>
      <div><label>เลือกเดือน</label><input type="month" name="month" value="<?= h($month) ?>"></div>
    <?php elseif($type==='yearly'): ?>
      <div><label>เลือกปี</label><input type="number" name="year" value="<?= h($year) ?>"></div>
    <?php else: ?>
      <div><label>จาก</label><input type="date" name="from" value="<?= h($from) ?>"></div>
      <div><label>ถึง</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <?php endif; ?>

    <div style="align-self:end">
      <button class="btn btn-primary" type="submit">ดูรายงาน</button>
    </div>
  </form>

  <div class="card" style="margin-top:12px">
    <div class="report-header">
      <h2 style="margin:0"><?= h($title) ?></h2>
      <div class="no-print report-actions">
        <a class="btn" href="../export_csv.php?<?= http_build_query(['type'=>$type,'day'=>$day,'month'=>$month,'year'=>$year,'from'=>$from,'to'=>$to]) ?>">ดาวน์โหลด CSV</a>
        <a class="btn" target="_blank" href="../export_pdf.php?<?= http_build_query(['type'=>$type,'day'=>$day,'month'=>$month,'year'=>$year,'from'=>$from,'to'=>$to,'title'=>$title]) ?>">PDF (พิมพ์/บันทึก)</a>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table zebra sticky">
        <thead>
          <tr>
            <th style="width:160px">เวลา</th>
            <th style="width:170px">Ticket</th>
            <th>ผู้แจ้ง</th>
            <th style="width:160px">ประเภท</th>
            <th style="width:120px">สำคัญ</th>
            <th style="width:160px">สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
              <td><?= h($r['ticket_no']) ?></td>
              <td><?= h($r['name']) ?></td>
              <td><?= h($r['category']) ?></td>
              <td>
                <span class="badge <?= h(th_class('prio', $r['priority'])) ?>">
                  <?= h($r['priority']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= h(th_class('status', $r['status'])) ?>">
                  <?= h($r['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if(!$rows): ?><p class="muted" style="margin-top:10px">ไม่พบข้อมูล</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
