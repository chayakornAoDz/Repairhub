
<?php require_once __DIR__ . '/header.php'; require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create'){
  $stmt = $pdo->prepare('INSERT INTO loans (requester_name,contact,item_id,qty,loan_date,due_date,status,note,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$_POST['requester_name'], $_POST['contact']??'', (int)$_POST['item_id'], (float)$_POST['qty'], $_POST['loan_date'], $_POST['due_date']?:null, 'ยืมอยู่', $_POST['note']??'', $_SESSION['admin_id'], date('c')]);
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='return'){
  $loan_id = (int)$_POST['loan_id'];
  $pdo->beginTransaction();
  try{
    $g = $pdo->prepare('SELECT * FROM loans WHERE id=?'); $g->execute([$loan_id]); $loan = $g->fetch(PDO::FETCH_ASSOC);
    if($loan && !$loan['return_date']){
      // add stock back
      $it = $pdo->prepare('SELECT * FROM inventory_items WHERE id=?'); $it->execute([$loan['item_id']]); $item = $it->fetch(PDO::FETCH_ASSOC);
      $new = $item['stock_qty'] + $loan['qty'];
      $pdo->prepare('UPDATE inventory_items SET stock_qty=?, updated_at=? WHERE id=?')->execute([$new, date('c'), $item['id']]);
      $pdo->prepare('UPDATE loans SET status=?, return_date=? WHERE id=?')->execute(['คืนแล้ว', date('c'), $loan_id]);
      $pdo->prepare('INSERT INTO stock_movements (item_id,qty,type,reference,created_by,created_at) VALUES (?,?,?,?,?,?)')->execute([$item['id'],$loan['qty'],'return','คืนอุปกรณ์ #'.$loan_id,$_SESSION['admin_id'],date('c')]);
    }
    $pdo->commit();
  } catch(Exception $e){ $pdo->rollBack(); }
}

$items = $pdo->query('SELECT * FROM inventory_items ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$loans = $pdo->query('SELECT l.*, i.name AS item_name, i.unit FROM loans l LEFT JOIN inventory_items i ON i.id=l.item_id ORDER BY l.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
  <h1 style="margin-top:0">ยืมอุปกรณ์</h1>
  <form class="card" method="post">
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
    <h3 style="margin-top:0">รายการยืมล่าสุด</h3>
    <table class="table">
      <tr><th>ผู้ยืม</th><th>อุปกรณ์</th><th>จำนวน</th><th>วันที่ยืม</th><th>กำหนดคืน</th><th>สถานะ</th><th></th></tr>
      <?php foreach($loans as $l): ?>
        <tr>
          <td><?= h($l['requester_name']) ?></td>
          <td><?= h($l['item_name']) ?></td>
          <td><?= h($l['qty']) ?> <?= h($l['unit']) ?></td>
          <td><?= h($l['loan_date']) ?></td>
          <td><?= h($l['due_date'] ?: '-') ?></td>
          <td><span class="badge <?= $l['status']==='ยืมอยู่'?'warn':'good' ?>"><?= h($l['status']) ?></span></td>
          <td>
            <?php if(!$l['return_date']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>">
                <button class="btn">ทำเครื่องหมายว่า "คืนแล้ว"</button>
              </form>
            <?php else: ?>
              <span style="color:#9ca3af">คืนเมื่อ <?= h(date('d/m/Y', strtotime($l['return_date']))) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
