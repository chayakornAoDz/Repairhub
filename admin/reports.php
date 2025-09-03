<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$meId = (int)($_SESSION['admin_id'] ?? 0);

/* ====== PRG: บันทึก/แก้ไขหมายเหตุจากผู้ดูแล ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note') {
  $rid  = (int)($_POST['request_id'] ?? 0);
  $note = trim($_POST['note'] ?? '');

  if ($rid > 0) {
    $stmt = $pdo->prepare('INSERT INTO request_updates (request_id, status, note, updated_by, created_at)
                           VALUES (?,?,?,?,?)');
    $stmt->execute([$rid, null, $note, $meId ?: null, date('c')]);
  }
  $qs = $_GET;
  header('Location: reports.php?'.http_build_query($qs));
  exit;
}

/* ====== ตัวกรอง / พารามิเตอร์รายงาน ====== */
$type  = $_GET['type']  ?? 'daily'; // daily|monthly|yearly|range
$day   = $_GET['day']   ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year  = $_GET['year']  ?? date('Y');
$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-d');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset  = ($page - 1) * $perPage;

$map = [
  'daily'   => 'รายวัน',
  'monthly' => 'รายเดือน',
  'yearly'  => 'รายปี',
  'range'   => 'ช่วงวันที่กำหนด'
];

/* =========================
 *    WHERE + PARAMS
 * ========================= */
$where = "";
$params = [];

if ($type==='daily') {
  $where = "date(created_at)=?";
  $params = [$day];
  $title = "รายงานประจำวัน $day";
} elseif ($type==='monthly') {
  $where = "substr(created_at,1,7)=?";
  $params = [$month];
  $title = "รายงานประจำเดือน $month";
} elseif ($type==='yearly') {
  $where = "substr(created_at,1,4)=?";
  $params = [$year];
  $title = "รายงานประจำปี $year";
} else {
  $where = "date(created_at) BETWEEN ? AND ?";
  $params = [$from,$to];
  $title = "รายงานช่วงวันที่ $from ถึง $to";
}

/* นับทั้งหมด */
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE $where");
$stmtCnt->execute($params);
$totalRows  = (int)$stmtCnt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* =========================
 *   ดึงหน้า + หมายเหตุล่าสุด
 * ========================= */
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
  LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$pos = 1;
foreach ($params as $p) { $stmt->bindValue($pos++, $p); }
$stmt->bindValue($pos++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($pos++, $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* util: สร้าง class สำหรับ badge */
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
        <a class="btn" target="_blank" href="../export_pdf.php?<?= http_build_query(['type'=>$type,'day'=>$day,'month'=>$month,'year'=>$year,'from'=>$from,'to'=>$to,'title'=>$title]) ?>">ดาวน์โหลด PDF</a>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table zebra sticky responsive">
        <thead>
          <tr>
            <th style="width:160px">เวลา</th>
            <th style="width:170px">Ticket</th>
            <th>ผู้แจ้ง</th>
            <th style="width:160px">ประเภท</th>
            <th style="width:120px">สำคัญ</th>
            <th style="width:160px">สถานะ</th>
            <th style="min-width:240px">หมายเหตุ (ผู้ดูแล)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
              <td class="mono"><?= h($r['ticket_no']) ?></td>
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
              <td>
                <form method="post" class="note-form no-print" style="display:flex; gap:6px; align-items:center">
                  <input type="hidden" name="action" value="save_note">
                  <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                  <input
                    name="note"
                    value="<?= h($r['admin_note'] ?? '') ?>"
                    placeholder="เพิ่มหมายเหตุ..."
                    style="flex:1; min-width:180px"
                  >
                  <button class="btn btn-sm" type="submit">บันทึก</button>
                </form>
                <div class="only-print">
                  <?= h($r['admin_note'] ?? '') ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if(!$rows): ?><p class="muted" style="margin-top:10px">ไม่พบข้อมูล</p><?php endif; ?>
    </div>

    <?php if($totalPages > 1): ?>
      <?php
        // ใช้ตัวช่วยสร้างลิงก์เพจ ให้ชี้รายงานไฟล์นี้เสมอ (reports.php)
        $mk = fn($p) => rh_page_url(max(1, min($totalPages, (int)$p)));
      ?>
      <div class="pagination">
        <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="<?= h($mk($page-1)) ?>">← ก่อนหน้า</a>
        <span class="badge">หน้า <?= (int)$page ?> / <?= (int)$totalPages ?></span>
        <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= h($mk($page+1)) ?>">ถัดไป →</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>

<style>
  .table-wrap{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .table.responsive th, .table.responsive td{ white-space:nowrap; }

  .report-header{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
  .report-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  @media (max-width:640px){
    .report-header{ align-items:flex-start; }
    .report-actions .btn{ width:100%; }
  }

  .report-toolbar{ display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; }
  @media (max-width:1024px){ .report-toolbar{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width:640px){ .report-toolbar{ grid-template-columns: 1fr; } }

  @media (max-width:1024px){
    .table.responsive th:nth-child(4),
    .table.responsive td:nth-child(4),
    .table.responsive th:nth-child(5),
    .table.responsive td:nth-child(5){ display:none; }
  }

  .note-form{ display:flex; gap:6px; align-items:center; }
  @media (max-width:768px){
    .note-form{ flex-direction:column; align-items:stretch; }
    .note-form input{ width:100%; }
    .note-form .btn{ width:100%; }
  }

  @media (max-width:640px){
    .table.responsive th, .table.responsive td{ font-size:13px; }
    .table.responsive th:nth-child(1){ width:120px; }
  }
</style>
