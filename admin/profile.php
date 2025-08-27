
<?php require_once __DIR__ . '/header.php'; require_once __DIR__ . '/../inc/functions.php';
$pdo = db();
$me = current_admin();
$msg = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['action']??'')==='update_profile'){
    $pic = $me['profile_pic'];
    if(!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK){
      $pic = save_upload('profile_pic', __DIR__.'/../uploads');
    }
    $stmt = $pdo->prepare('UPDATE admins SET display_name=?, profile_pic=? WHERE id=?');
    $stmt->execute([$_POST['display_name']?:null, $pic, $me['id']]);
    $msg='บันทึกโปรไฟล์แล้ว';
  }
  if(($_POST['action']??'')==='change_password'){
    if($_POST['new_password'] && $_POST['new_password'] === ($_POST['confirm_password'] ?? '')){
      $stmt = $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?');
      $stmt->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $me['id']]);
      $msg='เปลี่ยนรหัสผ่านแล้ว';
    } else { $msg='รหัสผ่านไม่ตรงกัน'; }
  }
  $me = current_admin();
}
?>
<div class="card">
  <h1 style="margin-top:0">โปรไฟล์</h1>
  <?php if($msg): ?><div class="badge"><?= h($msg) ?></div><?php endif; ?>
  <div class="row">
    <form class="card" method="post" enctype="multipart/form-data">
      <h3 style="margin-top:0">ข้อมูลส่วนตัว</h3>
      <input type="hidden" name="action" value="update_profile">
      <label>ชื่อที่แสดง</label><input name="display_name" value="<?= h($me['display_name']) ?>">
      <label>รูปโปรไฟล์</label><input type="file" name="profile_pic" accept="image/*">
      <button class="btn btn-primary" type="submit" style="margin-top:8px">บันทึก</button>
    </form>

    <form class="card" method="post">
      <h3 style="margin-top:0">เปลี่ยนรหัสผ่าน</h3>
      <input type="hidden" name="action" value="change_password">
      <label>รหัสผ่านใหม่</label><input type="password" name="new_password" required>
      <label>ยืนยันรหัสผ่าน</label><input type="password" name="confirm_password" required>
      <button class="btn btn-primary" type="submit" style="margin-top:8px">บันทึก</button>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
