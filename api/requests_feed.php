
<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json; charset=utf-8');

try{
  $pdo = db();
  $stmt = $pdo->query("SELECT ticket_no, name, department, category, status, substr(created_at,1,16) as created_at FROM requests ORDER BY datetime(created_at) DESC LIMIT 10");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error'=> $e->getMessage()]);
}
