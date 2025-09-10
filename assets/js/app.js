// ===== Utils =====
function toast(msg, ok = true) {
  const el = document.createElement('div');
  el.textContent = msg;
  el.style.position = 'fixed';
  el.style.bottom = '20px';
  el.style.right = '20px';
  el.style.padding = '10px 14px';
  el.style.borderRadius = '12px';
  el.style.background = ok ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
  el.style.border = ok ? '1px solid #10b981' : '1px solid #ef4444';
  el.style.color = '#e5e7eb';
  el.style.zIndex = 9999;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 2800);
}

function autoHeight(e) {
  e.style.height = 'auto';
  e.style.height = (e.scrollHeight + 2) + 'px';
}

document.addEventListener('input', (e) => {
  if (e.target.matches('textarea.auto')) autoHeight(e.target);
});

// ===== Mobile/Tablet Drawer + Submenu =====
document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const trigger = document.querySelector('.nav-trigger');
  const overlay = document.querySelector('.nav-overlay');

  // Drawer open/close
  if (trigger) {
    const setOpen = (open) => {
      body.classList.toggle('nav-open', open);
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      setOpen(!body.classList.contains('nav-open'));
    });

    overlay && overlay.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

    // ถ้ากลับเป็น Desktop ให้ปิด drawer อัตโนมัติ
    const mq = window.matchMedia('(min-width:1025px)');
    const closeOnDesktop = () => setOpen(false);
    if (mq.addEventListener) mq.addEventListener('change', closeOnDesktop);
    else mq.addListener(closeOnDesktop);
  }

  // แตะรายการที่มีเมนูย่อยให้กาง/พับ (เฉพาะ ≤1024px)
  document.querySelectorAll('.menu .has-sub > a').forEach((a) => {
    a.addEventListener('click', (ev) => {
      if (window.matchMedia('(max-width:1024px)').matches) {
        ev.preventDefault();
        a.parentElement.classList.toggle('open');
      }
    });
  });
});


