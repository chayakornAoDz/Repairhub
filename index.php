<?php require_once __DIR__ . '/inc/functions.php'; session_start(); $_SESSION['csrf']=bin2hex(random_bytes(16)); ?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<script defer src="assets/js/app.js"></script>
</head><body>
<div class="container">

  <!-- HERO (หัวเรื่อง + โลโก้) -->
  <div class="card hero shimmer">
    <div class="hero-left">
      <h1 class="hero-title">🛠️ แจ้งซ่อมระบบ - <?= h(APP_NAME) ?></h1>
      <p class="hero-sub">ไม่ต้องล็อคอิน ผู้ใช้ทั่วไปสามารถแจ้งซ่อมและรับหมายเลข Ticket เพื่อติดตามได้</p>
    </div>

    <!-- โลโก้: ใส่ไฟล์ที่ assets/img/logo.jpg (หรือเปลี่ยน path ด้านล่าง) -->
    <div class="brand-box">
      <img class="brand-img" src="assets/img/logoSPB_.png" alt="Logo"
           onerror="this.style.display='none'">
    </div>
  </div>

  <div class="row" style="margin-top:16px">
    <form class="card" method="post" action="submit.php" enctype="multipart/form-data">
      <h2 style="margin-top:0">ส่งคำขอซ่อม</h2>
      <div class="row">
        <div>
          <label>ชื่อผู้แจ้ง</label>
          <input name="name" required>
        </div>
        <div>
          <label>ช่องทางติดต่อ (เบอร์/อีเมล/ไลน์)</label>
          <input name="contact" required>
        </div>
      </div>

      <div class="row">
        <div>
          <label>แผนก/หน่วยงาน</label>
          <input name="department">
        </div>
        <div>
          <label>สถานที่</label>
          <input name="location">
        </div>
      </div>

      <div class="row" style="margin-top:16px">
        <div>
          <label>ประเภทปัญหา</label>
          <select name="category" required>
            <option value="">-- เลือกประเภท --</option>
            <option>ไฟฟ้า</option>
            <option>อินเทอร์เน็ต</option>
            <option>โทรศัพท์</option>
            <option>คอมพิวเตอร์</option>
            <option>เครื่องพิมพ์</option>
            <option>งานช่าง</option>
            <option>ซอฟต์แวร์</option>
            <option>ฮาร์ดแวร์</option>
            <option>กล้อง CCTV</option>
            <option>อื่น ๆ</option>
          </select>
        </div>
        <div>
          <label>ความสำคัญ</label>
          <select name="priority">
            <option>ปกติ</option>
            <option>เร่งด่วน</option>
            <option>วิกฤต</option>
          </select>
        </div>
      </div>

      <div>
        <label>รายละเอียด</label>
        <textarea class="auto" name="description" rows="4" required></textarea>
      </div>

      <div>
        <label>แนบรูป/ไฟล์ (ไม่บังคับ)</label>
        <input type="file" name="attachment" accept="image/*,.pdf,.doc,.docx,.xlsx,.xls">
      </div>

      <input type="text" name="website" style="display:none"> <!-- honeypot -->
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button class="btn" type="reset">ล้างข้อมูล</button>
        <button class="btn btn-primary" type="submit">ส่งคำขอ ➜</button>
      </div>
      <p class="muted" style="margin:8px 0 0">หลังส่ง ระบบจะแสดงหมายเลข Ticket เพื่อใช้ติดตาม</p>
    </form>

    <div class="card">
      <h2 style="margin-top:0">ติดตามงาน</h2>
      <form method="get" action="track.php" class="row" style="align-items:end">
        <div>
          <label>หมายเลข Ticket</label>
          <input name="t" placeholder="เช่น RH-20250101-0001" required>
        </div>
        <div>
          <button class="btn btn-primary" type="submit">ค้นหา</button>
        </div>
      </form>
      <div class="card" style="margin-top:12px">
        <h3 style="margin-top:0">สำหรับผู้ดูแล</h3>
        <p class="muted">เข้าสู่ระบบเพื่อจัดการงานซ่อม, สต็อก และรายงาน</p>
        <a class="btn" href="admin/login.php">ระบบ Admin</a>
      </div>

      <div class="card" style="margin-top:12px">
        <h2>งานล่าสุด</h2>
        <div id="recentFeed" style="min-height:220px"></div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/recent_feed.js"></script>
</body>
</html>
