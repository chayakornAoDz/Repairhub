
<?php
require_once __DIR__ . '/../inc/auth.php'; require_login();
require_once __DIR__ . '/../inc/functions.php';
$pdo = db();
$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? 'ใหม่';
$note = $_POST['note'] ?? '';

$pdo->beginTransaction();
try{
  $stmt = $pdo->prepare('UPDATE requests SET status=?, updated_at=? WHERE id=?');
  $stmt->execute([$status, date('c'), $id]);

  $log = $pdo->prepare('INSERT INTO request_updates (request_id,status,note,updated_by,created_at) VALUES (?,?,?,?,?)');
  $log->execute([$id, $status, $note, $_SESSION['admin_id'], date('c')]);

  $pdo->commit();
} catch(Exception $e){
  $pdo->rollBack();
}
header('Location: request_view.php?id='.$id);
exit;
