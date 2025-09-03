<?php
// admin/header.php

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_login();
$me = current_admin();

/* ===== Path helpers =====
 * ตัวอย่าง:
 *   /repairhub/admin                         -> $SCRIPT_DIR
 *   /repairhub/admin/cctv_bundle             -> $SCRIPT_DIR
 *   /repairhub                               -> $APP_ROOT
 *   /repairhub/admin                         -> $ADMIN_BASE
 */
$SCRIPT_DIR = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_ROOT   = preg_replace('#/admin(?:/.*)?$#', '', $SCRIPT_DIR);
$ADMIN_BASE = $APP_ROOT . '/admin';

// สำหรับ active state
$curFile  = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$reqUri   = str_replace('\\','/', $_SERVER['REQUEST_URI']);
$isCctv   = (strpos($reqUri, '/admin/cctv_bundle/cctv_checklist_') !== false);
$isInv    = ($curFile === 'inventory.php');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(APP_NAME) ?> - แผงควบคุม</title>

  <!-- ให้ลิงก์ relative ในเมนู/ส่วนต่าง ๆ กลับไปที่ /repairhub/admin/ เสมอ -->
  <base href="<?= htmlspecialchars($ADMIN_BASE) ?>/">

  <!-- CSS/JS หลักของโปรเจ็กต์ (อยู่นอก admin) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($APP_ROOT) ?>/assets/css/style.css">
  <script defer src="<?= htmlspecialchars($APP_ROOT) ?>/assets/js/app.js"></script>
</head>
<body>
<button class="nav-trigger" type="button" aria-label="เปิดเมนู" aria-expanded="false">☰</button>
<div class="nav-overlay"></div>

<div class="grid">
  <aside class="sidebar" id="sidebar">
    <!-- โลโก้ -->
     <a href="../index.php">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;">
      <div class="logo" style="display:flex;align-items:center;gap:8px;">
        <span class="ico">🛠️ </span>
        <span class="collapse-hide" style="font-weight:700;letter-spacing:.3px"><?= h(APP_NAME) ?></span>
      </div>
    </div>
    </a>

    <!-- ผู้ใช้ -->
    <div class="userbox" style="display:flex;gap:10px;align-items:center;margin:8px 0 16px">
      <img
        src="<?= h($me['profile_pic'] ? $APP_ROOT.'/uploads/'.$me['profile_pic'] : 'https://api.dicebear.com/9.x/identicon/svg?seed='.urlencode($me['username'])) ?>"
        alt="avatar"
        style="width:38px;height:38px;border-radius:50%;border:1px solid #334155;background:#0b132a">
      <div class="name collapse-hide">
        <div style="font-weight:600"><?= h($me['display_name'] ?: $me['username']) ?></div>
        <a href="profile.php" style="font-size:12px;color:#9ca3af">โปรไฟล์</a>
      </div>
    </div>

    <!-- เมนู -->
    <nav class="menu">
      <a href="dashboard.php" class="<?= $curFile==='dashboard.php'?'active':'' ?>" title="แดชบอร์ด">
        <span class="ico">📊</span><span class="lbl collapse-hide">แดชบอร์ด</span>
      </a>

      <!-- งานซ่อม -->
      <div class="menu-item has-sub <?= $curFile==='requests.php'?'active':'' ?>">
        <a href="requests.php" title="งานซ่อม">
          <span class="ico">📝</span><span class="lbl collapse-hide">งานซ่อม</span>
        </a>
        <div class="sub collapse-hide">
          <a href="requests.php?status=งานซ่อม">งานซ่อม</a>
          <a href="requestsnew.php?status=งานใหม่">งานใหม่</a>
          <a href="reports.php?status=report">รายงาน</a>
        </div>
      </div>

      <!-- ทรัพย์สิน -->
      <div class="menu-item has-sub <?= $isInv?'active':'' ?>">
        <a href="inventory.php" title="ทรัพย์สิน">
          <span class="ico">📦</span><span class="lbl collapse-hide">ทรัพย์สิน</span>
        </a>
        <div class="sub collapse-hide">
          <a href="inventory.php">รายการทรัพย์สิน</a>
          <a href="inventory_add_edit.php">เพิ่มทรัพย์สิน รับเข้าออก ปรับยอด</a>
          <a href="inventory_movements.php">รายงานความเคลื่อนไหวทรัพย์สิน</a>
        </div>
      </div>

      <a href="loans.php" class="<?= $curFile==='loans.php'?'active':'' ?>" title="ยืมอุปกรณ์">
        <span class="ico">🔄</span><span class="lbl collapse-hide">ยืมอุปกรณ์</span>
      </a>

      <!-- CCTV Checklist -->
      <div class="menu-item has-sub <?= $isCctv?'active':'' ?>">
        <a href="cctv_bundle/cctv_index.php" title="CCTV-CheckList">
          <span class="ico">📹</span><span class="lbl collapse-hide">CCTV</span>
        </a>
        <div class="sub collapse-hide">
          <a href="cctv_bundle/cctv_index.php">CCTV-Plan</a>
          <a href="cctv_bundle/cctv_checklist_Hik.php">CCTV-Hikvision</a>
          <a href="cctv_bundle/cctv_checklist_Dahua.php">CCTV-Dahua</a>
        </div>
      </div>

      <a href="users.php" class="<?= $curFile==='users.php'?'active':'' ?>" title="ผู้ดูแล">
        <span class="ico">👤</span><span class="lbl collapse-hide">ผู้ดูแล</span>
      </a>

      <a href="logout.php" title="ออกจากระบบ">
        <span class="ico">🚪</span><span class="lbl collapse-hide">ออกจากระบบ</span>
      </a>
    </nav>
  </aside>

  <!-- หน้าหลักของแต่ละเพจจะเริ่มใน <main> และปิดใน footer.php -->
  <main style="padding:20px">
