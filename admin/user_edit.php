<?php
// admin/user_edit.php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo   = db();                         // PDO (SQLite) จาก inc/functions.php
$me_id = (int)($_SESSION['admin_id'] ?? 0);  // ผู้ที่กำลังล็อกอินอยู่ตอนนี้
$id    = (int)($_GET['id'] ?? ($_POST['id'] ?? 0)); // ไอดีของบัญชีที่กำลังแก้ไข

// โหลดข้อมูลผู้ใช้
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
  echo '<div class="card">ไม่พบผู้ดูแล</div>';
  require __DIR__ . '/footer.php';
  exit;
}

// flash (ข้อความผลลัพธ์)
$flash = $_SESSION['flash'] ?? ['msg' => '', 'err' => ''];
$_SESSION['flash'] = ['msg' => '', 'err' => '']; // เคลียร์ไว้รอบถัดไป

// ===== ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // บันทึกโปรไฟล์ (ชื่อที่แสดง / รูปโปรไฟล์)
  if (($_POST['action'] ?? '') === 'save_profile') {
    $display = trim($_POST['display_name'] ?? '');
    $pic     = $u['profile_pic'];

    // ถ้ามีอัปโหลดรูปใหม่
    if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      // ลบไฟล์เก่า (ถ้ามี)
      $old = __DIR__ . '/../uploads/' . $pic;
      if ($pic && is_file($old)) { @unlink($old); }

      // ฟังก์ชันช่วยอัปโหลด (คาดว่าอยู่ใน functions.php)
      if (function_exists('save_upload')) {
        $pic = save_upload('profile_pic', __DIR__ . '/../uploads');   // คืนชื่อไฟล์
      } else {
        // fallback ง่าย ๆ
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $fname = 'avatar_' . $id . '_' . time() . '.' . strtolower($ext);
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $fname);
        $pic = $fname;
      }
    }

    $stmt = $pdo->prepare('UPDATE admins SET display_name = ?, profile_pic = ? WHERE id = ?');
    $stmt->execute([$display ?: null, $pic, $id]);

    $_SESSION['flash'] = ['msg' => 'บันทึกโปรไฟล์เรียบร้อย', 'err' => ''];
    header('Location: user_edit.php?id=' . $id);
    exit;
  }

  // เปลี่ยนรหัสผ่าน (ต้องยืนยันรหัสเดิม และต้องเป็นเจ้าของบัญชีเท่านั้น)
  if (($_POST['action'] ?? '') === 'change_password') {

  if ($me_id !== $id) {
    $_SESSION['flash'] = ['msg' => '', 'err' => 'อนุญาตเฉพาะเจ้าของบัญชีเท่านั้นในการเปลี่ยนรหัสผ่าน'];
    header('Location: user_edit.php?id=' . $id);
    exit;
  }

  $current = $_POST['current_password'] ?? '';
  $p1      = $_POST['new_password'] ?? '';
  $p2      = $_POST['confirm_password'] ?? '';

  if ($current === '' || $p1 === '' || $p2 === '') {
    $_SESSION['flash'] = ['msg' => '', 'err' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง'];
  } elseif (!password_verify($current, $u['password_hash'])) {
    $_SESSION['flash'] = ['msg' => '', 'err' => 'รหัสผ่านเดิมไม่ถูกต้อง'];
  } elseif ($p1 !== $p2) {
    $_SESSION['flash'] = ['msg' => '', 'err' => 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน'];
  } elseif (mb_strlen($p1) < 6) {
    $_SESSION['flash'] = ['msg' => '', 'err' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร'];
  } else {
    $hash = password_hash($p1, PASSWORD_DEFAULT);

    // 👉 บันทึกเวลาที่แก้ (UTC หรือ local ก็ได้) และผู้ที่แก้ (me_id)
    $changedAt = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
      'UPDATE admins 
         SET password_hash = ?, 
             password_changed_at = ?, 
             password_changed_by = ? 
       WHERE id = ?'
    );
    $stmt->execute([$hash, $changedAt, $me_id, $id]);

    $_SESSION['flash'] = ['msg' => 'เปลี่ยนรหัสผ่านเรียบร้อย', 'err' => ''];
  }

  header('Location: user_edit.php?id=' . $id);
  exit;
}
}

// แกะ flash
$msg = $flash['msg'] ?? '';
$err = $flash['err'] ?? '';
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h1 style="margin:0">แก้ไขผู้ดูแล</h1>
    <div style="display:flex;gap:8px">
      <a class="btn" href="users.php">← กลับรายการผู้ดูแล</a>
      <a class="btn" href="../index.php">← กลับหน้าแจ้งซ่อม</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="badge" style="margin-top:10px"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="badge bad" style="margin-top:10px"><?= h($err) ?></div><?php endif; ?>

  <div class="row" style="margin-top:12px">
    <!-- ฟอร์มโปรไฟล์ -->
    <form class="card" method="post" enctype="multipart/form-data" action="user_edit.php?id=<?= (int)$id ?>">
      <h3 style="margin-top:0">ข้อมูลโปรไฟล์</h3>
      <input type="hidden" name="action" value="save_profile">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <label>ชื่อผู้ใช้</label>
      <input value="<?= h($u['username']) ?>" disabled>

      <label>ชื่อที่แสดง</label>
      <input name="display_name" value="<?= h($u['display_name']) ?>" placeholder="เช่น AdminIT">

      <label>รูปโปรไฟล์ (อัปโหลดเพื่อแทนที่)</label>
      <input type="file" name="profile_pic" accept="image/*">

      <?php if(!empty($u['profile_pic'])): ?>
        <div style="margin-top:8px" class="muted">ไฟล์ปัจจุบัน: <?= h($u['profile_pic']) ?></div>
      <?php endif; ?>

      <div style="margin-top:10px">
        <button class="btn btn-primary" type="submit">บันทึกโปรไฟล์</button>
      </div>
    </form>

    <!-- ฟอร์มเปลี่ยนรหัสผ่าน -->
    <form class="card" method="post" action="user_edit.php?id=<?= (int)$id ?>" onsubmit="return validatePasswordForm();">
      <h3 style="margin-top:0">เปลี่ยนรหัสผ่าน</h3>
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <label>รหัสผ่านเดิม</label>
      <input type="password" name="current_password" id="current_password" placeholder="กรอกรหัสเดิม"
             <?= $me_id !== $id ? 'disabled' : 'required' ?>>

      <?php if ($me_id !== $id): ?>
        <div class="muted" style="margin:6px 0">* เปลี่ยนรหัสผ่านได้เฉพาะเจ้าของบัญชีเท่านั้น</div>
      <?php endif; ?>

      <label>รหัสผ่านใหม่</label>
      <input type="password" name="new_password" id="new_password"
             <?= $me_id !== $id ? 'disabled' : 'required' ?>>

      <label>ยืนยันรหัสผ่านใหม่</label>
      <input type="password" name="confirm_password" id="confirm_password"
             <?= $me_id !== $id ? 'disabled' : 'required' ?>>

      <div style="margin-top:10px">
        <button class="btn btn-primary" type="submit" <?= $me_id !== $id ? 'disabled' : '' ?>>บันทึกรหัสผ่าน</button>
      </div>
    </form>
  </div>
</div>

<script>
function validatePasswordForm(){
  // กันกดส่งถ้าไม่ใช่เจ้าของบัญชี
  var meIsOwner = <?= ($me_id === $id) ? 'true' : 'false' ?>;
  if(!meIsOwner) return false;

  var cur = document.getElementById('current_password').value.trim();
  var p1  = document.getElementById('new_password').value.trim();
  var p2  = document.getElementById('confirm_password').value.trim();

  if(!cur || !p1 || !p2){
    alert('กรุณากรอกข้อมูลให้ครบทุกช่อง');
    return false;
  }
  if(p1 !== p2){
    alert('รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน');
    return false;
  }
  if(p1.length < 6){
    alert('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
    return false;
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
