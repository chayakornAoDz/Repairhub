<?php
// admin/cctv_bundle/cctv_checklist_Hik.php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../header.php';

$BUNDLE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // /repairhub/admin/cctv_bundle
$API = $BUNDLE_URL . '/api/hik';

$months_th=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$y_now=(int)date('Y');
$m_now=(int)date('n');

/* >>> รับค่าจาก query string ถ้ามี <<< */
$m = isset($_GET['m']) ? max(1,min(12,(int)$_GET['m'])) : $m_now;
$y = isset($_GET['y']) ? (int)$_GET['y'] : $y_now;
?>
<link rel="stylesheet" href="/repairhub/assets/css/style.css">

<div class="cctv-wrap">
  <header class="cctv-header">
    <div class="cctv-title">
      <strong>ตารางตรวจเช็คกล้องวงจรปิด (Hikvision)</strong>
      <small class="cctv-sub">** ปกติใส่ / | ไม่ปกติใส่ × (คลิกเซลล์เพื่อสลับค่า) **</small>
    </div>
    <div class="cctv-ctrl">
      <label class="cctv-field"><span>เดือน</span>
        <select id="cctv-month" class="cctv-select">
          <?php foreach($months_th as $i=>$name): $val=$i+1; ?>
            <option value="<?= $val ?>" <?= $val===$m?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="cctv-field"><span>ปี</span>
        <select id="cctv-year" class="cctv-select">
          <?php for($yy=$y_now-3;$yy<=$y_now+3;$yy++): ?>
            <option value="<?= $yy ?>" <?= $yy==$y?'selected':'' ?>><?= $yy+543 ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <button id="cctv-cam" class="cctv-btn ghost">ตั้งค่ากล้อง</button>
      <button id="cctv-clear" class="cctv-btn ghost">ล้างเดือนนี้</button>
      <button id="cctv-export" class="cctv-btn outline">ส่งออก (.json)</button>
      <input id="cctv-file" type="file" accept="application/json" hidden>
      <button id="cctv-import" class="cctv-btn outline">นำเข้า</button>
      <button id="cctv-print" class="cctv-btn">พิมพ์ / PDF</button>
    </div>
  </header>

  <div class="cctv-table-wrap">
    <table class="cctv-table cctv-eq" id="cctv-table" aria-describedby="cctv-caption">
      <caption id="cctv-caption">เช็คลิสต์รายวัน แยกตามกล้อง</caption>
      <thead id="cctv-thead"></thead>
      <tbody id="cctv-tbody"></tbody>
      <tfoot id="cctv-tfoot"></tfoot>
    </table>
  </div>

  <div class="cctv-note" style="margin-top:10px">
    <label class="inline"><span>ผู้จัดทำ</span><input id="cctv-author" class="cctv-input" placeholder="ชื่อ - นามสกุล"></label>
    <label class="inline"><span>หมายเหตุ (สาเหตุ)</span><input id="cctv-note" class="cctv-input" placeholder="ตัวอย่าง: A02 Adapter เสีย เปลี่ยนวันที่ 25/08/68"></label>
  </div>
</div>

<script>
  window.CCTV_CONFIG = {
    set:'hik', prefix:'A', count:16, useServer:true,
    api:{ load:'<?= htmlspecialchars($API) ?>/load.php', save:'<?= htmlspecialchars($API) ?>/save.php' }
  };
</script>
<script src="<?= htmlspecialchars($BUNDLE_URL) ?>/assets/cctv.js"></script>
