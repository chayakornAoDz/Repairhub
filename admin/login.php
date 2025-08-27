<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
session_start();

/* ถ้า login อยู่แล้ว ไป dashboard */
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

$pdo = db();

/* ----- ตั้งค่า brute-force limit ----- */
$MAX_ATTEMPTS = 5;       // พยายามได้กี่ครั้ง
$LOCKOUT_TIME = 60;      // ล็อคกี่วินาที (60 = 1 นาที)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "login_fail_".$ip;

    /* ตรวจสอบ lockout */
    if (isset($_SESSION[$key]) && $_SESSION[$key]['count'] >= $MAX_ATTEMPTS) {
        $elapsed = time() - $_SESSION[$key]['last'];
        if ($elapsed < $LOCKOUT_TIME) {
            $_SESSION['login_flash'] = [
                'error'  => '',
                'locked' => "ถูกล็อคชั่วคราว โปรดลองใหม่ใน ".($LOCKOUT_TIME - $elapsed)." วินาที"
            ];
            header('Location: login.php'); exit;
        } else {
            unset($_SESSION[$key]); // reset
        }
    }

    /* ตรวจสอบ user */
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($password, $row['password_hash'])) {
        $_SESSION['admin_id'] = $row['id'];
        session_regenerate_id(true);
        unset($_SESSION[$key]); // clear fail
        header('Location: dashboard.php'); exit;
    } else {
        // fail
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count'=>1,'last'=>time()];
        } else {
            $_SESSION[$key]['count']++;
            $_SESSION[$key]['last']=time();
        }
        $_SESSION['login_flash'] = [
            'error'  => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
            'locked' => ''
        ];
        header('Location: login.php'); exit;
    }
}

/* ----- Flash message ----- */
$flash = $_SESSION['login_flash'] ?? ['error'=>'','locked'=>''];
unset($_SESSION['login_flash']);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบผู้ดูแล • RepairHub Lite</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">

  <div class="login-wrap">
    <form class="card auth-card" method="post" autocomplete="off">
      <div class="brand">
        <div class="brand-icon">🛠️</div>
        <div class="brand-text">
          <div class="brand-title">RepairHub <span class="brand-lite">Lite</span></div>
          <div class="brand-sub">Admin Console</div>
        </div>
      </div>

      <h1 class="auth-title">เข้าสู่ระบบผู้ดูแล</h1>

      <?php if($flash['locked']): ?>
        <div class="alert bad"><?= h($flash['locked']) ?></div>
      <?php elseif($flash['error']): ?>
        <div class="alert bad"><?= h($flash['error']) ?></div>
      <?php endif; ?>

      <label>ชื่อผู้ใช้</label>
      <div class="input-wrap">
        <span class="input-ico">👤</span>
        <input name="username" required placeholder="Username">
      </div>

      <label>รหัสผ่าน</label>
      <div class="input-wrap">
        <span class="input-ico">🔒</span>
        <input type="password" name="password" required placeholder="••••••••">
      </div>

      <button class="btn btn-primary auth-submit" type="submit"
              onclick="this.disabled=true;this.form.submit();">เข้าสู่ระบบ</button>

      <div class="auth-foot">
        <a href="../index.php" class="link-muted">← กลับไปหน้าแจ้งซ่อม</a>
        <span class="muted">สำหรับ Admin SPB ENGINEERING</span>
      </div>
    </form>
  </div>

</body>
</html>
