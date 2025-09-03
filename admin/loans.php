<?php
// ---------- ทำส่วน POST ให้เสร็จก่อน แล้วค่อย require header ----------

require_once __DIR__ . '/../inc/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

// ไฟล์ปัจจุบัน (กัน base href ทำให้หลุดไป /admin/)
$self = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: 'loans.php');

// หน้าปัจจุบัน (รับจาก GET ตอนเข้าหน้า/ตอน redirect กลับ)
$page = max(1, (int)($_GET['page'] ?? 1));

/* ===== Handle POST ===== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  try {
    $act = $_POST['action'] ?? '';

    if ($act === 'create') {
      $stmt = $pdo->prepare('
        INSERT INTO loans
          (requester_name, contact, item_id, qty, loan_date, due_date, status, note, created_by, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)
      ');
      $stmt->execute([
        trim($_POST['requester_name'] ?? ''),
        trim($_POST['contact'] ?? ''),
        (int)($_POST['item_id'] ?? 0),
        (float)($_POST['qty'] ?? 0),
        $_POST['loan_date'] ?? date('Y-m-d'),
        ($_POST['due_date'] ?? '') ?: null,
        'ยืมอยู่',
        trim($_POST['note'] ?? ''),
        $_SESSION['admin_id'] ?? null,
        date('c'),
      ]);
    }

    if ($act === 'return') {
      $loan_id = (int)($_POST['loan_id'] ?? 0);

      $pdo->beginTransaction();

      $g = $pdo->prepare('SELECT * FROM loans WHERE id=?');
      $g->execute([$loan_id]);
      $loan = $g->fetch(PDO::FETCH_ASSOC);

      if ($loan && empty($loan['return_date'])) {
        // คืนสต็อก
        $it = $pdo->prepare('SELECT * FROM inventory_items WHERE id=?');
        $it->execute([(int)$loan['item_id']]);
        $item = $it->fetch(PDO::FETCH_ASSOC);
        if (!$item) throw new Exception('ไม่พบสินค้า');

        $new = (float)$item['stock_qty'] + (float)$loan['qty'];
        $pdo->prepare('UPDATE inventory_items SET stock_qty=?, updated_at=? WHERE id=?')
            ->execute([$new, date('c'), (int)$item['id']]);

        // อัปเดตสถานะยืม
        $pdo->prepare('UPDATE loans SET status=?, return_date=? WHERE id=?')
            ->execute(['คืนแล้ว', date('c'), $loan_id]);

        // บันทึกความเคลื่อนไหวสต็อก (return)
        $pdo->prepare('
          INSERT INTO stock_movements (item_id, qty, type, reference, created_by, created_at)
          VALUES (?,?,?,?,?,?)
        ')->execute([(int)$item['id'], (float)$loan['qty'], 'return', 'คืนอุปกรณ์ #'.$loan_id, $_SESSION['admin_id'] ?? null, date('c')]);
      }

      $pdo->commit();
    }

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // เก็บ flash ไว้ก็ได้
    $_SESSION['flash'] = ['err' => 'ผิดพลาด: '.$e->getMessage()];
  }

  // PRG: กลับมาหน้าเดิมเสมอ (กัน F5 ซ้ำ)
  header('Location: '.$self.'?'.http_build_query(['page'=>$page]));
  exit;
}

// ---------- จากนี้ค่อยเริ่มเรนเดอร์หน้า ----------
require_once __DIR__ . '/header.php';

/* ===== Data for forms ===== */
$items = $pdo->query('SELECT * FROM inventory_items ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

/* ===== Pagination config ===== */
$perPage = 5;

$totalLoans = (int)$pdo->query('SELECT COUNT(*) FROM loans')->fetchColumn();
$totalPages = max(1, (int)ceil($totalLoans / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* ===== Fetch page data ===== */
$listStmt = $pdo->prepare('
  SELECT l.*, i.name AS item_name, i.unit
  FROM loans l
  LEFT JOIN inventory_items i ON i.id = l.item_id
  ORDER BY l.id DESC
  LIMIT :limit OFFSET :offset
');
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$loans = $listStmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== Helper: build page URL ===== */
$buildPage = function(int $p) use ($self) {
  $qs = $_GET; unset($qs['page']); $qs['page'] = $p;
  return $self.'?'.http_build_query($qs);
};
?>
<div class="card">
  <h1 style="margin-top:0">ยืมอุปกรณ์</h1>

  <!-- action ต้องชี้ไฟล์ปัจจุบันแบบ explicit -->
  <form class="card" method="post" action="<?= h($self.'?'.http_build_query(['page'=>$page])) ?>">
    <h3 style="margin-top:0">สร้างบันทึกการยืม</h3>
    <input type="hidden" name="action" value="create">
    <div class="row">
      <div><label>ผู้ยืม</label><input name="requester_name" required></div>
      <div><label>ติดต่อ</label><input name="contact"></div>
    </div>
    <div class="row">
      <div>
        <label>อุปกรณ์</label>
        <select name="item_id" required>
          <option value="">-- เลือก --</option>
          <?php foreach($items as $i): ?>
            <option value="<?= (int)$i['id'] ?>"><?= h($i['name']) ?> (คงเหลือ: <?= h($i['stock_qty']) ?> <?= h($i['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>จำนวน</label>
        <input type="number" step="0.01" name="qty" required>
      </div>
    </div>
    <div class="row">
      <div><label>วันที่ยืม</label><input type="date" name="loan_date" value="<?= h(date('Y-m-d')) ?>" required></div>
      <div><label>กำหนดคืน</label><input type="date" name="due_date"></div>
    </div>
    <label>หมายเหตุ</label><input name="note">
    <button class="btn btn-primary" type="submit" style="margin-top:8px">บันทึก</button>
  </form>

  <div class="card" style="margin-top:12px">
    <div class="report-header" style="margin-bottom:6px">
      <h3 style="margin:0">รายการยืมล่าสุด</h3>
      <div class="small" style="color:#9ca3af">
        แสดง <?= $totalLoans ? ($offset+1) : 0 ?>–<?= min($offset + $perPage, $totalLoans) ?> จากทั้งหมด <?= $totalLoans ?> รายการ
      </div>
    </div>

    <table class="table">
      <tr><th>ผู้ยืม</th><th>อุปกรณ์</th><th>จำนวน</th><th>วันที่ยืม</th><th>กำหนดคืน</th><th>สถานะ</th><th></th></tr>
      <?php if(!$loans): ?>
        <tr><td colspan="7" style="color:#9ca3af">ไม่มีข้อมูล</td></tr>
      <?php else: ?>
        <?php foreach($loans as $l): ?>
          <tr>
            <td><?= h($l['requester_name']) ?></td>
            <td><?= h($l['item_name']) ?></td>
            <td><?= h($l['qty']) ?> <?= h($l['unit']) ?></td>
            <td><?= h($l['loan_date']) ?></td>
            <td><?= h($l['due_date'] ?: '-') ?></td>
            <td><span class="badge <?= $l['status']==='ยืมอยู่'?'warn':'good' ?>"><?= h($l['status']) ?></span></td>
            <td>
              <?php if(empty($l['return_date'])): ?>
                <!-- ตรงนี้ก็ต้องชี้มาที่ไฟล์ปัจจุบัน -->
                <form method="post" action="<?= h($self.'?'.http_build_query(['page'=>$page])) ?>" style="display:inline">
                  <input type="hidden" name="action" value="return">
                  <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                  <button class="btn btn-sm">ทำเครื่องหมายว่า "คืนแล้ว"</button>
                </form>
              <?php else: ?>
                <span style="color:#9ca3af">คืนเมื่อ <?= h(date('d/m/Y', strtotime($l['return_date']))) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a class="page-btn" href="<?= h($buildPage($page-1)) ?>">ก่อนหน้า</a>
        <?php else: ?>
          <span class="page-btn disabled">ก่อนหน้า</span>
        <?php endif; ?>

        <?php for($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="page-btn active"><?= $p ?></span>
          <?php else: ?>
            <a class="page-btn" href="<?= h($buildPage($p)) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a class="page-btn" href="<?= h($buildPage($page+1)) ?>">ถัดไป</a>
        <?php else: ?>
          <span class="page-btn disabled">ถัดไป</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
