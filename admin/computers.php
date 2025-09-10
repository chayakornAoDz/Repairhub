<?php
// admin/computers.php — Computer Inventory (SQLite) with clean button classes

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../inc/functions.php';
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }

/* ---------- CSRF & single-use token ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $_SESSION['form_token'] = bin2hex(random_bytes(16));

$db = db();

/* ---------- Ensure table ---------- */
try{
  $db->exec('PRAGMA journal_mode=WAL;');
  $db->exec('PRAGMA busy_timeout=5000;');
  $exists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='computers'")->fetchColumn();
  if (!$exists){
    $db->exec("
      CREATE TABLE IF NOT EXISTS computers(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_code TEXT, type TEXT NOT NULL, hostname TEXT NOT NULL, user_name TEXT NOT NULL,
        position_name TEXT, department TEXT, brand_model TEXT, cpu TEXT, ram TEXT, storage TEXT,
        os_name TEXT, key_software TEXT, monitor TEXT, printer TEXT, peripherals TEXT,
        ip_address TEXT, location TEXT, purchase_date TEXT, warranty_end TEXT, note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      );
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_c_host ON computers(hostname);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_c_user ON computers(user_name);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_c_dep  ON computers(department);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_c_ip   ON computers(ip_address);");
  }
}catch(Throwable $e){}

/* ---------- helpers ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function input($k){ return trim($_POST[$k] ?? ''); }
function parseDateOrNull($s){ $s=trim($s); if($s==='') return null; $t=strtotime($s); return $t?date('Y-m-d',$t):null; }
function fmt_dmy($s){
  $s = trim((string)$s); if ($s==='') return '';
  $s = substr($s,0,10); [$y,$m,$d]=array_pad(explode('-',$s),3,'');
  $y=(int)$y; $m=(int)$m; $d=(int)$d; if($y>2400) $y-=543;
  if($y<=0||$m<=0||$d<=0) return e($s);
  return sprintf('%02d/%02d/%02d',$d,$m,$y%100);
}

/* ---------- actions (PRG + form token) ---------- */
if ($method==='POST'
    && hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')
    && isset($_POST['form_token'])
    && hash_equals($_SESSION['form_token']??'', $_POST['form_token'])) {

  unset($_SESSION['form_token']);

  if ($action==='save'){
    $id=(int)($_POST['id']??0);
    $f = [
      'asset_code'=>input('asset_code'),'type'=>input('type'),'hostname'=>input('hostname'),
      'user_name'=>input('user_name'),'position_name'=>input('position_name'),
      'department'=>input('department'),'brand_model'=>input('brand_model'),
      'cpu'=>input('cpu'),'ram'=>input('ram'),'storage'=>input('storage'),
      'os_name'=>input('os_name'),'key_software'=>input('key_software'),
      'monitor'=>input('monitor'),'printer'=>input('printer'),'peripherals'=>input('peripherals'),
      'ip_address'=>input('ip_address'),'location'=>input('location'),
      'purchase_date'=>parseDateOrNull(input('purchase_date')),
      'warranty_end'=>parseDateOrNull(input('warranty_end')),'note'=>input('note')
    ];
    if ($id>0){
      $sql="UPDATE computers SET asset_code=:asset_code,type=:type,hostname=:hostname,user_name=:user_name,
            position_name=:position_name,department=:department,brand_model=:brand_model,cpu=:cpu,ram=:ram,
            storage=:storage,os_name=:os_name,key_software=:key_software,monitor=:monitor,printer=:printer,
            peripherals=:peripherals,ip_address=:ip_address,location=:location,purchase_date=:purchase_date,
            warranty_end=:warranty_end,note=:note,updated_at=CURRENT_TIMESTAMP WHERE id=:id";
      $f['id']=$id; $db->prepare($sql)->execute($f); $_SESSION['flash']='อัปเดตแล้ว';
    }else{
      $sql="INSERT INTO computers(asset_code,type,hostname,user_name,position_name,department,brand_model,cpu,ram,storage,os_name,key_software,monitor,printer,peripherals,ip_address,location,purchase_date,warranty_end,note)
            VALUES(:asset_code,:type,:hostname,:user_name,:position_name,:department,:brand_model,:cpu,:ram,:storage,:os_name,:key_software,:monitor,:printer,:peripherals,:ip_address,:location,:purchase_date,:warranty_end,:note)";
      $db->prepare($sql)->execute($f); $_SESSION['flash']='เพิ่มรายการแล้ว';
    }
    header('Location: computers.php'); exit;
  }

  if ($action==='delete'){
    $id=(int)($_POST['id']??0);
    if($id>0){ $db->prepare("DELETE FROM computers WHERE id=?")->execute([$id]); $_SESSION['flash']='ลบแล้ว'; }
    header('Location: computers.php'); exit;
  }
}

/* ---------- filters & list ---------- */
$q=trim($_GET['q']??''); $dep=trim($_GET['dep']??''); $type=trim($_GET['type']??'');
$page=max(1,(int)($_GET['page']??1)); $perpage=5; $offset=($page-1)*$perpage;

$where=" WHERE 1=1 "; $p=[];
if($q!==''){ $where.=" AND (hostname LIKE :q OR user_name LIKE :q OR ip_address LIKE :q OR brand_model LIKE :q) "; $p[':q']="%$q%"; }
if($dep!==''){ $where.=" AND department=:dep "; $p[':dep']=$dep; }
if($type!==''){ $where.=" AND type=:type "; $p[':type']=$type; }

$st=$db->prepare("SELECT COUNT(*) FROM computers $where"); $st->execute($p); $total=(int)$st->fetchColumn();

$st=$db->prepare("SELECT * FROM computers $where ORDER BY department,user_name,hostname LIMIT :l OFFSET :o");
foreach($p as $k=>$v){ $st->bindValue($k,$v); }
$st->bindValue(':l',$perpage,PDO::PARAM_INT); $st->bindValue(':o',$offset,PDO::PARAM_INT);
$st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

$deps  = $db->query("SELECT DISTINCT department FROM computers WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$types = $db->query("SELECT DISTINCT type FROM computers WHERE type IS NOT NULL AND type<>'' ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
$pages = max(1,(int)ceil($total/$perpage));
?>
<?php require __DIR__ . '/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

<style>
/* ---------------- Buttons: NEW CLASSES ---------------- */
:root{
  --btn-h: 44px;        /* ปุ่มมาตรฐาน */
  --btn-h-sm: 34px;     /* ปุ่มเล็ก (ในตาราง) */
  --btn-radius: 12px;
}
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  height:var(--btn-h); min-height:var(--btn-h);
  padding:0 16px; border:0; border-radius:var(--btn-radius);
  font-weight:600; font-size:.95rem; line-height:1; white-space:nowrap;
  color:#fff; text-decoration:none; cursor:pointer; box-sizing:border-box;
  -webkit-appearance:none; appearance:none; margin:0;
}
.btn--sm{ height:var(--btn-h-sm); min-height:var(--btn-h-sm); padding:0 12px; font-size:.85rem; border-radius:10px; }
.btn--block{ width:100%; }
.btn-primary{ background:#0ea5e9; color:#fff; margin-top: 10px}
.btn-success{ background:#22c55e; color:#fff; margin-top: 10px}
.btn-danger{ background:#ef4444; color:#fff; margin-top: 10px}
.btn-slate{ background:#334155; color:#e5e7eb; margin-top: 10px}

/* ---------------- Layout ---------------- */
.page-wrap{ width:100%; padding:24px 24px 40px; box-sizing:border-box; }
.header-bar{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.header-title{ color:#e5e7eb; font-weight:700; font-size:24px; line-height:1.1; margin:0; flex:1 1 260px; }
.header-actions{ display:flex; align-items:center; gap:12px; }

/* จอเล็ก: ปุ่มลงบรรทัดใหม่ 2 ปุ่มแบ่ง 50% */
@media (max-width:768px){
  .header-actions{ width:100%; }
  .header-actions .btn{ flex:1 1 50%; width:auto !important; }
}

/* ---------------- Filters ---------------- */
.input, select, textarea{
  background:#0f172a; color:#e5e7eb; border:1px solid #334155; border-radius:10px;
  padding:0 12px; height:var(--btn-h); box-sizing:border-box;
}
.input::placeholder{ color:#94a3b8; }
.filter-grid{ display:grid; grid-template-columns: 1fr 200px 180px 120px; gap:8px; margin:12px 0 14px;}
@media (max-width:1024px){ .filter-grid{ grid-template-columns:1fr 1fr; } }

/* ---------------- Table ---------------- */
.table-wrap{ overflow-x:auto; }
table.comptable{ width:100%; border-collapse:separate; border-spacing:0; font-size:12px; }
table.comptable thead th{ font-size:11.5px; background:#1f2937; color:#e5e7eb; position:sticky; top:0; z-index:1; }
table.comptable th, table.comptable td{ border:1px solid #334155; padding:.45rem .4rem; color:#e5e7eb; }

/* ปุ่มในตารางให้เล็กและช่องเว้นพอดี */
.actions{ display:flex; flex-direction:column; gap:6px; align-items:stretch; }
.actions .btn{ width:120px; }      /* กำหนดความกว้างพอดีคอลัมน์ */
@media (max-width:1024px){
  table.comptable thead{ display:none; }
  table.comptable, table.comptable tbody, table.comptable tr, table.comptable td{ display:block; width:100%; }
  table.comptable tr{ background:#0f172a; margin-bottom:14px; border:1px solid #334155; border-radius:14px; padding:10px; }
  table.comptable td{ border:none; padding:6px 10px; }
  table.comptable td::before{ content: attr(data-th) " : "; font-weight:600; color:#93c5fd; display:inline-block; min-width:120px; }
  .actions{ flex-direction:row; gap:8px; }
  .actions .btn{ width:auto; }
}

/* ---------------- Pager ---------------- */
.pager{ display:flex; flex-wrap:wrap; gap:6px; margin-top:14px; }
.pager a{ text-decoration:none; padding:.35rem .6rem; border-radius:.5rem; background:#334155; color:#e5e7eb; }
.pager a.active{ background:#4f46e5; }
.pager .muted{
  background:#1f2937;
  color:#64748b;
  padding:.35rem .6rem;
  border-radius:.5rem;
  cursor:not-allowed;
}


/* ---------------- Modal ---------------- */
#formModal{ display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; padding:18px; }
#formModal.show{ display:flex; }
#formModal .modal-box{ background:#fff; color:#0f172a; width:100%; max-width:980px; border-radius:18px; padding:18px; max-height:95vh; overflow:auto; box-shadow:0 10px 40px rgba(0,0,0,.35); }
.form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media (max-width:900px){ .form-grid{ grid-template-columns:1fr; } }
.form-field{ display:flex; flex-direction:column; gap:6px; }
.form-label{ font-weight:600; font-size:.92rem; color:#0f172a; }
.form-control{ background:#fff; border:1px solid #cbd5e1; color:#0f172a; border-radius:10px; padding:10px 12px; }
.form-control:focus{ outline:none; border-color:#60a5fa; box-shadow:0 0 0 3px rgba(59,130,246,.25); }
.flatpickr-input[readonly].form-control{ background:#fff; }
.modal-footer{ display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }

</style>

<div class="page-wrap">
  <div class="header-bar">
    <h1 class="header-title">ทะเบียนคอมพิวเตอร์</h1>
    <div class="header-actions">
      <a id="btnDl"
	   class="btn btn-primary"
	   href="computers_export_pdf.php?q=<?=urlencode($q)?>&dep=<?=urlencode($dep)?>&type=<?=urlencode($type)?>"
	   target="_blank" rel="noopener noreferrer">
	  ดาวน์โหลด PDF
	</a>
      <button id="btnAdd" type="button" class="btn btn-success">+ เพิ่ม</button>
    </div>
  </div>

  <form class="filter-grid" method="get">
    <input type="text" class="input" name="q" value="<?=e($q)?>" placeholder="ค้นหา: ชื่อเครื่อง/ผู้ใช้/IP/รุ่น">
    <select name="dep" class="input">
      <option value="">-- ทุกแผนก --</option>
      <?php foreach($deps as $d): ?><option value="<?=e($d)?>" <?=$dep===$d?'selected':'';?>><?=e($d)?></option><?php endforeach; ?>
    </select>
    <select name="type" class="input">
      <option value="">-- ทุกประเภท --</option>
      <?php foreach($types as $t): ?><option value="<?=e($t)?>" <?=$type===$t?'selected':'';?>><?=e($t)?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary">ค้นหา</button>
  </form>

  <div class="table-wrap">
    <table class="comptable">
      <thead>
        <tr>
          <th>#</th><th>Type</th><th>Computer Name</th><th>ผู้ใช้งาน</th><th>ตำแหน่ง</th>
          <th>แผนก</th><th>ยี่ห้อ/รุ่น</th><th>CPU</th><th>RAM</th><th>HDD/SSD</th>
          <th>OS</th><th>โปรแกรมที่ใช้</th><th>จอภาพ</th><th>Printer</th><th>การต่อพ่วง</th>
          <th>IP/Network</th><th>สถานที่</th><th>ซื้อ</th><th>หมดประกัน</th><th>หมายเหตุ</th><th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=$offset+1; foreach($rows as $r): ?>
        <tr>
          <td data-th="#"><?=$i++;?></td>
          <td data-th="Type"><?=e($r['type'])?></td>
          <td data-th="Computer Name"><?=e($r['hostname'])?></td>
          <td data-th="ผู้ใช้งาน"><?=e($r['user_name'])?></td>
          <td data-th="ตำแหน่ง"><?=e($r['position_name'])?></td>
          <td data-th="แผนก"><?=e($r['department'])?></td>
          <td data-th="ยี่ห้อ/รุ่น"><?=e($r['brand_model'])?></td>
          <td data-th="CPU"><?=e($r['cpu'])?></td>
          <td data-th="RAM"><?=e($r['ram'])?></td>
          <td data-th="HDD/SSD"><?=e($r['storage'])?></td>
          <td data-th="OS"><?=e($r['os_name'])?></td>
          <td data-th="โปรแกรมที่ใช้"><?=e($r['key_software'])?></td>
          <td data-th="จอภาพ"><?=e($r['monitor'])?></td>
          <td data-th="Printer"><?=e($r['printer'])?></td>
          <td data-th="การต่อพ่วง"><?=e($r['peripherals'])?></td>
          <td data-th="IP/Network"><?=e($r['ip_address'])?></td>
          <td data-th="สถานที่"><?=e($r['location'])?></td>
          <td data-th="ซื้อ"><?=fmt_dmy($r['purchase_date'])?></td>
          <td data-th="หมดประกัน"><?=fmt_dmy($r['warranty_end'])?></td>
          <td data-th="หมายเหตุ"><?=e($r['note'])?></td>
          <td data-th="จัดการ">
            <div class="actions">
              <button type="button" class="btn btn-primary btn--sm btn-edit"
                data-row='<?=e(json_encode($r, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT))?>'>แก้ไข</button>
              <form method="post" onsubmit="return confirm('ลบรายการนี้?')" style="margin:0;">
                <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn--sm">ลบ</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; if(!$rows): ?>
          <tr><td colspan="21" style="text-align:center;">ไม่มีข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pager">
  <?php
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    $base = 'computers.php?q='.urlencode($q).'&dep='.urlencode($dep).'&type='.urlencode($type).'&page=';
  ?>

  <?php if ($page > 1): ?>
    <a href="<?= $base.$prev ?>">« ก่อนหน้า</a>
  <?php else: ?>
    <span class="muted">« ก่อนหน้า</span>
  <?php endif; ?>

  <?php for ($p=1; $p <= $pages; $p++): ?>
    <a class="<?= $p==$page ? 'active' : '' ?>" href="<?= $base.$p ?>"><?= $p ?></a>
  <?php endfor; ?>

  <?php if ($page < $pages): ?>
    <a href="<?= $base.$next ?>">ถัดไป »</a>
  <?php else: ?>
    <span class="muted">ถัดไป »</span>
  <?php endif; ?>
</div>


<!-- Modal -->
<div id="formModal">
  <div class="modal-box">
    <h2 class="text-xl" style="font-weight:700;margin:0 0 10px;">เพิ่มเครื่อง</h2>
    <form id="computerForm" method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
      <input type="hidden" name="form_token" value="<?=e($_SESSION['form_token'] ?? '')?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f_id" value="0">

      <div class="form-field"><label class="form-label">รหัสทรัพย์สิน</label><input class="form-control" id="f_asset_code" name="asset_code"></div>
      <div class="form-field"><label class="form-label">ประเภท</label>
        <select class="form-control" id="f_type" name="type" required>
          <option value="">-- ประเภท --</option><option>PC</option><option>NB</option><option>AIO</option><option>Srv</option>
        </select>
      </div>
      <div class="form-field"><label class="form-label">Computer Name</label><input class="form-control" id="f_hostname" name="hostname" required></div>
      <div class="form-field"><label class="form-label">ผู้ใช้งาน</label><input class="form-control" id="f_user_name" name="user_name" required></div>
      <div class="form-field"><label class="form-label">ตำแหน่ง</label><input class="form-control" id="f_position_name" name="position_name"></div>
      <div class="form-field"><label class="form-label">แผนก</label><input class="form-control" id="f_department" name="department"></div>
      <div class="form-field"><label class="form-label">ยี่ห้อ/รุ่น</label><input class="form-control" id="f_brand_model" name="brand_model"></div>
      <div class="form-field"><label class="form-label">CPU</label><input class="form-control" id="f_cpu" name="cpu"></div>
      <div class="form-field"><label class="form-label">RAM</label><input class="form-control" id="f_ram" name="ram"></div>
      <div class="form-field"><label class="form-label">HDD/SSD</label><input class="form-control" id="f_storage" name="storage"></div>
      <div class="form-field"><label class="form-label">OS</label><input class="form-control" id="f_os_name" name="os_name"></div>
      <div class="form-field"><label class="form-label">โปรแกรมที่ใช้</label><input class="form-control" id="f_key_software" name="key_software"></div>
      <div class="form-field"><label class="form-label">จอภาพ</label><input class="form-control" id="f_monitor" name="monitor"></div>
      <div class="form-field"><label class="form-label">Printer</label><input class="form-control" id="f_printer" name="printer"></div>
      <div class="form-field"><label class="form-label">การต่อพ่วง</label><input class="form-control" id="f_peripherals" name="peripherals"></div>
      <div class="form-field"><label class="form-label">IP/Network</label><input class="form-control" id="f_ip_address" name="ip_address"></div>
      <div class="form-field"><label class="form-label">สถานที่/ไซต์งาน</label><input class="form-control" id="f_location" name="location"></div>
      <div class="form-field"><label class="form-label">วันที่ซื้อ</label><input class="form-control datepicker" id="f_purchase_date" name="purchase_date" placeholder="dd/mm/yy"></div>
      <div class="form-field"><label class="form-label">หมดประกัน</label><input class="form-control datepicker" id="f_warranty_end" name="warranty_end" placeholder="dd/mm/yy"></div>
      <div class="form-field" style="grid-column:1/-1;"><label class="form-label">หมายเหตุ</label><textarea class="form-control" id="f_note" name="note" rows="3"></textarea></div>
      <div class="modal-footer" style="grid-column:1/-1;">
        <button type="button" id="btnClose" class="btn btn-slate btn--sm">ยกเลิก</button>
        <button id="btnSubmit" class="btn btn-success btn--sm">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const modal = document.getElementById('formModal');
  const btnAdd = document.getElementById('btnAdd');
  const btnClose = document.getElementById('btnClose');
  const form = document.getElementById('computerForm');
  const btnSubmit = document.getElementById('btnSubmit');
  const btnDl  = document.getElementById('btnDl');

  // flatpickr dd/mm/yy visible, submit Y-m-d
  if (window.flatpickr) {
    flatpickr(".datepicker", { altInput:true, altFormat:"d/m/y", dateFormat:"Y-m-d", locale:"th", allowInput:true });
  }

  function fillForm(row){
    document.querySelector('.modal-box h2').innerText = row ? 'แก้ไขเครื่อง' : 'เพิ่มเครื่อง';
    document.getElementById('f_id').value = row?.id || 0;
    const map={ asset_code:'f_asset_code',type:'f_type',hostname:'f_hostname',user_name:'f_user_name',
      position_name:'f_position_name',department:'f_department',brand_model:'f_brand_model',cpu:'f_cpu',ram:'f_ram',
      storage:'f_storage',os_name:'f_os_name',key_software:'f_key_software',monitor:'f_monitor',printer:'f_printer',
      peripherals:'f_peripherals',ip_address:'f_ip_address',location:'f_location',purchase_date:'f_purchase_date',
      warranty_end:'f_warranty_end',note:'f_note' };
    for(const k in map){
      const el=document.getElementById(map[k]); if(!el) continue;
      if(!row){ el.value=''; if(el._flatpickr) el._flatpickr.clear(); continue; }
      let v=row[k]??''; if((k==='purchase_date'||k==='warranty_end')&&v&&v.length>10) v=v.substring(0,10);
      el.value=v; if(el._flatpickr){ v?el._flatpickr.setDate(v,true,"Y-m-d"):el._flatpickr.clear(); }
    }
  }
  function openModal(row){ modal.classList.add('show'); fillForm(row||null); }
  function closeModal(){ modal.classList.remove('show'); }

  btnAdd?.addEventListener('click', e=>{ e.preventDefault(); openModal(); });
  btnClose?.addEventListener('click', closeModal);
  document.querySelectorAll('.btn-edit').forEach(b=>{
    b.addEventListener('click', ()=>{ try{ openModal(JSON.parse(b.dataset.row)); }catch{ openModal(); } });
  });

  // prevent double submit
  form?.addEventListener('submit', function(){
    btnSubmit.disabled=true; btnSubmit.textContent='กำลังบันทึก...';
  });

  // Make 2 big buttons equal width on desktop only
  function syncBtnWidth(){
    if(!btnDl||!btnAdd) return;
    if (window.innerWidth >= 768){
      btnDl.style.width=''; btnAdd.style.width='';
      const w=Math.max(btnDl.offsetWidth, btnAdd.offsetWidth);
      btnDl.style.width=w+'px'; btnAdd.style.width=w+'px';
    }else{ btnDl.style.width=''; btnAdd.style.width=''; }
  }
  syncBtnWidth(); window.addEventListener('resize', syncBtnWidth); setTimeout(syncBtnWidth,80);

  if(history.replaceState) history.replaceState({}, document.title, location.href);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
