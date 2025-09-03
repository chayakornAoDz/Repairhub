// ===== CCTV Checklist (RHT) — PHP module JS =====
;(() => {
  const el = (s, r=document) => r.querySelector(s);
  const els = (s, r=document) => Array.from(r.querySelectorAll(s));
  const MONTHS_TH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

  const monthSel = el('#rht-month');
  const yearSel  = el('#rht-year');
  const thead    = el('#rht-thead');
  const tbody    = el('#rht-tbody');
  const tfoot    = el('#rht-tfoot');
  const clearBtn = el('#rht-clear-month');
  const exportBtn= el('#rht-export');
  const importBtn= el('#rht-import');
  const importFile=el('#rht-import-file');
  const printBtn = el('#rht-print');
  const camBtn   = el('#rht-cam-config');
  const authorEl = el('#rht-author');
  const noteEl   = el('#rht-note');

  const cfg = window.RHT_CONFIG || {useServer:false, api:{}};

  const storageKey = (y,m) => `rht-dahua::${y}-${String(m).padStart(2,'0')}`;
  const daysInMonth = (y,m) => new Date(y,m,0).getDate();
  const defaultLabels = (n=17) => Array.from({length:n}, (_,i)=>`B${i+1}`);
  const defaultGrid = (d,c) => Array.from({length:d}, ()=>Array.from({length:c}, ()=>''));

  let memo = null;
  const getYM = () => [parseInt(yearSel.value,10), parseInt(monthSel.value,10)];
  const pct = (p,t) => `${t? Math.round(p*10000/t)/100 : 0}%`;

  // ---------- Persistence (แก้หลัก) ----------
  // โหลด: server -> (fallback) localStorage -> default
  async function loadData(y,m){
    if (cfg.useServer) {
      try {
        const res = await fetch(`${cfg.api.load}?year=${y}&month=${m}`, {cache:'no-store'});
        if (res.ok) {
          const obj = await res.json();
          if (obj && obj.data) {
            try { localStorage.setItem(storageKey(y,m), JSON.stringify(obj.data)); } catch {}
            return obj.data;
          }
        }
      } catch (e) { console.warn('server load failed', e); }
    }
    try { const raw = localStorage.getItem(storageKey(y,m)); if (raw) return JSON.parse(raw); } catch {}
    const d = daysInMonth(y,m);
    return {labels: defaultLabels(), grid: defaultGrid(d,17), author:'', note:''};
  }

  // บันทึกทันทีทุกครั้ง (ไม่ debounce)
  function debounceSave(y,m,data,delay=0){ saveData(y,m,data); }

  // เขียน localStorage ก่อนเสมอ แล้วค่อยยิงขึ้น server
  async function saveData(y,m,data){
    try { localStorage.setItem(storageKey(y,m), JSON.stringify(data)); } catch {}
    if (cfg.useServer) {
      try {
        await fetch(cfg.api.save,{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({year:y,month:m,data})
        });
      } catch(e){ console.warn('server save failed (kept in localStorage)', e); }
    }
  }
  // -------------------------------------------

  funct
