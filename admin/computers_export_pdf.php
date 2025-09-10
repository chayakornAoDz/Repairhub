<?php
// admin/computers_export_pdf.php
// เปิดหน้า Print (HTML/CSS) แนวนอน A4 ไม่ใช้ mPDF + thead ซ้ำทุกหน้า

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../inc/functions.php';
$db = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmt_dmy($s){
  $s = trim((string)$s); if ($s==='') return '';
  $s = substr($s,0,10);
  [$y,$m,$d] = array_pad(explode('-', $s), 3, '');
  $y=(int)$y; $m=(int)$m; $d=(int)$d;
  if ($y>2400) $y-=543; // กันค่าปี พ.ศ.
  if ($y<=0||$m<=0||$d<=0) return h($s);
  return sprintf('%02d/%02d/%02d', $d, $m, $y%100);
}

/* ----- filters ----- */
$q    = trim($_GET['q'] ?? '');
$dep  = trim($_GET['dep'] ?? '');
$type = trim($_GET['type'] ?? '');

$where  = " WHERE 1=1 ";
$params = [];
if ($q   !== '') { $where .= " AND (hostname LIKE :q OR user_name LIKE :q OR ip_address LIKE :q OR brand_model LIKE :q) "; $params[':q'] = "%$q%"; }
if ($dep !== '') { $where .= " AND department = :dep ";  $params[':dep']  = $dep; }
if ($type!== '') { $where .= " AND type = :type ";       $params[':type'] = $type; }

$sql = "SELECT * FROM computers {$where} ORDER BY department, user_name, hostname";
$st  = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ----- header text ----- */
$company  = 'SPB Engineering Co.,Ltd.';
$formcode = 'QF-AD-04-01';
$title    = 'ทะเบียนคอมพิวเตอร์ - พิมพ์';

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Computers - Print</title>
<style>
/* ขนาดกระดาษ A4 แนวนอน + ระยะขอบ */
@page { size: A4 landscape; margin: 12mm; }

/* ฟอนต์และพื้นฐาน */
html,body{ font-family:"TH Sarabun New", Arial, "DejaVu Sans", sans-serif; font-size:10pt; color:#111; }
table{ width:100%; border-collapse:collapse; }
th, td{ border:1px solid #666; padding:4px 5px; vertical-align:top; }
thead{ display: table-header-group; }  /* ทำหัวตารางซ้ำทุกหน้า */
tfoot{ display: table-footer-group; }
th{ background:#eef3f7; font-weight:700; }
.center{ text-align:center; }
.right{ text-align:right; }
.noborder{ border:none !important; }
.header-cell{ border:1px solid #666; padding:4px 6px; }
tr{ page-break-inside: avoid; }

/* จัดความกว้างคอลัมน์คร่าวๆ ให้พอดีหน้า */
th.w-idx{ width:22px; }
th.w-type{ width:38px; }
th.w-host{ width:100px; }
th.w-user{ width:90px; }
th.w-pos { width:80px; }
th.w-dep { width:70px; }
th.w-brand{ width:95px; }
th.w-cpu{ width:70px; }
th.w-ram{ width:42px; }
th.w-sto{ width:70px; }
th.w-os { width:55px; }
th.w-soft{ width:90px; }
th.w-mon{ width:70px; }
th.w-prn{ width:70px; }
th.w-peri{ width:75px; }
th.w-ip{ width:75px; }
th.w-loc{ width:50px; }
th.w-buy{ width:45px; }
th.w-war{ width:60px; }
th.w-note{ width:80px; }
</style>
</head>
<body onload="setTimeout(()=>window.print(), 50)">

<table>
  <thead>
    <!-- แถวหัวเอกสาร (ซ้ำทุกหน้า) -->
    <tr class="noborder">
      <th colspan="18" class="header-cell" style="text-align:left;"><?=$company?></th>
      <th colspan="2" class="header-cell right">
        <span style="display:inline-block; border:1px solid #666; padding:3px 8px;"><?=$formcode?></span>
      </th>
    </tr>
    <tr class="noborder">
      <th colspan="20" class="noborder center" style="padding:6px 0 4px 0; font-weight:700; font-size:12pt;"><?=h($title)?></th>
    </tr>

    <!-- แถวหัวคอลัมน์ (ซ้ำทุกหน้า) -->
    <tr>
      <th class="center w-idx">#</th>
      <th class="w-type">Type</th>
      <th class="w-host">Computer Name</th>
      <th class="w-user">ผู้ใช้งาน</th>
      <th class="w-pos">ตำแหน่ง</th>
      <th class="w-dep">แผนก</th>
      <th class="w-brand">ยี่ห้อ/รุ่น</th>
      <th class="w-cpu">CPU</th>
      <th class="w-ram">RAM</th>
      <th class="w-sto">HDD/SSD</th>
      <th class="w-os">OS</th>
      <th class="w-soft">โปรแกรมที่ใช้</th>
      <th class="w-mon">จอภาพ</th>
      <th class="w-prn">Printer</th>
      <th class="w-peri">การต่อพ่วง</th>
      <th class="w-ip">IP/Network</th>
      <th class="w-loc">สถานที่</th>
      <th class="w-buy">ซื้อ</th>
      <th class="w-war">หมดประกัน</th>
      <th class="w-note">หมายเหตุ</th>
    </tr>
  </thead>

  <tbody>
    <?php $i=1; foreach($rows as $r): ?>
      <tr>
        <td class="center"><?= $i++; ?></td>
        <td><?= h($r['type']) ?></td>
        <td><?= h($r['hostname']) ?></td>
        <td><?= h($r['user_name']) ?></td>
        <td><?= h($r['position_name']) ?></td>
        <td><?= h($r['department']) ?></td>
        <td><?= h($r['brand_model']) ?></td>
        <td><?= h($r['cpu']) ?></td>
        <td><?= h($r['ram']) ?></td>
        <td><?= h($r['storage']) ?></td>
        <td><?= h($r['os_name']) ?></td>
        <td><?= h($r['key_software']) ?></td>
        <td><?= h($r['monitor']) ?></td>
        <td><?= h($r['printer']) ?></td>
        <td><?= h($r['peripherals']) ?></td>
        <td><?= h($r['ip_address']) ?></td>
        <td><?= h($r['location']) ?></td>
        <td class="center"><?= fmt_dmy($r['purchase_date']) ?></td>
        <td class="center"><?= fmt_dmy($r['warranty_end']) ?></td>
        <td><?= h($r['note']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
