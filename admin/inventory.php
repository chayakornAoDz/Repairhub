<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../inc/functions.php';

$pdo = db();

/* --------- สิทธิ์ลบ & ผู้ใช้ปัจจุบัน --------- */
$ADMIN_ID = 1;                                   // id admin หลักที่อนุญาตให้ลบสินค้า
$me_id    = (int)($_SESSION['admin_id'] ?? 0);

/* --------- POST -> Redirect -> GET (กันส่งฟอร์มซ้ำ) --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // เก็บ query string เดิม (คงหน้า/ตัวกรอง)
  $qs = [];
  if (isset($_GET['page'])) $qs['page'] = (int)$_GET['page'];
  if (isset($_GET['cat']) && $_GET['cat'] !== '') $qs['cat'] = $_GET['cat'];
  $qs = $qs ? ('?' . http_build_query($qs)) : '';

  try {
    $act = $_POST['action'] ?? '';

    if ($act === 'update_item') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('id ไม่ถูกต้อง');
      $stmt = $pdo->prepare('
        UPDATE inventory_items
           SET sku=?, name=?, category=?, unit=?, min_qty=?, location=?, updated_at=?
         WHERE id=?
      ');
      $stmt->execute([
        trim($_POST['sku'] ?? '') ?: null,
        trim($_POST['name'] ?? ''),
        trim($_POST['category'] ?? '') ?: 'อื่น ๆ',
        trim($_POST['unit'] ?? '') ?: 'ชิ้น',
        (float)($_POST['min_qty'] ?? 0),
        trim($_POST['location'] ?? ''),
        date('c'),
        $id
      ]);
      $_SESSION['flash'] = ['msg' => 'อัปเดตรายการแล้ว', 'err' => ''];
    }

    elseif ($act === 'delete_item') {
      if ($me_id !== $ADMIN_ID) {
        $_SESSION['flash'] = ['msg' => '', 'err' => 'คุณไม่มีสิทธิ์ลบสินค้า'];
      } else {
        $id = (int)($_POST['id'] ?? 0);
        // ลบความเคลื่อนไหวที่ผูกไว้ก่อน (ถ้าไม่ได้ตั้ง FK)
        $pdo->prepare('DELETE FROM stock_movements WHERE item_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM inventory_items  WHERE id=?')->execute([$id]);
        $_SESSION['flash'] = ['msg' => 'ลบสินค้าแล้ว', 'err' => ''];
      }
    }

  } catch (Throwable $e) {
    $_SESSION['flash'] = ['msg'=>'', 'err'=>'ผิดพลาด: '.$e->getMessage()];
  }

  header('Location: inventory.php' . $qs);
  exit;
}

/* --------- ตัวแปรแบ่งหน้า/ตัวกรอง --------- */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset  = ($page - 1) * $perPage;

$catFilter = $_GET['cat'] ?? '';

/* --------- หมวดหมู่สำหรับ filter --------- */
$cats = $pdo->query("SELECT COALESCE(category,'อื่น ๆ') AS c FROM inventory_items GROUP BY c ORDER BY c")
            ->fetchAll(PDO::FETCH_COLUMN);
if (!is_array($cats)) $cats = [];

/* --------- สรุปจำนวนต่อหมวด (ทำปุ่ม pill) --------- */
/* สรุปจำนวนต่อหมวดเพื่อขึ้น badge */
$catStats = $pdo->query("
  SELECT COALESCE(category,'อื่น ๆ') AS c, COUNT(*) AS cnt
  FROM inventory_items
  GROUP BY c
")->fetchAll(PDO::FETCH_ASSOC);
$counts = [];
foreach($catStats as $r){ $counts[$r['c']] = (int)$r['cnt']; }

/* ชุดหมวดตายตัว (ปุ่มย่อ) */
$fixedCats = ['หมึกพิมพ์','อุปกรณ์คอมพ์','CCTV','อะไหล่','เครื่องเขียน','แม่พิมพ์/งานช่าง','อื่น ๆ'];



/* --------- ดึงรายการตามหน้า --------- */
$where  = '1=1';
$params = [];
if ($catFilter !== '') { $where .= " AND COALESCE(category,'อื่น ๆ') = ?"; $params[] = $catFilter; }

$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE $where");
$stmtCnt->execute($params);
$total       = (int)$stmtCnt->fetchColumn();
$totalPages  = max(1, (int)ceil($total / $perPage));

$stmtList = $pdo->prepare("
  SELECT * FROM inventory_items
  WHERE $where
  ORDER BY COALESCE(category,'อื่น ๆ'), name
  LIMIT ? OFFSET ?
");
foreach ($params as $k=>$v) { $stmtList->bindValue($k+1, $v, PDO::PARAM_STR); }
$stmtList->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmtList->bindValue(count($params)+2, $offset,  PDO::PARAM_INT);
$stmtList->execute();
$itemsPage = $stmtList->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($itemsPage)) $itemsPage = [];

/* --------- flash --------- */
$flash = $_SESSION['flash'] ?? null;
if (!is_array($flash)) $flash = ['msg'=>'','err'=>''];
$_SESSION['flash'] = ['msg'=>'','err'=>''];
?>

<div class="inv-page container">
  <div class="inv-head">
    <h1 class="inv-title">รายการสินค้า</h1>
    <?php if (!empty($flash['msg'])): ?><div class="inv-badge inv-good"><?= h($flash['msg']) ?></div><?php endif; ?>
    <?php if (!empty($flash['err'])): ?><div class="inv-badge inv-bad"><?= h($flash['err']) ?></div><?php endif; ?>
  </div>

  <!-- รายการสินค้า -->
  <div class="inv-card">
      <!-- ...เดิม... -->
      <div class="inv-list-head">
          <div class="inv-box red" style="margin-bottom:12px">
          <div class="inv-toolbar">
            <h3 class="inv-card-title" style="margin:0">รายการสินค้าที่มีทั้งหมด</h3>

            <div class="right">
              <!-- กรองด้วย select เดิม -->
              <form method="get" class="inv-actions" style="display:flex;gap:8px;align-items:center">
                <select class="inv-input" name="cat">
                  <option value="">ทุกหมวดหมู่</option>
                  <?php foreach ($cats as $c): ?>
                    <option value="<?= h($c) ?>" <?= $catFilter===$c?'selected':'' ?>><?= h($c) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($page>1): ?><input type="hidden" name="page" value="<?= (int)$page ?>"><?php endif; ?>
                <button class="inv-btn">กรอง</button>

                <!-- PDF แนวนอน -->
                <a class="inv-btn" target="_blank"
                  href="inventory_items_export_pdf.php?<?= http_build_query(['cat'=>$catFilter]) ?>">
                  ดาวน์โหลด PDF
                </a>
              </form>
            </div>
      </div>

      <!-- แถบปุ่มหมวดหมู่ (กรอบสีเหลือง) -->
  <div class="cat-row">
    <div class="cat-scroll">
      <div class="inv-box yellow" style="margin-top:8px">
        <div class="cat-pills">
          <?php
            $isAll = ($catFilter==='');
            // ปุ่ม "ทั้งหมด"
            $totalAll = array_sum(array_map(fn($r)=>(int)$r['cnt'],$catStats));
          ?>
          <button type="button"
                  class="cat-pill <?= $isAll?'active':'' ?>"
                  onclick="goCat('')">
            ทั้งหมด <span class="count"><?= (int)$totalAll ?></span>
          </button>

              <?php foreach($catStats as $row): $cname=$row['c']; $cnt=(int)$row['cnt']; ?>
                <button type="button"
                        class="cat-pill <?= $catFilter===$cname?'active':'' ?>"
                        onclick="goCat('<?= h($cname) ?>')">
                  <?= h($cname) ?> <span class="count"><?= $cnt ?></span>
                </button>
              <?php endforeach; ?>
           </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
    <div class="inv-table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>รหัสทรัพย์สิน</th>
            <th>ชื่อ</th>
            <th>คงเหลือ</th>
            <th>หน่วย</th>
            <th>Min</th>
            <th>ที่เก็บ</th>
            <th class="inv-right">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $currentCat = null;
          foreach ($itemsPage as $i):
            $cat = $i['category'] ?: 'อื่น ๆ';
            if ($cat !== $currentCat):
              $currentCat = $cat;
        ?>
            <tr class="inv-cat-row"><td colspan="7"><?= h($currentCat) ?></td></tr>
        <?php endif; ?>
            <tr>
              <td><?= h($i['sku'] ?: '-') ?></td>
              <td><?= h($i['name']) ?></td>
              <td><?= h($i['stock_qty']) ?></td>
              <td><?= h($i['unit']) ?></td>
              <td><?= h($i['min_qty']) ?></td>
              <td><?= h($i['location']) ?></td>
              <td class="inv-right">
                <button class="inv-btn inv-sm" type="button" onclick="toggleEdit(<?= (int)$i['id'] ?>)">แก้ไข</button>
                <?php if ($me_id === $ADMIN_ID): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('ลบสินค้า \"<?= h($i['name']) ?>\" ?')">
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                    <button class="inv-btn inv-sm inv-danger" type="submit">ลบ</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>

            <!-- ฟอร์มแก้ไข -->
            <tr id="edit-<?= (int)$i['id'] ?>" class="inv-edit-row" style="display:none">
              <td colspan="7">
                <form method="post" class="inv-edit-form">
                  <input type="hidden" name="action" value="update_item">
                  <input type="hidden" name="id"     value="<?= (int)$i['id'] ?>">

                  <div class="inv-grid-3">
                    <div>
                      <label>รหัสทรัพย์สิน</label>
                      <input class="inv-input" name="sku" value="<?= h($i['sku']) ?>">
                    </div>
                    <div>
                      <label>ชื่อ</label>
                      <input class="inv-input" name="name" value="<?= h($i['name']) ?>" required>
                    </div>
                    <div>
                      <label>หมวดหมู่</label>
                      <select class="inv-input" name="category">
                        <?php
                          $allCats = array_values(array_unique(array_merge($cats, ['หมึกพิมพ์','อุปกรณ์คอมพ์','อะไหล่','เครื่องเขียน','แม่พิมพ์/งานช่าง','อื่น ๆ'])));
                          sort($allCats, SORT_NATURAL);
                          foreach ($allCats as $c):
                        ?>
                          <option <?= ($i['category']?:'อื่น ๆ')===$c ? 'selected':'' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="inv-grid-3">
                    <div>
                      <label>หน่วย</label>
                      <input class="inv-input" name="unit" value="<?= h($i['unit']) ?>">
                    </div>
                    <div>
                      <label>Min</label>
                      <input class="inv-input" type="number" step="0.01" name="min_qty" value="<?= h($i['min_qty']) ?>">
                    </div>
                    <div>
                      <label>ที่เก็บ</label>
                      <input class="inv-input" name="location" value="<?= h($i['location']) ?>">
                    </div>
                  </div>

                  <div class="inv-edit-actions">
                    <button type="button" class="inv-btn" onclick="toggleEdit(<?= (int)$i['id'] ?>)">ยกเลิก</button>
                    <button class="inv-btn inv-primary">บันทึก</button>
                  </div>
                </form>
              </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$itemsPage): ?>
          <tr><td colspan="7" class="inv-muted">ไม่พบข้อมูล</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="inv-pages">
        <?php
          $baseQS = http_build_query(array_filter(['cat'=>$catFilter]));
          $mk = function($p) use ($baseQS){ return 'inventory.php?'.($baseQS?($baseQS.'&'):'').'page='.$p; };
        ?>
        <a class="inv-page-btn <?= $page<=1?'inv-disabled':'' ?>" href="<?= h($mk(max(1,$page-1))) ?>">← ก่อนหน้า</a>
        <span class="inv-badge">หน้า <?= (int)$page ?> / <?= (int)$totalPages ?></span>
        <a class="inv-page-btn <?= $page>=$totalPages?'inv-disabled':'' ?>" href="<?= h($mk(min($totalPages,$page+1))) ?>">ถัดไป →</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// toggle แถวแก้ไข
function toggleEdit(id){
  const tr = document.getElementById('edit-'+id);
  if (!tr) return;
  tr.style.display = (tr.style.display === 'none' || !tr.style.display) ? 'table-row' : 'none';
}
</script>
<script>
function goCat(cat){
  const p = new URLSearchParams(window.location.search);
  if (cat) p.set('cat', cat); else p.delete('cat');
  p.set('page', '1'); // เริ่มหน้าแรกใหม่เมื่อเปลี่ยนหมวด
  window.location = 'inventory.php?' + p.toString();
}
</script>
