// admin/assets/js/cctv-checklist.js
;(() => {
  const el  = (s, r=document) => r.querySelector(s);
  const els = (s, r=document) => Array.from(r.querySelectorAll(s));
  const pad2 = n => String(n).padStart(2,'0');

  // ---- config from page ----
  const cfg = Object.assign({
    set:'dahua', prefix:'B', count:17,
    useServer:false, api:{ load:'', save:'' }
  }, window.RHT_CONFIG || {});

  // ---- dom ----
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

  // Basic guards: ถ้า selector ไม่เจอ จะ log ช่วยดีบั๊ก
  if (!thead || !tbody || !tfoot) {
    console.error('[RHT] Missing table DOM (thead/tbody/tfoot). Check IDs.');
  }

  const storageKey   = (y,m)=>`rht-${cfg.set}::${y}-${pad2(m)}`;
  const daysInMonth  = (y,m)=>new Date(y,m,0).getDate();
  const defaultLabels= (n=cfg.count)=>Array.from({length:n},(_,i)=>`${cfg.prefix}${i+1}`);
  const defaultGrid  = (d,c)=>Array.from({length:d},()=>Array.from({length:c},()=>'')); // d rows, c cols
  const pct = (p,t)=>`${t? Math.round(p*10000/t)/100 : 0}%`;
  const getYM = ()=>[parseInt(yearSel.value,10), parseInt(monthSel.value,10)];

  let state = null;

  // -------- persistence ----------
  async function loadData(y,m){
    // server first
    if (cfg.useServer && cfg.api.load) {
      try {
        const res = await fetch(`${cfg.api.load}?year=${y}&month=${m}`, { cache:'no-store' });
        if (res.ok) {
          const obj = await res.json();
          if (obj && obj.data) {
            try { localStorage.setItem(storageKey(y,m), JSON.stringify(obj.data)); } catch {}
            return obj.data;
          }
        }
      } catch(e){ console.warn('[RHT] server load failed, fallback local', e); }
    }
    // local fallback
    try {
      const raw = localStorage.getItem(storageKey(y,m));
      if (raw) return JSON.parse(raw);
    } catch {}
    // default
    const d = daysInMonth(y,m);
    return { labels: defaultLabels(), grid: defaultGrid(d, cfg.count), author:'', note:'' };
  }

  async function saveData(y,m,data){
    // write local immediately
    try { localStorage.setItem(storageKey(y,m), JSON.stringify(data)); } catch {}
    // push to server async
    if (cfg.useServer && cfg.api.save) {
      try {
        await fetch(cfg.api.save, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({ year:y, month:m, data })
        });
      } catch(e){ console.warn('[RHT] server save failed (kept local)', e); }
    }
  }
  // --------------------------------

  function updateRowStats(tr,rowVals){
    const ok  = rowVals.filter(v=>v==='/').length;
    const bad = rowVals.filter(v=>v==='×').length;
    const tds = els('td', tr);
    const camCount = tds.length - 4;
    tds[tds.length-3].textContent = String(ok);
    tds[tds.length-2].textContent = String(bad);
    tds[tds.length-1].textContent = pct(ok, camCount);
  }
  function summarize(data){
    let ok=0,bad=0;
    data.grid.forEach(r=>r.forEach(v=>{
      if (v==='/') ok++;
      else if (v==='×') bad++;
    }));
    return { okTotal: ok, badTotal: bad };
  }

  async function render(){
    try{
      const [y,m] = getYM();
      state = await loadData(y,m);

      thead.innerHTML=''; tbody.innerHTML=''; tfoot.innerHTML='';
      if (authorEl) authorEl.value = state.author || '';
      if (noteEl)   noteEl.value   = state.note   || '';

      // header
      const trHead = document.createElement('tr');
      trHead.appendChild(Object.assign(document.createElement('th'), { textContent:'วันที่' }));
      state.labels.forEach(lbl => {
        const th = document.createElement('th'); th.textContent = lbl; trHead.appendChild(th);
      });
      ['ปกติ','ผิดปกติ','คิดเป็น'].forEach((t,i)=>{
        const th=document.createElement('th'); th.textContent=t; th.className=['rht-stats-ok','rht-stats-bad','rht-stats-pct'][i]; trHead.appendChild(th);
      });
      thead.appendChild(trHead);

      // adjust grid rows by days
      const dCount = daysInMonth(y,m);
      if (state.grid.length !== dCount) {
        const newGrid = defaultGrid(dCount, state.labels.length);
        for (let i=0;i<Math.min(dCount,state.grid.length);i++)
          for (let j=0;j<Math.min(state.labels.length,state.grid[i].length);j++)
            newGrid[i][j] = state.grid[i][j];
        state.grid = newGrid;
        await saveData(y,m,state);
      }

      // body rows
      for (let d=1; d<=dCount; d++){
        const tr = document.createElement('tr');

        const tdDate = document.createElement('td');
        tdDate.textContent = `${d}/${m}/${y+543}`;
        tdDate.className   = 'rht-date';
        tr.appendChild(tdDate);

        let ok=0, bad=0;
        for (let c=0; c<state.labels.length; c++){
          const td = document.createElement('td');
          td.className   = 'rht-cell';
          td.dataset.val = state.grid[d-1][c] || '';
          td.textContent = td.dataset.val;
          td.title       = `วัน ${d}/${m}/${y+543} • ${state.labels[c]}`;
          td.addEventListener('click', async ()=>{
            const seq  = ['','/','×'];
            const cur  = td.dataset.val || '';
            const next = seq[(seq.indexOf(cur)+1)%seq.length];
            td.dataset.val = next;
            td.textContent = next;
            state.grid[d-1][c] = next;
            await saveData(y,m,state);
            updateRowStats(tr, state.grid[d-1]);
            updateFooter();
          });
          if (td.dataset.val==='/') ok++;
          if (td.dataset.val==='×') bad++;
          tr.appendChild(td);
        }

        const tdOk  = document.createElement('td'); tdOk.className='rht-stats-ok';  tdOk.textContent = String(ok);
        const tdBad = document.createElement('td'); tdBad.className='rht-stats-bad'; tdBad.textContent = String(bad);
        const tdPct = document.createElement('td'); tdPct.className='rht-stats-pct'; tdPct.textContent = pct(ok, state.labels.length);
        tr.appendChild(tdOk); tr.appendChild(tdBad); tr.appendChild(tdPct);

        tbody.appendChild(tr);
      }
      updateFooter();
    }catch(err){
      console.error('[RHT] render error', err);
    }
  }

  function updateFooter(){
    if (!state) return;
    tfoot.innerHTML = '';
    const tr  = document.createElement('tr');
    const sum = summarize(state);

    const span = document.createElement('td');
    span.colSpan = 1 + state.labels.length;
    span.textContent = `จำนวนกล้อง: ${state.labels.length} ตัว`;

    const tdOk  = document.createElement('td'); tdOk.className='rht-stats-ok';  tdOk.textContent=String(sum.okTotal);
    const tdBad = document.createElement('td'); tdBad.className='rht-stats-bad'; tdBad.textContent=String(sum.badTotal);
    const total = state.labels.length * state.grid.length;
    const tdPct = document.createElement('td'); tdPct.className='rht-stats-pct'; tdPct.textContent=pct(sum.okTotal, total);

    tr.appendChild(span); tr.appendChild(tdOk); tr.appendChild(tdBad); tr.appendChild(tdPct);
    tfoot.appendChild(tr);
  }

  function bind(){
    monthSel.addEventListener('change', render);
    yearSel .addEventListener('change', render);

    clearBtn.addEventListener('click', async ()=>{
      const [y,m] = getYM();
      if (!confirm('ล้างข้อมูลเดือนนี้ทั้งหมดหรือไม่?')) return;
      state = { labels: defaultLabels(), grid: defaultGrid(daysInMonth(y,m), cfg.count), author:'', note:'' };
      await saveData(y,m,state);
      render();
    });

    exportBtn.addEventListener('click', ()=>{
      const [y,m] = getYM();
      const blob = new Blob([JSON.stringify({year:y, month:m, data:state}, null, 2)], {type:'application/json'});
      const url  = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href=url; a.download=`rht-${cfg.set}-${y}-${pad2(m)}.json`; a.click();
      URL.revokeObjectURL(url);
    });

    importBtn.addEventListener('click', ()=>importFile.click());
    importFile.addEventListener('change', ()=>{
      const file = importFile.files?.[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = async () => {
        try {
          const obj = JSON.parse(reader.result);
          if (!obj || !obj.data) throw 0;
          const [y,m] = getYM();
          state = obj.data;
          await saveData(y,m,state);
          render();
          alert('นำเข้าข้อมูลสำเร็จ');
        } catch { alert('ไฟล์ไม่ถูกต้อง'); }
      };
      reader.readAsText(file);
      importFile.value='';
    });

    printBtn.addEventListener('click', ()=>window.print());

    camBtn.addEventListener('click', async ()=>{
      const txt = prompt('กำหนดป้ายชื่อกล้อง (คั่นด้วย , )', state.labels.join(', '));
      if (txt === null) return;
      const labels = txt.split(',').map(s=>s.trim()).filter(Boolean);
      if (!labels.length) return;
      const d = state.grid.length;
      const newGrid = defaultGrid(d, labels.length);
      for (let i=0;i<d;i++) for (let j=0;j<Math.min(labels.length,state.grid[i].length);j++) newGrid[i][j]=state.grid[i][j];
      state.labels = labels; state.grid = newGrid;
      const [y,m] = getYM();
      await saveData(y,m,state);
      render();
    });

    if (authorEl) authorEl.addEventListener('change', async ()=>{
      const [y,m]=getYM(); state.author = authorEl.value.trim(); await saveData(y,m,state);
    });
    if (noteEl)   noteEl.addEventListener('change', async ()=>{
      const [y,m]=getYM(); state.note   = noteEl.value.trim();   await saveData(y,m,state);
    });
  }

  // flush ล่าสุดก่อนออก/รีเฟรช
  window.addEventListener('beforeunload', () => {
    const [y,m] = getYM(); if (!state) return;
    try {
      localStorage.setItem(storageKey(y,m), JSON.stringify(state));
      if (cfg.useServer && cfg.api.save && navigator.sendBeacon) {
        const blob = new Blob([JSON.stringify({ year:y, month:m, data:state })], { type:'application/json' });
        navigator.sendBeacon(cfg.api.save, blob);
      }
    } catch {}
  });

  document.addEventListener('DOMContentLoaded', ()=>{ bind(); render(); });
})();
