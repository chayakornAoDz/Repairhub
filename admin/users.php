<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

// กำหนดว่าใครคือ Administrator (id=1)
$ADMIN_ID = 1;

// ผู้ใช้ที่ล็อกอินอยู่ตอนนี้
$me_id = (int)($_SESSION['admin_id'] ?? 0);

// สร้าง / ลบ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // สร้างผู้ใช้ใหม่
  if (($_POST['action'] ?? '') === 'create') {
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';

    // อัปโหลดรูป (ถ้ามี)
    $pic = null;
    if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $pic = save_upload('profile_pic', __DIR__ . '/../uploads');
    }

    // กันชื่อผู้ใช้ซ้ำ
    $chk = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username=?');
    $chk->execute([$username]);
    if ($chk->fetchColumn() > 0) {
      echo "<div class='card'><div class='badge bad'>ชื่อผู้ใช้ซ้ำ</div></div>";
    } else {
      $stmt = $pdo->prepare('
        INSERT INTO admins (username, password_hash, display_name, profile_pic, created_at)
        VALUES (?,?,?,?,?)
      ');
      $stmt->execute([
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $display_name ?: null,
        $pic,
        date('c')
      ]);
    }
  }

  // ลบผู้ใช้ — อนุญาตเฉพาะ ADMIN_ID เท่านั้น และห้ามลบ id=ADMIN_ID
  if (($_POST['action'] ?? '') === 'delete') {
    if ($me_id !== $ADMIN_ID) {
      echo "<div class='card'><div class='badge bad'>คุณไม่มีสิทธิ์ลบผู้ใช้</div></div>";
    } else {
      $targetId = (int)$_POST['id'];
      if ($targetId === $ADMIN_ID) {
        echo "<div class='card'><div class='badge bad'>ห้ามลบบัญชีผู้ดูแลหลัก</div></div>";
      } else {
        $stmt = $pdo->prepare('DELETE FROM admins WHERE id=?');
        $stmt->execute([$targetId]);
      }
    }
  }
}

// ดึงรายการผู้ดูแล + ชื่อผู้แก้ไขรหัสล่าสุด (ถ้ามี)
$users = $pdo->query("
  SELECT a.*,
         b.display_name AS changed_by_name,
         b.username     AS changed_by_username
  FROM admins a
  LEFT JOIN admins b ON b.id = a.password_changed_by
  ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
  <h1 style="margin-top:0">ผู้ดูแล</h1>
  <a class='btn' href='../index.php' style='margin:8px 0;display:inline-block'>⬅️ ย้อนกลับหน้าแจ้งซ่อม</a>

  <form class="card" method="post" enctype="multipart/form-data">
    <h3 style="margin-top:0">เพิ่มผู้ดูแล</h3>
    <input type="hidden" name="action" value="create">
    <div class="row">
      <div>
        <label>ชื่อผู้ใช้</label>
        <input name="username" required>
      </div>
      <div>
        <label>ชื่อที่แสดง</label>
        <input name="display_name">
      </div>
    </div>
    <label>รูปโปรไฟล์</label>
    <input type="file" name="profile_pic" accept="image/*">
    <label>รหัสผ่าน</label>
    <input type="password" name="password" required>
    <button class="btn btn-primary" type="submit" style="margin-top:16px">บันทึก</button>
  </form>

  <div class="card" style="margin-top:12px">
    <h3 style="margin-top:0">รายการผู้ดูแล</h3>
    <table class="table">
      <tr>
        <th>ชื่อผู้ใช้</th>
        <th>ชื่อที่แสดง</th>
        <th>สร้างเมื่อ</th>
        <th>แก้รหัสผ่านล่าสุด</th>
        <th>ผู้แก้ไข</th>
        <th style="width:180px;text-align:right">จัดการ</th>
      </tr>
      <?php foreach ($users as $u): ?>
        <?php
          $targetId = (int)$u['id'];
          // สิทธิ์แก้ไข
          $canEdit = ($targetId === $ADMIN_ID)
                      ? ($me_id === $ADMIN_ID)   // ID=1 แก้ได้เฉพาะตัวเอง
                      : ($me_id === $targetId);  // ID อื่นแก้ได้เฉพาะตัวเอง
          // สิทธิ์ลบ
          $canDelete = ($me_id === $ADMIN_ID && $targetId !== $ADMIN_ID);
        ?>
        <tr>
          <td><?= h($u['username']) ?></td>
          <td><?= h($u['display_name'] ?: '-') ?></td>
          <td><?= !empty($u['created_at']) ? h(date('d/m/Y H:i', strtotime($u['created_at']))) : '<span class="muted">-</span>' ?></td>

          <td>
            <?php if(!empty($u['password_changed_at'])): ?>
              <?= h(date('d/m/Y H:i', strtotime($u['password_changed_at']))) ?>
            <?php else: ?>
              <span class="muted">-</span>
            <?php endif; ?>
          </td>

          <td>
            <?php
              if (!empty($u['password_changed_by'])) {
                $name = $u['changed_by_name'] ?: $u['changed_by_username'];
                echo h($name);
              } else {
                echo '<span class="muted">-</span>';
              }
            ?>
          </td>

          <td style="text-align:right">
            <?php if ($canEdit): ?>
              <a class="btn btn-sm btn-edit" href="user_edit.php?id=<?= (int)$u['id'] ?>">แก้ไข</a>
            <?php else: ?>
              <span class="muted" style="font-size:12px">ไม่มีสิทธิ์</span>
            <?php endif; ?>

            <?php if ($canDelete): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('ลบผู้ดูแลคนนี้?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
