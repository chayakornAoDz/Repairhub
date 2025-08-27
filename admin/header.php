
<?php require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/functions.php'; require_login(); $me = current_admin(); ?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>แผงควบคุม - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<script defer src="../assets/js/app.js"></script>
</head><body>
<div class="grid">
  <aside class="sidebar">
    <div class="logo">🛠️ <?= h(APP_NAME) ?></div>
    <div style="display:flex;gap:10px;align-items:center;margin:8px 0 16px">
      <img src="<?= h($me['profile_pic'] ? '../uploads/'.$me['profile_pic'] : 'https://api.dicebear.com/9.x/identicon/svg?seed='.urlencode($me['username'])) ?>" alt="avatar" style="width:38px;height:38px;border-radius:50%;border:1px solid #334155;background:#0b132a">
      <div>
        <div style="font-weight:600"><?= h($me['display_name'] ?: $me['username']) ?></div>
        <a href="profile.php" style="font-size:12px;color:#9ca3af">โปรไฟล์</a>
      </div>
    </div>
    <?php
  $cur = basename($_SERVER['PHP_SELF']);
  $isInv = ($cur === 'inventory.php');
      ?>
      <nav class="menu">
        <a href="dashboard.php" class="<?= $cur==='dashboard.php'?'active':'' ?>">📊 แดชบอร์ด</a>
        <a href="requests.php"  class="<?= $cur==='requests.php'?'active':'' ?>">📝 งานซ่อม</a>

        <!-- สต็อก + เมนูย่อย -->
        <div class="menu-item has-sub <?= $isInv?'active':'' ?>">
          <a href="inventory.php">📦 สต็อก</a>
          <div class="sub">
            <a href="inventory_add_edit.php">เพิ่มสินค้า รับเข้าออก ปรับยอด</a>
            <a href="inventory_movements.php">รายงานความเคลื่อนไหวสต็อก</a>
            <!-- <a href="inventory.php#items">📋 รายการสินค้า</a> -->
          </div>
        </div>

        <a href="loans.php"    class="<?= $cur==='loans.php'?'active':'' ?>">🔄 ยืมอุปกรณ์</a>
        <a href="reports.php"  class="<?= $cur==='reports.php'?'active':'' ?>">📑 รายงาน</a>
        <a href="users.php"    class="<?= $cur==='users.php'?'active':'' ?>">👤 ผู้ดูแล</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
      </nav>

  </aside>
  <main style="padding:20px">
