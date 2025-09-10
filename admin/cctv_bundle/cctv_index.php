<?php
// admin/cctv_bundle/cctv_index.php
// ค้นหาตารางเช็คกล้อง + อัปโหลดภาพผัง CCTV (Preview)

date_default_timezone_set('Asia/Bangkok');

/* ===== Resolve base paths/urls ===== */
$ADMIN_DIR = dirname(__DIR__);      // .../repairhub/admin
$ROOT_DIR  = dirname(__DIR__, 2);   // .../repairhub (project root)

/* login & helpers */
require_once $ROOT_DIR . '/inc/auth.php';
require_once $ROOT_DIR . '/inc/functions.php';
require_login();

/* URL ของโฟลเดอร์ bundle (สำหรับลิงก์เปิด Hik/Dahua) */
$BUNDLE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // /repairhub/admin/cctv_bundle หรือ /admin/cctv_bundle

/* สร้าง ROOT_URL แบบไดนามิก (รองรับทั้งรากโดเมน และโฟลเดอร์ย่อย) */
$docroot  = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');
$rootPath = rtrim(str_replace('\\','/', realpath($ROOT_DIR)), '/');
$rootUrl  = trim(str_replace($docroot, '', $rootPath), '/');   // "" หรือ "repairhub"
$ROOT_URL = $rootUrl === '' ? '' : ('/' . $rootUrl);

/* โฟลเดอร์อัปโหลด (ไฟล์ระบบ & เว็บ) */
$UPLOAD_DIR = $ROOT_DIR . '/uploads';
$UPLOAD_URL = $ROOT_URL . '/uploads';

/* ===== Month / Year (with safe defaults) ===== */
$months_th = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$y_now = (int)date('Y');
$m_now = (int)date('n');

$m = isset($_GET['m']) && $_GET['m'] !== '' ? max(1, min(12, (int)$_GET['m'])) : $m_now;
$y = isset($_GET['y']) && $_GET['y'] !== '' ? (int)$_GET['y'] : $y_now;

/* ===== Upload plan image (run BEFORE any output) ===== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['plan'])) {
    if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0777, true);

    if (is_uploaded_file($_FILES['plan']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['plan']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp','svg'];
        if (!in_array($ext, $allow)) $ext = 'jpg';

        // ลบไฟล์เก่า (ถ้ามี)
        foreach (glob($UPLOAD_DIR . '/cctv_plan.*') as $old) @unlink($old);
        // เซฟไฟล์ใหม่
        $dest = $UPLOAD_DIR . '/cctv_plan.' . $ext;
        @move_uploaded_file($_FILES['plan']['tmp_name'], $dest);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?uploaded=1&m=' . $m . '&y=' . $y);
        exit;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?err=1&m=' . $m . '&y=' . $y);
        exit;
    }
}

/* ===== After this line it's safe to output HTML ===== */
require_once $ADMIN_DIR . '/header.php';

/* preview url (if exists) */
$existing = glob($UPLOAD_DIR . '/cctv_plan.*');
$planUrl  = $existing ? ($UPLOAD_URL . '/' . basename($existing[0]) . '?v=' . time()) : '';
?>
<style>
  .cctv-landing{max-width:1200px;margin:0 auto}
  .cctv-landing .cards{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:900px){.cctv-landing .cards{grid-template-columns:1fr}}
  .cctv-landing .card h3{margin:0 0 8px}
  .cctv-landing .actions{display:flex;gap:8px;flex-wrap:wrap}
  .plan-box{max-height:70vh;overflow:auto;border:1px solid #1f2937;border-radius:12px;background:#0b1222;padding:8px}
  .plan-box img{width:100%;height:auto;display:block;object-fit:contain}
</style>

<div class="cctv-landing">
  <h1>ค้นหาตารางเช็คกล้อง</h1>

  <div class="card" style="margin-bottom:14px">
    <div class="row" style="align-items:end;gap:12px;flex-wrap:wrap">
      <label>
        เดือน
        <select id="m" class="btn" style="padding:8px 10px">
          <?php foreach($months_th as $i=>$name){ $v=$i+1; ?>
            <option value="<?= $v ?>" <?= ($v === ($m ?? $m_now)) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php } ?>
        </select>
      </label>

      <label>
        ปี
        <select id="y" class="btn" style="padding:8px 10px">
          <?php for($yy=$y_now-3;$yy<=$y_now+3;$yy++){ ?>
            <option value="<?= $yy ?>" <?= ($yy === ($y ?? $y_now)) ? 'selected' : '' ?>>
              <?= $yy + 543 ?>
            </option>
          <?php } ?>
        </select>
      </label>

      <div class="actions">
        <a id="goHik" class="btn btn-primary"
           href="<?= h($BUNDLE_URL) ?>/cctv_checklist_Hik.php?m=<?= $m ?>&y=<?= $y ?>">
          เปิด Hikvision
        </a>

        <a id="goDahua" class="btn btn-primary"
           href="<?= h($BUNDLE_URL) ?>/cctv_checklist_Dahua.php?m=<?= $m ?>&y=<?= $y ?>">
          เปิด Dahua
        </a>
      </div>
    </div>

    <?php if(isset($_GET['uploaded'])): ?>
      <div class="alert" style="margin-top:10px">อัปโหลดสำเร็จ</div>
    <?php elseif(isset($_GET['err'])): ?>
      <div class="alert bad" style="margin-top:10px">อัปโหลดไม่สำเร็จ</div>
    <?php endif; ?>
  </div>

  <div class="cards">
    <div class="card">
      <h3>อัปโหลดภาพผัง CCTV</h3>
      <form method="post" enctype="multipart/form-data">
        <label>เลือกไฟล์ภาพ (jpg, png, webp, svg)</label>
        <input type="file" name="plan" accept="image/*" required>
        <button class="btn btn-primary" type="submit" style="margin-top:10px">อัปโหลด</button>
      </form>
    </div>

    <div class="card">
      <h3>ตัวอย่างภาพผัง (Preview)</h3>
      <?php if ($planUrl): ?>
        <div class="plan-box"><img src="<?= h($planUrl) ?>" alt="CCTV Plan"></div>
        <div style="margin-top:8px">
          <a class="btn" href="<?= h($planUrl) ?>" target="_blank" rel="noopener">เปิดรูปในแท็บใหม่</a>
        </div>
      <?php else: ?>
        <p class="small" style="color:#9ca3af">ยังไม่มีการอัปโหลดไฟล์ผัง</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  const mSel = document.getElementById('m');
  const ySel = document.getElementById('y');
  const BUNDLE = '<?= h($BUNDLE_URL) ?>';

  function updateLinks(){
    const q = `?m=${mSel.value}&y=${ySel.value}`;
    document.getElementById('goHik').href   = BUNDLE + '/cctv_checklist_Hik.php'   + q;
    document.getElementById('goDahua').href = BUNDLE + '/cctv_checklist_Dahua.php' + q;

    const u = new URL(window.location.href);
    u.searchParams.set('m', mSel.value);
    u.searchParams.set('y', ySel.value);
    history.replaceState(null, '', u);
  }

  mSel.addEventListener('change', updateLinks);
  ySel.addEventListener('change', updateLinks);
  updateLinks();
})();
</script>

<?php
// ปิดหน้า
require_once $ADMIN_DIR . '/footer.php';
