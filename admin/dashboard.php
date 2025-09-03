<?php
require_once __DIR__ . '/header.php';   // header รวม functions.php อยู่แล้ว
$pdo = db();

/* ================== สรุปตัวเลขสถานะงาน ================== */
$statuses = ['ใหม่','กำลังดำเนินการ','รออะไหล่','เสร็จสิ้น','ยกเลิก'];
$counts = [];
foreach ($statuses as $st) {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE status=?');
  $stmt->execute([$st]);
  $counts[$st] = (int)$stmt->fetchColumn();
}

/* ========== เตรียมข้อมูล “สัดส่วนงานตามประเภท” 3 เดือนล่าสุด (Pie/Donut) ========== */
$months = [];
for ($i = 2; $i >= 0; $i--) $months[] = date('Y-m', strtotime("-$i month"));          // เช่น 2025-06, 2025-07, 2025-08
$labels = array_map(fn($ym) => date('M y', strtotime("$ym-01")), $months);            // เช่น Jun 25

$ph   = implode(',', array_fill(0, count($months), '?'));
$sql  = "
  SELECT substr(created_at,1,7) AS ym,
         COALESCE(category,'ไม่ระบุ') AS category,
         COUNT(*) AS c
  FROM requests
  WHERE substr(created_at,1,7) IN ($ph)
  GROUP BY ym, category
";
$stmt = $pdo->prepare($sql);
$stmt->execute($months);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* รวมยอดทุกเดือนเพื่อจัดอันดับ “ประเภทที่พบบ่อย” และล็อกสีให้คงที่ทุกเดือน */
$totalsByCat = [];
foreach ($rows as $r) {
  $totalsByCat[$r['category']] = ($totalsByCat[$r['category']] ?? 0) + (int)$r['c'];
}
arsort($totalsByCat);
$K    = 6;                                                 // แสดงประเภทยอดนิยม 6 รายการ
$cats = array_slice(array_keys($totalsByCat), 0, $K);
$useOthers = count($totalsByCat) > $K;
if ($useOthers) $cats[] = '...';

/* map: เดือน -> หมวด -> จำนวน */
$byMonth = [];
foreach ($months as $m) $byMonth[$m] = array_fill_keys($cats, 0);
foreach ($rows as $r) {
  $ym = $r['ym']; $cat = $r['category']; $c = (int)$r['c'];
  if (!isset($byMonth[$ym])) continue;
  if (array_key_exists($cat, $byMonth[$ym])) {
    $byMonth[$ym][$cat] += $c;
  } elseif ($useOthers) {
    $byMonth[$ym]['...'] += $c;
  }
}

/* สีคงที่ของแต่ละประเภท (พอ) */
$palette = ['#06b6d4','#22d3ee','#60a5fa','#34d399','#f59e0b','#a78bfa','#f87171','#10b981','#94a3b8'];
$colorByCat = [];
foreach ($cats as $i => $cat) $colorByCat[$cat] = $palette[$i % count($palette)];

/* ========== Top 5 “เบิกใช้” ย้อนหลัง 90 วัน ========== */
$since   = date('Y-m-d', strtotime('-90 days'));
$sqlTop5 = "
  SELECT i.id, i.name, i.unit,
         SUM(CASE WHEN sm.type IN ('out','issue') THEN sm.qty ELSE 0 END) AS used_qty
  FROM stock_movements sm
  LEFT JOIN inventory_items i ON i.id = sm.item_id
  WHERE sm.type IN ('out','issue') AND substr(sm.created_at,1,10) >= ?
  GROUP BY i.id, i.name, i.unit
  HAVING used_qty > 0
  ORDER BY used_qty DESC
  LIMIT 5
";
$top5 = $pdo->prepare($sqlTop5);
$top5->execute([$since]);
$top5 = $top5->fetchAll(PDO::FETCH_ASSOC);
$maxUsed = 0; foreach ($top5 as $t) $maxUsed = max($maxUsed, (float)$t['used_qty']);
?>

<style>
/* ====== เฉพาะหน้าแดชบอร์ดนี้ ====== */
.dash-grid{ display:grid; grid-template-columns: 1.1fr 0.9fr; gap:16px; }
@media (max-width:1024px){ .dash-grid{ grid-template-columns:1fr; } }

/* กล่องสรุปสถานะ */
.stat{display:flex;align-items:center;gap:12px;padding:16px;border:1px solid #1f2937;border-radius:14px;background:#0b1222}
.stat .num{font-size:26px;font-weight:700}

/* ===== Donut (SVG) ===== */
.pie-grid{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
@media (max-width:1000px){ .pie-grid{ grid-template-columns:1fr; } }
.pie-card{ border:1px solid #1f2937; border-radius:14px; background:#0b1222; padding:12px; }
.pie-wrap{ display:flex; align-items:center; gap:14px; }
.pie-fig{ width:150px; height:150px; flex:0 0 auto; }
.pie-legend{ display:flex; flex-direction:column; gap:6px; min-width:0; }
.pie-legend .row{ display:flex; align-items:center; gap:8px; justify-content:space-between; }
.pie-legend .left{ display:flex; align-items:center; gap:8px; min-width:0; }
.pie-legend i{ width:12px; height:12px; border-radius:3px; display:inline-block; }
.pie-legend .name{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#e5e7eb; }
.pie-legend .val{ color:#9ca3af; font-variant-numeric: tabular-nums; }

/* ===== Top-5 usage ===== */
.top5-list{ display:flex; flex-direction:column; gap:10px; }
.top5-row{ display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; }
.hbar{ height:12px; background:#0b1222; border:1px solid #1f2937; border-radius:999px; overflow:hidden; }
.hfill{ height:100%; background:linear-gradient(90deg,#0891b2,#06b6d4); }
.item-name{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.item-qty{ color:#cbd5e1; font-variant-numeric: tabular-nums; }
.small-muted{ color:#9ca3af; font-size:12px; }
</style>

<div class="card">
  <h1 style="margin-top:0">สรุปภาพรวม</h1>
  <div class="row-4">
    <?php foreach($counts as $k=>$v): ?>
      <div class="stat">
        <div style="flex:1">
          <div style="color:#9ca3af;font-size:12px"><?= h($k) ?></div>
          <div class="num"><?= (int)$v ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="dash-grid" style="margin-top:16px">

  <!-- ===== Donut: สัดส่วนงานตามประเภท (3 เดือนล่าสุด) ===== -->
  <div class="card">
    <div class="report-header">
      <h2 style="margin:0">สัดส่วนงานตามประเภท (3 เดือนล่าสุด)</h2>
      <div class="small-muted">Donut pie ต่อเดือน • สีของประเภทคงที่ทุกเดือน</div>
    </div>

    <div class="pie-grid" style="margin-top:10px">
      <?php
        $R = 54;              // รัศมีวง
        $STROKE = 22;         // ความหนา donut
        $C = 2 * M_PI * $R;   // เส้นรอบวง
        $GAP = 0;             // ช่องว่างระหว่างเซกเมนต์

        foreach ($months as $i => $ym):
          $monthData = $byMonth[$ym];
          $total = max(0, array_sum($monthData));
      ?>
        <div class="pie-card">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700"><?= h($labels[$i]) ?></div>
            <div class="small-muted">รวม <?= (int)$total ?> งาน</div>
          </div>

          <div class="pie-wrap">
            <svg class="pie-fig" viewBox="0 0 160 160" role="img" aria-label="งานตามประเภท">
              <g transform="translate(80,80) rotate(-90)">
                <?php
                  $offset = 0;
                  if ($total === 0) {
                    echo '<circle r="'.$R.'" cx="0" cy="0" fill="none" stroke="#243047" stroke-width="'.$STROKE.'" />';
                  } else {
                    // พื้น (สีเข้ม) 1 ครั้งพอ ช่วยให้ช่องว่างดูเรียบ
                    echo '<circle r="'.$R.'" cx="0" cy="0" fill="none" stroke="#0b1222" stroke-width="'.$STROKE.'" />';
                    foreach ($cats as $cat) {
                      $v = (int)$monthData[$cat];
                      if ($v <= 0) continue;
                      $len  = ($v / $total) * $C;
                      $dash = max(1, $len - $GAP);
                      $rest = $C - $dash;
                      $color = $colorByCat[$cat];
                      echo '<circle r="'.$R.'" cx="0" cy="0" fill="none" stroke="'.$color.'" stroke-width="'.$STROKE.'" '.
                           'stroke-dasharray="'.$dash.' '.$rest.'" stroke-dashoffset="'.(-$offset).'" />';
                      $offset += $len;
                    }
                  }
                ?>
              </g>
            </svg>

            <div class="pie-legend" style="flex:1 1 auto">
              <?php foreach ($cats as $cat):
                $v = (int)$monthData[$cat];
                $pct = ($total>0) ? round(($v/$total)*100) : 0;
              ?>
                <div class="row">
                  <div class="left">
                    <i style="background:<?= h($colorByCat[$cat]) ?>"></i>
                    <div class="name"><?= h($cat) ?></div>
                  </div>
                  <div class="val"><?= $v ?> <span class="small-muted">(<?= $pct ?>%)</span></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== Top-5 เบิกใช้มากสุด ===== -->
  <div class="card">
    <div class="report-header">
      <h2 style="margin:0">เบิกใช้สูงสุด 5 อันดับ</h2>
      <div class="small-muted">ย้อนหลัง 90 วัน</div>
    </div>

    <?php if (!$top5): ?>
      <div class="inv-muted">ไม่มีข้อมูลเบิกใช้</div>
    <?php else: ?>
      <div class="top5-list" style="margin-top:10px">
        <?php foreach ($top5 as $row):
          $pct = $maxUsed > 0 ? max(3, round(($row['used_qty']/$maxUsed)*100)) : 0;
        ?>
          <div>
            <div class="top5-row">
              <div class="item-name"><?= h($row['name']) ?></div>
              <div class="item-qty"><?= h($row['used_qty']) ?> <?= h($row['unit']) ?></div>
            </div>
            <div class="hbar"><div class="hfill" style="width:<?= $pct ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
