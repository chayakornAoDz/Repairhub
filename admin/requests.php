<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
global $TICKET_STATUSES;

$pdo = db();

/* ---------- รับค่ากรอง ---------- */
$kw  = $_GET['kw']  ?? '';
$st  = $_GET['st']  ?? '';
$cat = $_GET['cat'] ?? '';

/* ---------- เพจิเนชัน ---------- */
$perPage = 5;                                     // แสดง 5 แถวต่อหน้า
$page    = max(1, (int)($_GET['page'] ?? 1));

/* ---------- WHERE กลาง ---------- */
$where  = 'FROM requests WHERE 1=1';
$params = [];

if ($kw !== '') {
  $where .= ' AND (ticket_no LIKE ? OR name LIKE ? OR description LIKE ?)';
  $params[] = "%$kw%"; $params[] = "%$kw%"; $params[] = "%$kw%";
}
if ($st !== '') {
  $where .= ' AND status = ?';
  $params[] = $st;
}
if ($cat !== '') {
  $where .= ' AND category = ?';
  $params[] = $cat;
}

/* ---------- จำนวนทั้งหมด/หน้ารวม ---------- */
$stmt = $pdo->prepare("SELECT COUNT(*) $where");
$stmt->execute($params);
$totalRows  = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* ---------- ดึงข้อมูลตามหน้า ---------- */
$sql = "SELECT * $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $v) { $stmt->bindValue($i+1, $v); } // bind ? ของ filter
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- ประเภททั้งหมด (ทำ dropdown) ---------- */
$cats = $pdo->query("SELECT DISTINCT category FROM requests WHERE category <> '' ORDER BY category")
            ->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card">
  <h1 style="margin-top:0">งานซ่อม</h1>
  <a class="btn" href="../index.php" style="margin:12px 0;display:inline-flex;align-items:center;gap:8px">⬅️ ย้อนกลับหน้าแจ้งซ่อม</a>

  <!-- ฟอร์มกรอง -->
  <form class="filter-row" method="get">
    <div>
      <label>ค้นหา</label>
      <input name="kw" value="<?= h($kw) ?>" placeholder="Ticket/ชื่อ/รายละเอียด">
    </div>
    <div>
      <label>สถานะ</label>
      <select name="st">
        <option value="">ทั้งหมด</option>
        <?php foreach($TICKET_STATUSES as $s): ?>
          <option value="<?= h($s) ?>" <?= $st===$s?'selected':'' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>ประเภท</label>
      <div class="hstack">
        <select name="cat">
          <option value="">ทุกประเภท</option>
          <?php foreach($cats as $c): ?>
            <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
        <a class="btn btn-ghost" href="requests.php">ล้างค่า</a>
      </div>
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit" style="width:100%">กรอง</button>
    </div>
  </form>

  <!-- สรุปจำนวน -->
  <div class="muted small" style="margin:10px 0">
    แสดง <?= $totalRows ? ($offset+1) : 0 ?>–<?= min($offset+$perPage, $totalRows) ?> จาก <?= $totalRows ?> งาน
  </div>

  <table class="table table-hover compact">
    <thead>
      <tr>
        <th>เวลา</th>
        <th>Ticket</th>
        <th>ผู้แจ้ง</th>
        <th>ประเภท</th>
        <th>สำคัญ</th>
        <th>สถานะ</th>
        <th style="width:80px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></td>
          <td class="mono"><?= h($r['ticket_no']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><span class="chip"><?= h($r['category']) ?: '-' ?></span></td>
          <td>
            <?php
              $prio = trim($r['priority'] ?? 'ปกติ');
              $prioClassMap = [
                'วิกฤต'   => 'bad',
                'ด่วนมาก' => 'bad',
                'เร่งด่วน' => 'warn',
                'สูง'      => 'warn',
                'ปกติ'     => 'good',
                'ต่ำ'      => 'muted',
              ];
              $pc = $prioClassMap[$prio] ?? 'good';
            ?>
            <span class="badge <?= $pc ?>"><?= h($prio) ?></span>
          </td>
          <td>
            <?php
              $status = trim($r['status'] ?? '');
              $statusClassMap = [
                'ใหม่'          => 'bad',
                'กำลังดำเนินการ' => 'warn',
                'รออะไหล่'      => 'warn',
                'เสร็จสิ้น'      => 'good',
                'ยกเลิก'        => 'bad',
              ];
              $sc = $statusClassMap[$status] ?? 'muted';
            ?>
            <span class="badge <?= $sc ?>"><?= h($status) ?></span>
          </td>
          <td><a class="btn btn-ghost" href="request_view.php?id=<?= (int)$r['id'] ?>">เปิด</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="7" class="muted">ไม่พบข้อมูล</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- เพจิเนชัน -->
  <?php if ($totalPages > 1): ?>
    <?php
      // ใช้ตัวช่วยทำลิงก์เพจ — จะได้เป็น requests.php?page=2 เสมอ
      $mk = fn($p) => rh_page_url($p);
      $window = 5;
      $start  = max(1, $page - 2);
      $end    = min($totalPages, max($start + $window - 1, $page + 2));
      $start  = max(1, min($start, $end - $window + 1));
    ?>
    <nav class="pagination">
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="<?= h($mk(1)) ?>">« หน้าแรก</a>
      <a class="page-btn <?= $page<=1?'disabled':'' ?>" href="<?= h($mk(max(1,$page-1))) ?>">‹ ก่อนหน้า</a>

      <?php for($p=$start; $p<=$end; $p++): ?>
        <a class="page-btn <?= $p==$page?'active':'' ?>" href="<?= h($mk($p)) ?>"><?= (int)$p ?></a>
      <?php endfor; ?>

      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= h($mk(min($totalPages,$page+1))) ?>">ถัดไป ›</a>
      <a class="page-btn <?= $page>=$totalPages?'disabled':'' ?>" href="<?= h($mk($totalPages)) ?>">หน้าสุดท้าย »</a>
    </nav>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
