<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo = db();

/* ============== Safety: ensure new admin columns exist ============== */
(function(PDO $pdo){
  $cols = [];
  try {
    $rs = $pdo->query('PRAGMA table_info(admins)');
    if ($rs) foreach ($rs as $r) $cols[] = $r['name'];
  } catch(Throwable $e) {}

  $has = fn($c) => in_array($c, $cols, true);
  if (!$has('password_changed_by'))  $pdo->exec('ALTER TABLE admins ADD COLUMN password_changed_by INTEGER');
  if (!$has('password_changed_at'))  $pdo->exec('ALTER TABLE admins ADD COLUMN password_changed_at TEXT');
})($pdo);

/* ================== Config / Helpers ================== */
$ADMIN_ID = 1;
$me_id    = (int)($_SESSION['admin_id'] ?? 0);

function set_flash($msg, $ok=true){ $_SESSION['flash'] = ['msg'=>$msg,'ok'=>$ok]; }
function pop_flash(){ if(!empty($_SESSION['flash'])){ $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }

/* ================== Handle POST (PRG) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['action'] ?? '';

  // ---- LINE Messaging API: save / clear / test ----
  if ($act === 'save_line_bot') {
    $token = trim($_POST['line_bot_token'] ?? '');
    $to    = trim($_POST['line_bot_to'] ?? '');
    set_setting('line_channel_access_token', $token);
    set_setting('line_target_id', $to);
    set_flash('บันทึกค่า LINE Messaging API แล้ว', true);
    header('Location: users.php#linemsg'); exit;
  }

  if ($act === 'clear_line_bot') {
    set_setting('line_channel_access_token', '');
    set_setting('line_target_id', '');
    set_flash('ล้าง Token/Target เรียบร้อย', true);
    header('Location: users.php#linemsg'); exit;
  }

  if ($act === 'test_line_bot') {
    $token = trim(get_setting('line_channel_access_token', ''));
    $to    = trim(get_setting('line_target_id', ''));
    if (!$token || !$to) {
      set_flash('ยังไม่ได้ตั้งค่า Channel access token หรือ Target ID', false);
    } else {
      $ok = line_push_text('🔔 ทดสอบส่งจากระบบ RepairHub', $to, $token);
      set_flash($ok ? 'ส่งทดสอบสำเร็จ' : 'ส่งไม่สำเร็จ (ตรวจสอบ Token/Target และเชิญบอทเข้าห้อง/กลุ่มแล้วหรือยัง)', $ok);
    }
    header('Location: users.php#linemsg'); exit;
  }

  // ---- Create user ----
  if ($act === 'create') {
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';

    $pic = null;
    if (!empty($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $pic = save_upload('profile_pic', __DIR__ . '/../uploads');
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username=?');
    $chk->execute([$username]);
    if ($chk->fetchColumn() > 0) {
      set_flash('ชื่อผู้ใช้ซ้ำ', false);
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
        date('c'),
      ]);
      set_flash('เพิ่มผู้ดูแลเรียบร้อย', true);
    }
    header('Location: users.php'); exit;
  }

  // ---- Delete user (only super admin; cannot delete super admin) ----
  if ($act === 'delete') {
    if ($me_id !== $ADMIN_ID) {
      set_flash('คุณไม่มีสิทธิ์ลบผู้ใช้', false);
    } else {
      $targetId = (int)$_POST['id'];
      if ($targetId === $ADMIN_ID) {
        set_flash('ห้ามลบบัญชีผู้ดูแลหลัก', false);
      } else {
        $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$targetId]);
        set_flash('ลบผู้ใช้เรียบร้อย', true);
      }
    }
    header('Location: users.php'); exit;
  }
}

/* ================== Fetch users ================== */
$users = $pdo->query("
  SELECT a.*,
         b.display_name AS changed_by_name,
         b.username     AS changed_by_username
  FROM admins a
  LEFT JOIN admins b ON b.id = a.password_changed_by
  ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ================== Read LINE bot settings ================== */
$botToken = trim(get_setting('line_channel_access_token', ''));
$botTo    = trim(get_setting('line_target_id', ''));

/* ===== read flash safely (avoid undefined array key) ===== */
$f = pop_flash();                     // may be [], null, false, or array
if (!is_array($f)) { $f = []; }       // normalize

// รองรับทั้ง ['msg','ok'] และ ['msg','err']
$msg = $f['msg'] ?? ($f['err'] ?? '');
$ok  = array_key_exists('ok', $f)     // ใช้ array_key_exists เพื่อไม่สรุปผิด
    ? (bool)$f['ok']
    : ($msg !== '' && !isset($f['err']));
?>
<div class="card">
  <h1 style="margin-top:0">ผู้ดูแล</h1>
  <a class="btn" href="../index.php" style="margin:8px 0;display:inline-block">⬅️ ย้อนกลับหน้าแจ้งซ่อม</a>

  <?php if ($msg !== ''): ?>
    <div class="alert <?= $ok ? '' : 'bad' ?>" style="margin-bottom:10px">
      <?= h($msg) ?>
    </div>
  <?php endif; ?>


  <!-- ================== LINE Messaging API ================== -->
  <div class="card" id="linemsg" style="margin-bottom:12px">
    <h3 style="margin-top:0">LINE Messaging API</h3>
    <p class="small" style="color:#9ca3af;margin-top:-6px">
      ใช้ <b>Channel access token</b> (long-lived) และ <b>Target ID</b> เพื่อส่ง Push แจ้งเตือน<br>
      Target ID: ผู้ใช้ขึ้นต้น <code>U</code> • กลุ่ม <code>C</code> • ห้อง <code>R</code> (ต้องเชิญบอทเข้ากลุ่ม/ห้องก่อน)
    </p>

    <form method="post" class="row" autocomplete="off" style="row-gap:10px">
      <input type="hidden" name="action" value="save_line_bot">
      <div>
        <label>Channel Access Token</label>
        <input name="line_bot_token"
               value="<?= h($botToken) ?>"
               placeholder="ใส่ token จาก LINE Developers" autocomplete="off">
      </div>
      <div>
        <label>Target ID (U... / C... / R...)</label>
        <input name="line_bot_to"
               value="<?= h($botTo) ?>"
               placeholder="เช่น Cxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off">
      </div>
      <div style="grid-column:1/-1">
        <button class="btn btn-primary" type="submit">บันทึก</button>
      </div>
    </form>

    <div style="display:flex; gap:8px; margin-top:8px">
      <form method="post">
        <input type="hidden" name="action" value="test_line_bot">
        <button class="btn" type="submit">ทดสอบส่งแจ้งเตือน</button>
      </form>
      <form method="post" onsubmit="return confirm('ลบ Token/Target ทั้งหมด?')">
        <input type="hidden" name="action" value="clear_line_bot">
        <button class="btn btn-danger" type="submit">ลบ Token</button>
      </form>
    </div>
  </div>

  <!-- ================== Add user ================== -->
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

  <!-- ================== Users table ================== -->
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
          $targetId  = (int)$u['id'];
          $canEdit   = ($targetId === $ADMIN_ID) ? ($me_id === $ADMIN_ID) : ($me_id === $targetId);
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
