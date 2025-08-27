
<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';
session_start();
$pdo = db();

// รับ id จาก GET หรือ POST
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// flash message (PRG)
$flash = $_SESSION['flash'] ?? ['msg'=>'','err'=>''];
$_SESSION['flash'] = ['msg'=>'','err'=>'']; // reset

// โหลดข้อมูลผู้ดูแล
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id=?');
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$u){ echo '<div class="card">ไม่พบผู้ดูแล</div>'; require __DIR__.'/footer.php'; exit; }

$msg = $flash['msg'] ?? '';
$err = $flash['err'] ?? '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  // บันทึกโปรไฟล์
  if(($_POST['action'] ?? '') === 'save_profile'){
    $pic = $u['profile_pic'];
    if(!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error']===UPLOAD_ERR_OK){
      if($pic && file_exists(__DIR__.'/../uploads/'.$pic)){
        @unlink(__DIR__.'/../uploads/'.$pic);
      }
      $pic = save_upload('profile_pic', __DIR__.'/../uploads');
    }
    $stmt = $pdo->prepare('UPDATE admins SET display_name=?, profile_pic=? WHERE id=?');
    $stmt->execute([$_POST['display_name']?:null, $pic, $id]);
    $_SESSION['flash'] = ['msg'=>'บันทึกโปรไฟล์แล้ว','err'=>''];
    header('Location: user_edit.php?id='.$id); exit;
  }

  // เปลี่ยนรหัสผ่าน
  if(($_POST['action'] ?? '') === 'change_password'){
    $p1 = $_POST['new_password'] ?? '';
    $p2 = $_POST['confirm_password'] ?? '';
    if($p1 === '' || $p2 === ''){
      $_SESSION['flash'] = ['msg'=>'','err'=>'กรุณากรอกรหัสผ่านให้ครบทั้งสองช่อง'];
    } elseif($p1 !== $p2){
      $_SESSION['flash'] = ['msg'=>'','err'=>'รหัสผ่านไม่ตรงกัน'];
    } elseif(strlen($p1) < 6){
      $_SESSION['flash'] = ['msg':'','err'=>'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'];
    } else {
      $stmt = $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?');
      $stmt->execute([password_hash($p1, PASSWORD_DEFAULT), $id]);
      $_SESSION['flash'] = ['msg'=>'เปลี่ยนรหัสผ่านแล้ว','err'=>''];
    }
    header('Location: user_edit.php?id='.$id); exit;
  }
}
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h1 style="margin:0">แก้ไขผู้ดูแล</h1>
    <div style="display:flex;gap:8px">
      <a class="btn" href="users.php">← กลับรายการผู้ดูแล</a>
      <a class="btn" href="../index.php">← กลับหน้าแจ้งซ่อม</a>
    </div>
  </div>
  <?php if($msg): ?><div class="badge" style="margin-top:10px"><?= h($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="badge bad" style="margin-top:10px"><?= h($err) ?></div><?php endif; ?>
  <div class="row">
    <form class="card" method="post" enctype="multipart/form-data" action="user_edit.php?id=<?= (int)$id ?>">
      <h3 style="margin-top:0">ข้อมูลโปรไฟล์</h3>
      <input type="hidden" name="action" value="save_profile">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <p><b>ชื่อผู้ใช้:</b> <?= h($u['username']) ?></p>
      <label>ชื่อที่แสดง</label>
      <input name="display_name" value="<?= h($u['display_name']) ?>">
      <label>รูปโปรไฟล์ (อัปโหลดเพื่อแทนที่)</label>
      <input type="file" name="profile_pic" accept="image/*">
      <div style="margin-top:8px">
        <button class="btn btn-primary" type="submit">บันทึกโปรไฟล์</button>
      </div>
    </form>

    <form class="card" method="post" action="user_edit.php?id=<?= (int)$id ?>" onsubmit="return validatePasswordForm();">
      <h3 style="margin-top:0">เปลี่ยนรหัสผ่าน</h3>
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <label>รหัสผ่านใหม่</label>
      <input type="password" name="new_password" id="new_password" required>
      <label>ยืนยันรหัสผ่าน</label>
      <input type="password" name="confirm_password" id="confirm_password" required>
      <div style="margin-top:8px">
        <button class="btn btn-primary" type="submit">บันทึกรหัสผ่าน</button>
      </div>
    </form>
  </div>
</div>
<script>
function validatePasswordForm(){
  var p1 = document.getElementById('new_password').value.trim();
  var p2 = document.getElementById('confirm_password').value.trim();
  if(!p1 || !p2){
    alert('กรุณากรอกรหัสผ่านให้ครบทั้งสองช่อง');
    return false;
  }
  if(p1 !== p2){
    alert('รหัสผ่านทั้งสองช่องไม่ตรงกัน');
    return false;
  }
  if(p1.length < 6){
    alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
    return false;
  }
  return true;
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
