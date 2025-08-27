
<?php require_once __DIR__ . '/header.php'; require_once __DIR__ . '/../inc/functions.php'; global $TICKET_STATUSES;
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM requests WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$r){ echo '<div class="card">ไม่พบรายการ</div>'; require __DIR__.'/footer.php'; exit; }

$updates = $pdo->prepare('SELECT ru.*, a.display_name FROM request_updates ru LEFT JOIN admins a ON a.id = ru.updated_by WHERE request_id = ? ORDER BY ru.id DESC');
$updates->execute([$id]);
$us = $updates->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
  <h1 style="margin-top:0">Ticket: <?= h($r['ticket_no']) ?></h1>
  <div class="row">
    <div class="card">
      <p><b>ผู้แจ้ง:</b> <?= h($r['name']) ?> | <b>ติดต่อ:</b> <?= h($r['contact']) ?></p>
      <p><b>หน่วยงาน:</b> <?= h($r['department']) ?> | <b>สถานที่:</b> <?= h($r['location']) ?></p>
      <p><b>ประเภท:</b> <?= h($r['category']) ?> | <b>สำคัญ:</b> <?= h($r['priority']) ?></p>
      <p style="white-space:pre-line"><?= h($r['description']) ?></p>
      <?php if($r['attachment']): ?>
        <p><a class="btn" href="../uploads/<?= h($r['attachment']) ?>" target="_blank">เปิดไฟล์แนบ</a></p>
      <?php endif; ?>
    </div>
    <div class="card">
      <form method="post" action="request_update.php">
        <h3 style="margin-top:0">อัพเดตสถานะ</h3>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <label>สถานะ</label>
        <select name="status">
          <?php foreach($TICKET_STATUSES as $s): ?>
            <option <?= $r['status']===$s?'selected':'' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
        <label>หมายเหตุ</label>
        <textarea class="auto" name="note" rows="3"></textarea>
        <button class="btn btn-primary" type="submit" style="margin-top:8px">บันทึก</button>
      </form>
      <p style="color:#9ca3af;margin-top:8px">สร้างเมื่อ: <?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></p>
      <p style="color:#9ca3af;margin-top:-8px">อัพเดตล่าสุด: <?= h(date('d/m/Y H:i', strtotime($r['updated_at']))) ?></p>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3 style="margin-top:0">ประวัติ</h3>
    <?php if(!$us): ?><p>ยังไม่มีประวัติ</p><?php else: ?>
      <table class="table">
        <tr><th>เวลา</th><th>สถานะ</th><th>หมายเหตุ</th><th>โดย</th></tr>
        <?php foreach($us as $u): ?>
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
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
