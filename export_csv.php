
<?php
require_once __DIR__ . '/inc/functions.php';
$pdo = db();
$type = $_GET['type'] ?? 'daily';
$day = $_GET['day'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

if($type==='daily'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at)=? ORDER BY id DESC');
  $stmt->execute([$day]);
  $title = "daily-$day";
} elseif($type==='monthly'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,7)=? ORDER BY id DESC');
  $stmt->execute([$month]);
  $title = "monthly-$month";
} elseif($type==='yearly'){
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE substr(created_at,1,4)=? ORDER BY id DESC');
  $stmt->execute([$year]);
  $title = "yearly-$year";
} else {
  $stmt = $pdo->prepare('SELECT * FROM requests WHERE date(created_at) BETWEEN ? AND ? ORDER BY id DESC');
  $stmt->execute([$from,$to]);
  $title = "range-$from-$to";
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report-'.$title.'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['เวลา','Ticket','ผู้แจ้ง','ติดต่อ','แผนก','สถานที่','ประเภท','สำคัญ','สถานะ','รายละเอียด']);
while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
  fputcsv($out, [date('Y-m-d H:i', strtotime($r['created_at'])), $r['ticket_no'], $r['name'], $r['contact'], $r['department'], $r['location'], $r['category'], $r['priority'], $r['status'], preg_replace("/\s+/"," ", $r['description'])]);
}
fclose($out);
exit;
