<?php
// admin/inventory_export_xlsx.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/functions.php';

// autoload จาก Composer
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$pdo = db();

/* ---------- รับพารามิเตอร์ฟิลเตอร์ ---------- */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$cat  = $_GET['cat']  ?? '';
$type = $_GET['type'] ?? '';

/* ---------- where / params ---------- */
$where  = "WHERE date(m.created_at) BETWEEN ? AND ?";
$params = [$from, $to];

if ($cat !== '') { $where .= " AND COALESCE(i.category,'อื่น ๆ') = ?"; $params[] = $cat; }
if ($type!== '') { $where .= " AND m.type = ?";                       $params[] = $type; }

/* ---------- ดึงข้อมูล ---------- */
$sql = "
SELECT m.created_at, i.sku, i.name, COALESCE(i.category,'อื่น ๆ') AS category,
       m.type, m.qty, i.unit, m.reference
FROM stock_movements m
JOIN inventory_items i ON i.id = m.item_id
{$where}
ORDER BY m.created_at DESC, m.id DESC
";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- สร้างไฟล์ Excel ---------- */
$ss = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Stock Movements');

/* หัวรายงาน */
$sheet->setCellValue('A1', 'รายงานความเคลื่อนไหวสต็อก');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$meta = "ช่วงวันที่ {$from} ถึง {$to}";
if ($cat  !== '') $meta .= " • หมวดหมู่: {$cat}";
if ($type !== '') $meta .= " • ประเภท: {$type}";
$sheet->setCellValue('A2', $meta);
$sheet->mergeCells('A2:H2');

/* หัวคอลัมน์ */
$headerRow = 4;
$headers = ['เวลา','SKU','ชื่อสินค้า','หมวดหมู่','ประเภท','จำนวน(+/-)','หน่วย','อ้างอิง'];
$sheet->fromArray($headers, null, "A{$headerRow}");

$sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
  'font' => ['bold' => true],
  'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5F3FF']],
  'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

/* เนื้อข้อมูล */
$r = $headerRow + 1;
foreach($rows as $row){
  $sign = in_array($row['type'], ['out','issue']) ? -1 : 1;
  $sheet->setCellValue("A{$r}", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($row['created_at'])))
        ->setCellValueExplicit("B{$r}", (string)($row['sku'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
        ->setCellValue("C{$r}", $row['name'])
        ->setCellValue("D{$r}", $row['category'])
        ->setCellValue("E{$r}", $row['type'])
        ->setCellValue("F{$r}", $sign * (float)$row['qty'])
        ->setCellValue("G{$r}", $row['unit'])
        ->setCellValue("H{$r}", $row['reference']);
  $r++;
}

/* เสริมความสวยงาม */
$last = $r - 1;
if ($last >= $headerRow + 1) {
  // เส้นตาราง
  $sheet->getStyle("A{$headerRow}:H{$last}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

  // ฟอร์แมตวันที่และตัวเลข
  $sheet->getStyle("A".($headerRow+1).":A{$last}")
        ->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
  $sheet->getStyle("F".($headerRow+1).":F{$last}")
        ->getNumberFormat()->setFormatCode('#,##0.00');
}

/* ปรับความกว้างคอลัมน์/Freeze header */
foreach (range('A','H') as $col) {
  $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->freezePane('A'.($headerRow+1));

/* ---------- ส่งไฟล์ให้ดาวน์โหลด ---------- */
$fname = "inventory_{$from}_to_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
