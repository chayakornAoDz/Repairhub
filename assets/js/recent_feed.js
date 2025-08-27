// assets/js/recent_feed.js
(function () {

  // map สถานะ -> CSS class ของ badge (อิงจาก style.css ของคุณ)
  const STATUS_CLASS = {
    'ใหม่': 'good',                 // ฟ้า/เขียว
    'กำลังดำเนินการ': 'warn',       // ส้ม
    'รออะไหล่': 'warn',            // ส้ม
    'เสร็จสิ้น': 'good',            // เขียว
    'ยกเลิก': 'bad'                 // แดง
  };

  // กัน XSS เวลาฝังข้อความ
  function esc(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function fetchRecent() {
    try {
      const r = await fetch('api/requests_feed.php', { cache: 'no-store' });
      const data = await r.json();
      const wrap = document.getElementById('recentFeed');
      if (!wrap) return;

      if (!Array.isArray(data) || data.length === 0) {
        wrap.innerHTML = '<div class="muted">ยังไม่มีรายการ</div>';
        return;
      }

      wrap.innerHTML = data.map(it => {
        const cls = STATUS_CLASS[it.status] || '';
        return `
          <div class="list-item" style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.06)">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
              <b class="mono">${esc(it.ticket_no)}</b>
              <span class="badge ${cls}">${esc(it.status)}</span>
            </div>
            <div class="muted">${esc(it.created_at)} • ${esc(it.category || '-')}</div>
            <div>${esc(it.name || 'ไม่ระบุ')} <span class="muted">${esc(it.department || '')}</span></div>
          </div>
        `;
      }).join('');

    } catch (e) {
      console.error('[recentFeed]', e);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    fetchRecent();
    // รีเฟรชทุก 5 วิ
    setInterval(fetchRecent, 5000);
  });

})();
