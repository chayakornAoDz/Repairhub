
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function gen_ticket_no(PDO $pdo) {
    $date = date('Ymd');
    $prefix = "RH-$date-";
    // find last number today
    $stmt = $pdo->prepare('SELECT ticket_no FROM requests WHERE ticket_no LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = 1;
    if ($last) {
        $parts = explode('-', $last);
        $num = intval(end($parts)) + 1;
    }
    return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function save_upload($field, $targetDir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $safe = uniqid('file_', true) . ($ext ? ('.' . strtolower($ext)) : '');
    $dest = rtrim($targetDir, '/').'/'.$safe;
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return $safe;
    }
    return null;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function parse_date($str) {
    $ts = strtotime($str);
    return $ts ? date('Y-m-d', $ts) : null;
}

function guard_csrf() {
    session_start();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400);
            echo 'CSRF token invalid';
            exit;
        }
    }
}
?>
