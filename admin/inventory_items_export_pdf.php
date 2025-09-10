<?php
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();

/* ===== รับตัวกรอง (หมวดหมู่) ===== */
$cat = $_GET['cat'] ?? '';

/* ===== WHERE + PARAMS ===== */
$where  = '1=1';
$params = [];
if ($cat !== '') { $where .= " AND COALESCE(category,'อื่น ๆ') = ?"; $params[] = $cat; }

/* ===== ดึงรายการทั้งหมดตามตัวกรอง (เรียงตามหมวด > ชื่อ) ===== */
$sql = "
  SELECT id, sku, name, category, unit, stock_qty, min_qty, location
  FROM inventory_items
  WHERE $where
  ORDER BY COALESCE(category,'อื่น ๆ'), name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== helper ===== */
function nfmt($n){
  // แสดงตัวเลขแบบสั้น (ไม่มีทศนิยมหากไม่จำเป็น)
  return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายการทรัพย์สิน - รายงานทรัพย์สิน (PDF)</title>
<style>
  /* รูปแบบพื้นฐานสำหรับพิมพ์ */
  body{
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Inter, Arial, sans-serif;
    margin: 24px;
    color:#111;
  }
  h1{ margin:0 0 6px; }
  .muted{ color:#666; font-size:12px; }
  .sum{ margin:10px 0; font-size:13px; }

  /* ตาราง */
  table{ width:100%; border-collapse:collapse; font-size:12px; table-layout:fixed; }
  th,td{ border:1px solid #cfcfcf; padding:6px 8px; text-align:left; vertical-align:top; }
  th{ background:#f3f4f6; }
  .right{ text-align:right; white-space:nowrap; }
  .center{ text-align:center; }
  .mutedCell{ color:#6b7280; }

  /* สัดส่วนคอลัมน์สำหรับแนวนอน */
  col.col-cat { width: 16%; }  /* หมวดหมู่ */
  col.col-sku { width: 12%; }  /* รหัสทรัพย์สิน */
  col.col-name{ width: 32%; }  /* ชื่อสินค้า */
  col.col-qty { width: 10%; }  /* คงเหลือ */
  col.col-unit{ width: 8%;  }  /* หน่วย */
  col.col-min { width: 8%;  }  /* Min */
  col.col-loc { width: 14%; }  /* ที่เก็บ */

  /* แถบหมวด */
  .catRow th{
    background:#eef2ff;
    color:#1e3a8a;
    font-weight:700;
  }

  /* โหมดพิมพ์แนวนอน */
  @media print{
    @page {
      size: A4 landscape;     /* ← สำคัญ: แนวนอน */
      margin: 12mm 10mm;
    }
    body{ margin:0; }
  }
</style>
</head>
<body>

  <h1>รายงานรายการทรัพย์สิน (Stock List)</h1>
  <div class="muted">
    วันที่ออกรายงาน: <?= h(date('d/m/Y H:i')) ?>
    <?php if($cat!==''): ?> • หมวดหมู่: <?= h($cat) ?><?php endif; ?>
  </div>
  <div class="sum">จำนวนทั้งหมด: <b><?= count($rows) ?></b> รายการ</div>

  <table>
    <colgroup>
      <col class="col-cat">
      <col class="col-sku">
      <col class="col-name">
      <col class="col-qty">
      <col class="col-unit">
      <col class="col-min">
      <col class="col-loc">
    </colgroup>
    <thead>
      <tr>
        <th>หมวดหมู่</th>
        <th>รหัสทรัพย์สิน</th>
        <th>ชื่อทรัพย์สิน</th>
        <th class="right">คงเหลือ</th>
        <th class="center">หน่วย</th>
        <th class="right">Min</th>
        <th>ที่เก็บ</th>
      </tr>
    </thead>
    <tbody>
      <?php if($rows): 
            $currCat = null;
            foreach($rows as $r):
              $catName = $r['category'] ?: 'อื่น ๆ';
              // ถ้าอยากให้มีแถวหัวหมวดแยก, ใช้บล็อกนี้
              if ($cat==='' && $catName !== $currCat):
                $currCat = $catName; ?>
                <tr class="catRow"><th colspan="7"><?= h($currCat) ?></th></tr>
              <?php endif; ?>
              <tr>
                <td><?= h($catName) ?></td>
                <td><?= h($r['sku'] ?: '-') ?></td>
                <td><?= h($r['name']) ?></td>
                <td class="right"><?= h(nfmt($r['stock_qty'])) ?></td>
                <td class="center"><?= h($r['unit']) ?></td>
                <td class="right mutedCell"><?= h(nfmt($r['min_qty'])) ?></td>
                <td><?= h($r['location'] ?: '-') ?></td>
              </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7" class="muted">ไม่พบข้อมูล</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    // เปิดหน้าแล้วสั่งพิมพ์ได้ทันที (ผู้ใช้เลือก Save as PDF เพื่อบันทึกได้)
    window.addEventListener('load', () => { window.print(); });
  </script>
</body>
</html>
