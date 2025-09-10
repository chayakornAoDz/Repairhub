
<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dbDir  = __DIR__ . '/../data';
        $dbPath = $dbDir . '/app.sqlite';
        if (!is_dir($dbDir)) { @mkdir($dbDir, 0777, true); }
        if (!is_writable($dbDir)) { @chmod($dbDir, 0777); }
        $needInit = !file_exists($dbPath);
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // ลดปัญหา database is locked
            $pdo->exec('PRAGMA journal_mode=WAL;');     // เขียนพร้อมอ่านได้ดีขึ้น
            $pdo->exec('PRAGMA synchronous=NORMAL;');   // เร็วขึ้นและพอเหมาะสำหรับแอพภายใน
            $pdo->exec('PRAGMA busy_timeout=5000;');    // รอ 5 วิถ้าถูกล็อก
            $pdo->exec('PRAGMA foreign_keys = ON;');
            if ($needInit) {
                init_db($pdo);
            }
        } catch (PDOException $e) {
            die('ไม่สามารถเปิดไฟล์ฐานข้อมูลได้: ' . htmlspecialchars($dbPath) . '<br>ตรวจสอบว่าโฟลเดอร์ <code>data/</code> ถูกสร้างและมีสิทธิ์เขียน');
        }
    }
    return $pdo;
}
// เพิ่มฟังก์ชันเช็ค/เพิ่มคอลัมน์
function ensure_column(PDO $pdo, string $table, string $column, string $type) {
    // รองรับ SQLite
    $has = false;
    $rs = $pdo->query("PRAGMA table_info($table)");
    if ($rs) {
        foreach ($rs as $r) {
            if (strcasecmp($r['name'], $column) === 0) { $has = true; break; }
        }
    }
    if (!$has) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
    }
}

function init_db($pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        display_name TEXT,
        profile_pic TEXT,
        role TEXT DEFAULT "admin",
        created_at TEXT NOT NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_no TEXT UNIQUE NOT NULL,
        name TEXT,
        contact TEXT,
        department TEXT,
        location TEXT,
        category TEXT,
        priority TEXT,
        description TEXT,
        attachment TEXT,
        status TEXT DEFAULT "ใหม่",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS request_updates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER NOT NULL,
        status TEXT,
        note TEXT,
        updated_by INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku TEXT UNIQUE,
        name TEXT NOT NULL,
        unit TEXT DEFAULT "ชิ้น",
        stock_qty REAL DEFAULT 0,
        min_qty REAL DEFAULT 0,
        location TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS stock_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER NOT NULL,
        qty REAL NOT NULL,
        type TEXT NOT NULL, -- in|out|adjust|issue|return
        reference TEXT,
        created_by INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
    );');

    $pdo->exec('CREATE TABLE IF NOT EXISTS loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        requester_name TEXT,
        contact TEXT,
        item_id INTEGER NOT NULL,
        qty REAL NOT NULL,
        loan_date TEXT NOT NULL,
        due_date TEXT,
        return_date TEXT,
        status TEXT DEFAULT "ยืมอยู่",
        note TEXT,
        created_by INTEGER,
        created_at TEXT NOT NULL,
        FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
    );');

    // Seed default admin
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO admins (username, password_hash, display_name, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', date('c')]);

    // Indices
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_requests_created ON requests(created_at);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inventory_sku ON inventory_items(sku);');
}
?>
