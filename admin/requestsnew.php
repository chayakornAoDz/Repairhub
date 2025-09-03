<?php
// admin/requestsnew.php  — งาน "ใหม่" เท่านั้น + รองรับ schema ต่าง ๆ
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo = db();

/* === โหลด column names เพื่อตรวจว่ามีคอลัมน์อะไรบ้าง === */
$cols = $pdo->query("PRAGMA table_info(requests)")->fetchAll(PDO::FETCH_COLUMN, 1);
$has = function($name) use ($cols){ return in_array($name, $cols, true); };

/* === รับพารามิเตอร์ค้นหา/เพจจิเนชัน === */
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 5;
$offset   = ($page - 1) * $perPage;

/* === เงื่อนไขพื้นฐาน: งานใหม่ === */
$whereParts = ["status = ?"];
$params     = ['ใหม่'];

/* === เงื่อนไขค้นหา: build เฉพาะคอลัมน์ที่มีจริง === */
$searchableCandidates = [
  'ticket','ticket_no','code','request_code','request_no','ref',
  'detail','details','description','title',
  'requester_name','requester','name','requestor','reporter','owner'  // <-- เติม name, requestor
];
$searchable = array_values(array_filter($searchableCandidates, fn($c)=>$has($c)));

if ($q !== '' && $searchable){
  $like = "%{$q}%";
  $or = [];
  foreach($searchable as $c){ $or[] = "$c LIKE ?"; $params[] = $like; }
  $whereParts[] = '('.implode(' OR ', $or).')';
}

$whereSql = implode(' AND ', $whereParts);

/* === นับทั้งหมด (เฉพาะงานใหม่ + เงื่อนไขค้นหา) === */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

/* === ดึงรายการหน้า ===
     ใช้ SELECT * เพื่อหลีกเลี่ยงอ้างคอลัมน์ที่อาจไม่มีในบาง schema
*/
$listSql = "SELECT * FROM requests WHERE $whereSql ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

/* badge: จำนวนงานใหม่ทั้งหมด (ไม่กรองค้นหา) */
$newTotal = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='ใหม่'")->fetchColumn();

/* เพจจิเนชัน */
$totalPages = max(1, (int)ceil($total / $perPage));

/* Helpers */
function badge_priority($p){
  if ($p === 'เร่งด่วน') return 'badge warn';
  if ($p === 'วิกฤต')   return 'badge bad';
  return 'badge good';
}
function badge_status($s){
  switch($s){
    case 'ใหม่':               return 'badge bad';
    case 'กำลังดำเนินการ':     return 'badge warn';
    case 'รออะไหล่':           return 'badge muted';
    case 'เสร็จสิ้น':          return 'badge good';
    case 'ยกเลิก':             return 'badge bad';
    default:                    return 'badge';
  }
}
function first_nonempty(...$vals){
  foreach($vals as $v){ if(isset($v) && $v!=='') return $v; }
  return null;
}
?>
<style>
/* badge ตัวเลขสีขาวพื้นแดง */
.notif-count{
  display:inline-flex; align-items:center; justify-content:center;
  min-width:22px; height:22px; padding:0 8px;
  border-radius:999px; background:#ef4444; color:#fff;
  font-weight:700; font-size:12px; line-height:1;
  border:1px solid #b91c1c;
}
</style>

<div class="card">
  <div class="report-header" style="align-items:center; gap:10px">
    <h1 style="margin:0">งานใหม่</h1>
    <span class="notif-count"><?= (int)$newTotal ?></span>
    <div style="flex:1"></div>
    <a class="btn btn-ghost" href="requests.php">ดูงานทั้งหมด</a>
  </div>

  <form method="get" class="filter-row" style="margin-top:12px">
    <div>
      <label>ค้นหา</label>
      <input name="q" value="<?= h($q) ?>" placeholder="Ticket/ชื่อ/รายละเอียด">
    </div>
    <div></div><div></div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">กรอง</button>
    </div>
  </form>

  <div class="small" style="margin:10px 0">
    แสดง <?= $total ? ($offset+1) : 0 ?>–<?= min($offset+$perPage, $total) ?> จาก <?= (int)$total ?> งาน
  </div>

  <div class="table-wrap">
    <table class="table sticky zebra compact table-hover">
      <thead>
        <tr>
          <th style="width:140px">เวลา</th>
          <th>Ticket</th>
          <th>ผู้แจ้ง</th>
          <th>ประเภท</th>
          <th>สำคัญ</th>
          <th>สถานะ</th>
          <th style="width:70px"></th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="7" class="small" style="color:#9ca3af">ไม่มีงานใหม่</td></tr>
      <?php else: foreach($rows as $r):
        // ดึงค่าที่มีจริงใน schema ปัจจุบัน
        $ticket = first_nonempty(
          $r['ticket'] ?? null,
          $r['ticket_no'] ?? null,
          $r['code'] ?? null,
          $r['request_code'] ?? null,
          $r['request_no'] ?? null,
          $r['ref'] ?? null
        ) ?? ('#'.$r['id']);

        $requester = first_nonempty(
            $r['requester_name'] ?? null,
            $r['requester'] ?? null,
            $r['name'] ?? null,        // <-- เติมบรรทัดนี้
            $r['reporter'] ?? null,
            $r['owner'] ?? null,
            $r['requestor'] ?? null    // <-- เผื่อบางสคีมาใช้ชื่อนี้
            ) ?? '-';

        $category = first_nonempty(
          $r['category'] ?? null,
          $r['type'] ?? null
        ) ?? '-';

        $priority = first_nonempty(
          $r['priority'] ?? null,
          $r['importance'] ?? null
        ) ?? 'ปกติ';

        $status = $r['status'] ?? '-';

        $created = first_nonempty(
          $r['created_at'] ?? null,
          $r['created'] ?? null,
          $r['created_on'] ?? null,
          $r['created_date'] ?? null,
          $r['date'] ?? null
        );
        $createdFmt = $created ? date('d/m/Y H:i', strtotime($created)) : '-';
      ?>
        <tr>
          <td><?= h($createdFmt) ?></td>
          <td class="mono"><?= h($ticket) ?></td>
          <td><?= h($requester) ?></td>
          <td><span class="chip"><?= h($category) ?></span></td>
          <td><span class="<?= badge_priority($priority) ?>"><?= h($priority) ?></span></td>
          <td><span class="<?= badge_status($status) ?>"><?= h($status) ?></span></td>
          <td>
            <!-- เปลี่ยนปลายทาง link ให้ตรงกับหน้า view ของคุณ -->
            <a class="btn btn-sm" href="request_view.php?id=<?= (int)$r['id'] ?>">เปิด</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="?<?= http_build_query(['q'=>$q,'page'=>1]) ?>">« หน้าแรก</a>
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="?<?= http_build_query(['q'=>$q,'page'=>max(1,$page-1)]) ?>">ก่อนหน้า</a>
      <?php
        $start = max(1, $page-2);
        $end   = min($totalPages, $page+2);
        for($i=$start; $i<=$end; $i++):
      ?>
        <a class="page-btn <?= $i===$page?'active':'' ?>" href="?<?= http_build_query(['q'=>$q,'page'=>$i]) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="?<?= http_build_query(['q'=>$q,'page'=>min($totalPages,$page+1)]) ?>">ถัดไป »</a>
      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="?<?= http_build_query(['q'=>$q,'page'=>$totalPages]) ?>">หน้าสุดท้าย »</a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
