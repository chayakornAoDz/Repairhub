// assets/js/dashboard_chart.js
// วาดกราฟแท่งแบบ Grouped จาก window.chartData (ไม่เรียก API)
(function(){
  function color(i){
    const p = ['#22c55e','#3b82f6','#ef4444','#f59e0b','#8b5cf6','#06b6d4','#eab308','#f472b6'];
    return p[i % p.length];
  }
  function legendHTML(cats){
    return cats.map((c,i)=>`
      <span style="display:inline-flex;align-items:center;gap:6px;margin-right:12px">
        <span style="width:10px;height:10px;border-radius:2px;background:${color(i)};display:inline-block"></span>${c}
      </span>`).join('');
  }
  function tipHTML(month, cat, val){
    return `<div style="font-weight:600;margin-bottom:6px">${month}</div>
            <div style="display:flex;justify-content:space-between;gap:16px">
              <span>${cat}</span><b>${val}</b>
            </div>`;
  }

  function run(){
    const data = window.chartData;
    if(!data) return;

    const bar = document.getElementById('bar6');
    const labelsEl = document.getElementById('bar6Labels');
    const legendEl = document.getElementById('bar6Legend');
    if(!bar || !labelsEl) return;

    // tooltip
    let tip = document.getElementById('chartTip');
    if(!tip){
      tip = document.createElement('div');
      tip.id = 'chartTip';
      Object.assign(tip.style, {
        position:'fixed', zIndex:9999, pointerEvents:'none',
        background:'#0b1222', border:'1px solid #334155',
        boxShadow:'0 10px 30px rgba(0,0,0,.35)', borderRadius:'12px',
        padding:'10px 12px', fontSize:'12px', color:'#e5e7eb', display:'none'
      });
      document.body.appendChild(tip);
    }

    const labels = data.labels || [];
    const cats   = data.categories || [];
    const M      = data.matrix || [];
    const max    = Math.max(1, data.max || 1);

    if(legendEl) legendEl.innerHTML = legendHTML(cats);

// ค่ากว้าง/ช่องว่างต่อแท่งและต่อกลุ่ม
const barW = 14;                  // px ต่อแท่ง
const gap  = 6;                   // ช่องไฟระหว่างแท่ง
const groupPaddingLR = 16;        // padding ซ้าย/ขวาของกลุ่ม
const groupMinWidth  = Math.max(110, (cats.length || 1) * (barW + gap) + groupPaddingLR*2);

// ให้ container ของกราฟเว้นระยะเล็กน้อย และยืดเต็มความกว้าง
bar.style.gap = '12px';

// วาดกลุ่มต่อเดือน (ให้ยืดเต็มการ์ด)
bar.innerHTML = M.map((row, mi) => {
  const inner = (row || []).map((v, ci) => {
    const h = Math.round((v / max) * 100);  // สูงเป็น %
    const clr = color(ci);
return `<div class="barItem" data-mi="${mi}" data-ci="${ci}"
              style="height:${h}%; background:${clr};
                     flex:1; margin:0 3px; border-radius:6px 6px 0 0"></div>`;

  }).join('');

  return `
    <div class="group"
         style="
           /* ทำให้แต่ละเดือนยืดเต็มพื้นที่เท่า ๆ กัน */
           flex: 1 1 0;
           min-width:${groupMinWidth}px;
           height:100%;
           display:flex; align-items:flex-end;
           border:1px solid #1f2937; border-radius:8px; background:#0b1222;
           padding:8px ${groupPaddingLR}px;">
      <div class="group-inner" style="display:flex; align-items:flex-end; height:100%; width:100%;">${inner}</div>
    </div>`;
}).join('');

// labels ล่าง (ให้ยืดเต็มและเรียงเท่ากัน)
labelsEl.innerHTML = labels.map(() =>
  `<span style="
      flex: 1 1 0;
      min-width:${groupMinWidth}px;
      text-align:center; display:inline-block"></span>`
).join('');
labelsEl.querySelectorAll('span').forEach((el, i) => el.textContent = labels[i] || '');
labelsEl.style.display   = 'flex';
labelsEl.style.gap       = '12px';
labelsEl.style.overflowX = 'auto';

    // เติมข้อความทีหลัง เพื่อไม่ให้ติดกัน
    labelsEl.querySelectorAll('span').forEach((el, i) => el.textContent = labels[i] || '');

    labelsEl.style.display   = 'flex';
    labelsEl.style.gap       = '10px';
    labelsEl.style.overflowX = 'auto';

    // tooltip ต่อแท่ง
    bar.querySelectorAll('.barItem').forEach(el => {
      const mi = +el.getAttribute('data-mi');
      const ci = +el.getAttribute('data-ci');
      el.addEventListener('mouseenter', () => {
        const val = (M[mi] && typeof M[mi][ci] !== 'undefined') ? M[mi][ci] : 0;
        tip.innerHTML = tipHTML(labels[mi] || '', cats[ci] || '', val);
        tip.style.display = 'block';
      });
      el.addEventListener('mousemove', (e) => {
        tip.style.left = (e.clientX + 12) + 'px';
        tip.style.top  = (e.clientY + 12) + 'px';
      });
      el.addEventListener('mouseleave', () => tip.style.display = 'none');
    });
  }

  document.addEventListener('DOMContentLoaded', run);
})();
