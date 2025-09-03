// assets/js/dashboard_chart.js  (v8)
(function () {
  console.log('[pie] loaded v8');

  const COLORS = ['#22d3ee','#60a5fa','#a78bfa','#34d399','#fbbf24','#f472b6','#f87171','#94a3b8'];
  const esc = s => String(s ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  function seriesForMonth(mi, data){
    const cats = data.categories || [];
    const row  = (data.matrix || [])[mi] || [];
    return cats.map((label, i) => ({ label, value: +row[i] || 0 }));
  }

  function drawDonut(svgEl, series) {
    if (!svgEl) return;
    while (svgEl.firstChild) svgEl.removeChild(svgEl.firstChild);

    const ns = 'http://www.w3.org/2000/svg';
    svgEl.setAttribute('viewBox','-50 -50 100 100');

    const rect = svgEl.getBoundingClientRect();
    const size = Math.max(140, Math.min(rect.width || 200, rect.height || 200));
    const R = Math.round(size * 0.30);
    const W = Math.max(10, Math.round(size * 0.18));
    const C = 2 * Math.PI * R;

    const g = document.createElementNS(ns, 'g');
    g.setAttribute('transform', 'rotate(-90)');
    svgEl.appendChild(g);

    const track = document.createElementNS(ns, 'circle');
    track.setAttribute('r', R); track.setAttribute('cx', 0); track.setAttribute('cy', 0);
    track.setAttribute('fill','none'); track.setAttribute('stroke','#1f2937'); track.setAttribute('stroke-width', W);
    g.appendChild(track);

    const total = Math.max(1, series.reduce((s,x)=>s+(x.value||0),0));
    let offset = 0;
    series.forEach((s,i)=>{
      const v = Math.max(0, +s.value || 0); if (!v) return;
      const len = C * (v/total);
      const seg = document.createElementNS(ns, 'circle');
      seg.setAttribute('r',R); seg.setAttribute('cx',0); seg.setAttribute('cy',0);
      seg.setAttribute('fill','none'); seg.setAttribute('stroke', COLORS[i%COLORS.length]);
      seg.setAttribute('stroke-width',W); seg.setAttribute('stroke-linecap','butt');
      seg.setAttribute('stroke-dasharray', `${len} ${C-len}`);
      seg.setAttribute('stroke-dashoffset', -offset);
      g.appendChild(seg);
      offset += len;          // สำคัญ: ขยับจุดเริ่มของชิ้นถัดไป
    });
  }

  function legendHTML(series){
    const total = Math.max(1, series.reduce((s,x)=>s+(x.value||0),0));
    return series.map((s,i)=>{
      const pct = Math.round((s.value/total)*100);
      return `<div style="display:flex;align-items:center;gap:8px;margin:6px 0;">
        <span style="width:10px;height:10px;border-radius:2px;background:${COLORS[i%COLORS.length]};display:inline-block"></span>
        <span style="flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(s.label)}</span>
        <span style="opacity:.85">${esc(s.value)}</span>
        <span style="opacity:.6;min-width:36px;text-align:right">(${pct}%)</span>
      </div>`;
    }).join('');
  }

  function renderInto(svg, legend, monthIndex, data){
    const series = seriesForMonth(monthIndex, data);
    drawDonut(svg, series);
    if (legend) legend.innerHTML = legendHTML(series);
  }

  function renderAll(){
    const data = window.chartData;
    if (!data || !data.months || !data.months.length) {
      console.warn('[pie] no chartData');
      return;
    }
    const n = Math.min(3, data.months.length);
    const start = data.months.length - n;
    const idxs = Array.from({length:n},(_,k)=> start+k);

    // รองรับทั้ง .pie-card[data-mi] และ #pie0..2 / #pieLegend0..2
    const cards = Array.from(document.querySelectorAll('.pie-card[data-mi]'));
    if (cards.length){
      cards.slice(0,n).forEach((card,i)=>{
        renderInto(card.querySelector('svg'), card.querySelector('.pie-legend'), idxs[i], data);
      });
    } else {
      for (let i=0;i<n;i++){
        renderInto(
          document.getElementById('pie'+i) || document.querySelector(`svg[data-pie="${i}"]`),
          document.getElementById('pieLegend'+i) || document.querySelector(`.pie-legend[data-pie="${i}"]`),
          idxs[i], data
        );
      }
    }
  }

  function boot(){
    if (window.chartData && window.chartData.months && window.chartData.months.length) {
      renderAll();
    } else {
      console.warn('[pie] no chartData yet; waiting…');
      window.addEventListener('chartdata-ready', renderAll, { once:true });
      // เผื่อกรณีไม่ยิง event — โพลทุก 120ms สูงสุด ~6s
      let tries=0;
      const t=setInterval(()=>{
        if (window.chartData && window.chartData.months && window.chartData.months.length){
          clearInterval(t); renderAll();
        } else if (++tries>50){ clearInterval(t); }
      },120);
    }
  }

  document.addEventListener('DOMContentLoaded', boot);
  window.addEventListener('resize', ()=> window.chartData && renderAll());
})();
