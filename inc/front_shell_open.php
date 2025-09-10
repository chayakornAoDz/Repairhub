<?php
// inc/front_shell_open.php
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($page_title) ? $page_title : 'RepairHub Lite' ?></title>

  <!-- Sidebar shell -->
  <link rel="stylesheet" href="assets/css/rh-sidebar.css">

  <!-- YOUR site styles (สำคัญสุดๆ) -->
  <link rel="stylesheet" href="assets/css/style.css">
  <script defer src="assets/js/app.js"></script>

  <?= $extra_head_html ?? '' ?>
</head>
<body class="rh">
  <div class="rh-backdrop" aria-hidden="true"></div>
  <div class="rh-layout">
    <aside class="rh-sidebar" aria-label="Sidebar Navigation">
      <a href="index.php" class="rh-brand" aria-label="RepairHub Lite Home">
        <span class="rh-logo" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a5 5 0 0 1 2.6-1.4 5 5 0 1 0 6.3 6.3 5 5 0 0 1-6.9 4.9l-7.6 7.6a2 2 0 0 1-2.8-2.8l7.6-7.6a5 5 0 0 1 .8-6.9Z"/>
          </svg>
        </span>
        <strong class="rh-hide-when-collapsed">RepairHub Lite</strong>
      </a>

        <nav class="rh-nav" role="navigation">
        <!-- 1) ส่งคำแจ้งซ่อม -->
        <a href="index.php" class="<?= ($page??'')==='home' ? 'is-active' : '' ; ?>" id="rh-mobile-first-focus" title="ส่งคำแจ้งซ่อม">
        <!-- ส่งคำแจ้งซ่อม (home/repair) -->
          <svg viewBox="0 0 24 24" width="20" height="20"
              fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 11l9-8 9 8v9a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-5H9v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          </svg>
        <span class="rh-link-label">ส่งคำแจ้งซ่อม</span>
        </a>

        <!-- 2) ระบบเบิกของ -->
        <a href="issue.php" class="<?= ($page??'')==='issue' ? 'is-active' : '' ; ?>" title="ระบบเบิกของ">
        <!-- package outbound icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-6 9 6-9 6-9-6z"/><path d="M3 9v6l9 6 9-6V9"/><path d="M9 3v6l6 3V6"/>
        </svg>
        <span class="rh-link-label">ระบบเบิกของ</span>
        </a>

        <!-- 3) ระบบยืมของ -->
        <a href="user_loan.php" class="<?= ($page??'')==='loan' ? 'is-active' : '' ; ?>" title="ระบบยืมของ">
        <!-- hand/box icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 8V6a2 2 0 0 0-2-2h-4"/><path d="M3 8V6a2 2 0 0 1 2-2h4"/><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M7 12h10"/>
        </svg>
        <span class="rh-link-label">ระบบยืมของ</span>
        </a>

        <div class="rh-divider"></div>

        <!-- ลิงก์ไปหน้า Admin ตามเดิม -->
        <a href="./admin/login.php" class="<?= ($page??'')==='admin' ? 'is-active' : '' ; ?>" title="ระบบ Admin">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <span class="rh-link-label">ระบบ Admin</span>
        </a>
      </nav>
    </aside>

    <main class="rh-main">
      <header class="rh-topbar">
        <button id="rh-toggle-mobile" class="rh-btn rh-mobile-toggle" type="button" aria-label="เปิดเมนู">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div style="display:flex;align-items:center;gap:10px;">
          <strong style="color:#eaf2ff;">แจ้งซ่อมระบบ - RepairHub Lite</strong>
        </div>
        <!-- <div style="display:flex;align-items:center;gap:8px;">
          <a href="track.php" class="rh-btn">ค้นหา Ticket</a>
          <button id="rh-toggle-desktop" class="rh-btn rh-desktop-toggle" type="button" title="ยุบ/ขยายเมนู">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M8 8l-4 4 4 4"/></svg>
          </button>
          <button id="rh-close-mobile" class="rh-btn rh-mobile-toggle" type="button" aria-label="ปิดเมนู">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div> -->
      

      <section class="rh-content">
      
      <style>
        /* ป้องกันกรอบแดงจาก focus/invalid คลุมทั้งการ์ด */
        .card:focus,
        .card:focus-within,
        .hero:focus,
        .hero:focus-within,
        form:focus,
        form:focus-within {
          outline: none !important;
          box-shadow: none !important;
        }

        /* ไม่ให้ browser ใส่กรอบเงาแดงอัตโนมัติขณะกรอก (จะใช้สไตล์ของเราแทน) */
        input:invalid, select:invalid, textarea:invalid {
          box-shadow: none !important;
          outline: none !important;
        }

        /* โฟกัสอินพุตให้เป็นสีหลักของธีม (แทนกรอบแดง) */
        input:focus, select:focus, textarea:focus, button:focus {
          outline: none;
          box-shadow: 0 0 0 2px var(--primary, #14b8c4);
        }

        /* ลดไฮไลต์เวลาแตะบนมือถือ (บางทีดูเป็นสี่เหลี่ยมโทนแดง/ส้ม) */
        * { -webkit-tap-highlight-color: transparent; }

      </style>
    </header>