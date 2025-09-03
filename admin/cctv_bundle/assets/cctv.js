// CCTV checklist (Hik/Dahua) – fixed widths via <colgroup>, sticky-safe
(() => {
  const $ = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

  const table   = $('#cctv-table');
  const thead   = $('#cctv-thead');
  const tbody   = $('#cctv-tbody');
  const tfoot   = $('#cctv-tfoot');
  const mSel    = $('#cctv-month');
  const ySel    = $('#cctv-year');
  const btnClear= $('#cctv-clear');
  const btnExport=$('#cctv-export');
  const btnImport=$('#cctv-import');
  const fileImport=$('#cctv-file');
  const btnPrint = $('#cctv-print');
  const btnCam   = $('#cctv-cam');
  const authorEl = $('#cctv-author');
  const noteEl   = $('#cctv-note');

  const CFG = Object.assign({ set:'hik', prefix:'A', count:16, useServer:false, api:{} }, (window.CCTV_CONFIG||{}));
  const key = (y,m) => `cctv::${CFG.set}::${y}-${String(m).padStart(2,'0')}`;
  const daysInMonth = (y,m) => new Date(y,m,0).getDate();
  const labelsDefault = () => Array.from({length:CFG.count}, (_,i)=> `${CFG.prefix}${i+1}`);
  const gridDefault = (d,c) => Array.from({length:d}, ()=> Array.from({length:c}, ()=> '') );
  const getYM = () => [parseInt(ySel.value,10), parseInt(mSel.value,10)];
  const pct = (ok,total) => `${ total ? Math.round(ok*10000/total)/100 : 0 }%`;

  let state = null; // {labels, grid, author, note}

  // ---- server/local load/save ------------------------------------------------
  async function load(y,m){
    if (CFG.useServer){
      try{
        const r = await fetch(`${CFG.api.load}?year=${y}&month=${m}`, {cache:'no-store'});
        if (r.ok){ const obj = await r.json(); if (obj && obj.data) return obj.data; }
      }catch(e){}
    }
    try{
      const raw = localStorage.getItem(key(y,m));
      if (raw) return JSON.parse(raw);
    }catch(e){}
    const d = daysInMonth(y,m);
    return { labels: labelsDefault(), grid: gridDefault(d, CFG.count), author:'', note:'' };
  }
  async function save(y,m,data){
    if (CFG.useServer){
      try{
        const r = await fetch(CFG.api.save, {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({year:y, month:m, data})
        });
        if (r.ok) return;
      }catch(e){}
    }
    localStorage.setItem(key(y,m), JSON.stringify(data));
  }
  const deb = {};
  function debounceSave(y,m,data, delay=350){
    const k = `${y}-${m}`;
    if (deb[k]) clearTimeout(deb[k]);
    deb[k] = setTimeout(()=> save(y,m,data), delay);
  }

  // ---- build <colgroup> to force widths -------------------------------------
  function ensureColGroup(camCount){
    let colg = $('colgroup', table);
    if (!colg){
      colg = document.createElement('colgroup');
      table.insertBefore(colg, table.firstChild);
    }
    colg.innerHTML = '';
    // date
    const cDate = document.createElement('col'); cDate.style.width = '110px'; colg.appendChild(cDate);
    // camera cols
    for (let i=0;i<camCount;i++){
      const c = document.createElement('col'); c.style.width = '64px'; colg.appendChild(c);
    }
    // 3 stats
    for (let i=0;i<3;i++){
      const c = document.createElement('col'); c.style.width = '74px'; colg.appendChild(c);
    }
    table.style.tableLayout = 'fixed';
  }

  // ---- UI helpers ------------------------------------------------------------
  function buildHead(labels){
    thead.innerHTML='';
    const tr = document.createElement('tr');
    const thDate = document.createElement('th');
    thDate.textContent = 'วันที่';
    thDate.className = 'cctv-date';
    tr.appendChild(thDate);

    labels.forEach(l=>{
      const th = document.createElement('th');
      th.textContent = l;
      tr.appendChild(th);
    });

    ['ปกติ','ผิดปกติ','คิดเป็น'].forEach(txt=>{
      const th = document.createElement('th');
      th.textContent = txt;
      th.className = 'cctv-stats';
      tr.appendChild(th);
    });

    thead.appendChild(tr);
  }

  function updateRowStats(tr, rowVals){
    const ok = rowVals.filter(v=>v==='/').length;
    const bad = rowVals.filter(v=>v==='×').length;
    const cells = $$('td', tr);
    const last3 = cells.slice(-3);
    last3[0].textContent = ok;
    last3[1].textContent = bad;
    last3[2].textContent = pct(ok, rowVals.length);
  }

  function summarize(data){
    let ok=0,bad=0;
    data.grid.forEach(r=> r.forEach(v=>{ if (v==='/') ok++; else if (v==='×') bad++; }));
    return { ok, bad };
  }

  function buildBody(y,m){
    tbody.innerHTML='';
    const dCount = daysInMonth(y,m);

    // ถ้าจำนวนวันเปลี่ยน ให้ปรับตาราง
    if (state.grid.length !== dCount){
      const newGrid = gridDefault(dCount, state.labels.length);
      for (let i=0;i<Math.min(dCount, state.grid.length); i++){
        for (let j=0;j<Math.min(state.labels.length, state.grid[i].length); j++){
          newGrid[i][j] = state.grid[i][j];
        }
      }
      state.grid = newGrid;
      debounceSave(y,m,state, 100);
    }

    for (let d=1; d<=dCount; d++){
      const tr = document.createElement('tr');

      // date
      const tdDate = document.createElement('td');
      tdDate.textContent = `${d}/${m}/${y+543}`;
      tdDate.className = 'cctv-date';
      tr.appendChild(tdDate);

      // camera cells
      for (let c=0; c<state.labels.length; c++){
        const td = document.createElement('td');
        td.className = 'cctv-cell';
        const cur = state.grid[d-1][c] || '';
        td.dataset.val = cur;
        td.textContent = cur;
        td.title = `วัน ${d}/${m}/${y+543} • ${state.labels[c]}`;
        td.addEventListener('click', ()=>{
          const seq = ['', '/', '×'];
          const next = seq[(seq.indexOf(td.dataset.val||'') + 1) % seq.length];
          td.dataset.val = next;
          td.textContent = next;
          state.grid[d-1][c] = next;
          debounceSave(y,m,state);
          updateRowStats(tr, state.grid[d-1]);
        });
        tr.appendChild(td);
      }

      // stats
      const tdOk  = document.createElement('td');  tdOk.className  = 'cctv-ok';  tdOk.textContent  = '0';
      const tdBad = document.createElement('td');  tdBad.className = 'cctv-bad'; tdBad.textContent = '0';
      const tdPct = document.createElement('td');  tdPct.className = 'cctv-pct'; tdPct.textContent = '0%';
      tr.appendChild(tdOk); tr.appendChild(tdBad); tr.appendChild(tdPct);

      updateRowStats(tr, state.grid[d-1]);
      tbody.appendChild(tr);
    }
  }

  function buildFoot(){
    tfoot.innerHTML='';
    const tr = document.createElement('tr');
    const span = document.createElement('td');
    span.colSpan = 1 + state.labels.length;
    span.textContent = `จำนวนกล้อง: ${state.labels.length} ตัว`;
    const {ok,bad} = summarize(state);
    const totalCells = state.labels.length * state.grid.length;
    const tdOk  = document.createElement('td'); tdOk.className='cctv-ok';  tdOk.textContent = ok;
    const tdBad = document.createElement('td'); tdBad.className='cctv-bad'; tdBad.textContent= bad;
    const tdPct = document.createElement('td'); tdPct.className='cctv-pct'; tdPct.textContent = pct(ok, totalCells);
    tr.appendChild(span); tr.appendChild(tdOk); tr.appendChild(tdBad); tr.appendChild(tdPct);
    tfoot.appendChild(tr);
  }

  async function render(){
    const [y,m] = getYM();
    state = await load(y,m);

    // ความกว้างคอลัมน์แบบล็อกแน่นอน
    ensureColGroup(state.labels.length);

    // header/body/footer
    buildHead(state.labels);
    buildBody(y,m);
    buildFoot();

    // author/note
    if (authorEl) authorEl.value = state.author || '';
    if (noteEl)   noteEl.value   = state.note   || '';
  }

  // ---- bindings --------------------------------------------------------------
  function bind(){
    mSel.addEventListener('change', render);
    ySel.addEventListener('change', render);

    btnClear?.addEventListener('click', async ()=>{
      const [y,m] = getYM();
      if (!confirm('ล้างข้อมูลเดือนนี้ทั้งหมดหรือไม่?')) return;
      state = { labels: labelsDefault(), grid: gridDefault(daysInMonth(y,m), CFG.count), author:'', note:'' };
      await save(y,m,state);
      render();
    });

    btnExport?.addEventListener('click', ()=>{
      const [y,m] = getYM();
      const blob = new Blob([JSON.stringify({year:y, month:m, data:state}, null, 2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `cctv-${CFG.set}-${y}-${String(m).padStart(2,'0')}.json`;
      a.click();
      URL.revokeObjectURL(url);
    });

    btnImport?.addEventListener('click', ()=> fileImport.click());
    fileImport?.addEventListener('change', ()=>{
      const f = fileImport.files?.[0]; if (!f) return;
      const rd = new FileReader();
      rd.onload = async () => {
        try{
          const obj = JSON.parse(rd.result);
          if (!obj || !obj.data) throw 0;
          const [y,m] = getYM();
          state = obj.data;
          await save(y,m,state);
          render();
          alert('นำเข้าข้อมูลสำเร็จ');
        }catch{ alert('ไฟล์ไม่ถูกต้อง'); }
      };
      rd.readAsText(f);
      fileImport.value = '';
    });

    btnPrint?.addEventListener('click', ()=> window.print());

    btnCam?.addEventListener('click', async ()=>{
      const txt = prompt('กำหนดป้ายชื่อกล้อง (คั่นด้วย , )', state.labels.join(', '));
      if (txt===null) return;
      const labels = txt.split(',').map(s=>s.trim()).filter(Boolean);
      if (!labels.length) return;
      const d = state.grid.length;
      const newGrid = gridDefault(d, labels.length);
      for (let i=0;i<d;i++) for (let j=0;j<Math.min(labels.length, state.grid[i].length); j++){
        newGrid[i][j] = state.grid[i][j];
      }
      state.labels = labels;
      state.grid   = newGrid;
      const [y,m]  = getYM();
      await save(y,m,state);
      render();
    });

    authorEl?.addEventListener('change', ()=>{
      const [y,m] = getYM(); state.author = authorEl.value.trim(); debounceSave(y,m,state);
    });
    noteEl?.addEventListener('change', ()=>{
      const [y,m] = getYM(); state.note   = noteEl.value.trim();   debounceSave(y,m,state);
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{ bind(); render(); });
})();
