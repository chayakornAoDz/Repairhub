
<?php require_once __DIR__ . '/header.php'; require_once __DIR__ . '/../inc/functions.php';
$pdo = db();
$today = date('Y-m-d');
$month = date('Y-m');
$year = date('Y');

// ===== สรุปตัวเลขสถานะงาน =====
$counts = [];
foreach(['ใหม่','กำลังดำเนินการ','รออะไหล่','เสร็จสิ้น','ยกเลิก'] as $st){
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status = ?');
  $stmt->execute([$st]);
  $counts[$st] = (int)$stmt->fetchColumn();
}

// ===== เตรียมข้อมูลสำหรับกราฟแบบ Grouped (6 เดือนล่าสุด) =====
$months = [];
for ($i = 5; $i >= 0; $i--) {
  $months[] = date('Y-m', strtotime("-$i month"));
}
// ดึงยอดรายเดือน x หมวด (ไม่พึ่งฟังก์ชันวันที่ของ SQLite เพื่อลดปัญหา format)
$placeholders = implode(',', array_fill(0, count($months), '?'));
$sql = "
  SELECT substr(created_at,1,7) AS ym,
         COALESCE(category,'ไม่ระบุ') AS category,
         COUNT(*) AS c
  FROM requests
  WHERE substr(created_at,1,7) IN ($placeholders)
  GROUP BY ym, category
";
$stmt = $pdo->prepare($sql);
$stmt->execute($months);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// รวมยอดต่อหมวดทั้งช่วง เพื่อจัดลำดับมาก→น้อย (ถ้าอยากจำกัดจำนวนใช้ array_slice(...,0,$K))
$agg = [];
foreach ($rows as $r) {
  $agg[$r['category']] = ($agg[$r['category']] ?? 0) + (int)$r['c'];
}
arsort($agg);
$categories = array_keys($agg);

// index helper
$indexByMonth = array_flip($months);
$indexByCat   = array_flip($categories);

// matrix เดือน x หมวด
$matrix = [];
foreach ($months as $m) $matrix[] = array_fill(0, count($categories), 0);
foreach ($rows as $r) {
  $ym  = $r['ym'];
  $cat = $r['category'];
  if (!isset($indexByMonth[$ym]) || !isset($indexByCat[$cat])) continue;
  $matrix[$indexByMonth[$ym]][$indexByCat[$cat]] += (int)$r['c'];
}

// ป้ายกำกับล่าง
$labels = array_map(fn($ym)=>date('m/y', strtotime($ym.'-01')), $months);

// หา max สำหรับสเกลกราฟ
$max = 1;
foreach ($matrix as $row) foreach ($row as $v) if ($v > $max) $max = $v;
?>
<div class="card">
  <h1 style="margin-top:0">สรุปภาพรวม</h1>
  <div class="row-4">
    <?php foreach($counts as $k=>$v): ?>
      <div class="stat">
        <div style="flex:1">
          <div style="color:#9ca3af;font-size:12px"><?= h($k) ?></div>
          <div class="num"><?= h($v) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2 style="margin-top:0">งานใหม่ย้อนหลัง 6 เดือน</h2>
  <!-- Legend จะถูกเติมโดย JS -->
  <div id="bar6Legend" class="muted" style="margin-bottom:8px"></div>
  <!-- กล่องกราฟหลัก -->
  <div class="bar" id="bar6"></div>
  <!-- ป้ายกำกับเดือนล่าง -->
  <div id="bar6Labels" class="muted" style="display:flex;justify-content:flex-start;margin-top:8px"></div>
</div>

<!-- ส่งข้อมูลให้ JS ใช้งาน -->
<script>
window.chartData = {
  months: <?= json_encode($months, JSON_UNESCAPED_UNICODE) ?>,
  labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
  categories: <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>,
  matrix: <?= json_encode($matrix, JSON_UNESCAPED_UNICODE) ?>,
  max: <?= (int)$max ?>
};
</script>
<script>console.log('chartData =', window.chartData);</script>
<script src="../assets/js/dashboard_chart.js?v=5"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
