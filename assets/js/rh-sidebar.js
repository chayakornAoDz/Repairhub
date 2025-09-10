// RepairHub Lite - Sidebar controller
// Desktop: collapsed by default, hover to expand
// Mobile: fixed rail by default, tap the rail to expand, tap backdrop/Esc to close

(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const body = document.body;
  const MOBILE_BP = 1024;
  const storageKey = 'rh-sidebar-collapsed'; // desktop only

  const isMobile = () => window.matchMedia(`(max-width:${MOBILE_BP}px)`).matches;

  /* ---------- Desktop collapsed state ---------- */
  function setCollapsedDesktop(on){
    body.classList.toggle('rh-collapsed', !!on);
    try { localStorage.setItem(storageKey, on ? '1' : '0'); } catch(e){}
  }

  function initDesktop(){
    // เริ่มต้น: collapsed (ถ้าไม่เคยมีค่า จะให้ collapsed = true)
    let saved = null; try{ saved = localStorage.getItem(storageKey); }catch(e){}
    const shouldCollapse = (saved === null) ? true : (saved === '1');
    setCollapsedDesktop(shouldCollapse);

    // hover เพื่อกาง
    const sidebar = $('.rh-sidebar');
    if (sidebar){
      sidebar.addEventListener('mouseenter', () => body.classList.add('rh-hover'));
      sidebar.addEventListener('mouseleave', () => body.classList.remove('rh-hover'));
    }

    // ปุ่ม toggle (ถ้ามีใน DOM)
    $('#rh-toggle-desktop')?.addEventListener('click', () => {
      const now = body.classList.contains('rh-collapsed');
      setCollapsedDesktop(!now);
    });
  }

  /* ---------- Mobile: rail fixed + tap to expand ---------- */
  function openMobile(){ body.classList.add('rh-mobile-expanded'); }
  function closeMobile(){ body.classList.remove('rh-mobile-expanded'); }

  function initMobile(){
    // ปิด overlay/expanded ทุกครั้งที่เข้า mobile mode ใหม่
    closeMobile();

    const sidebar  = $('.rh-sidebar');
    const backdrop = $('.rh-backdrop');

    // แตะ rail เพื่อกาง (ถ้าขณะนั้นยังไม่กาง)
    sidebar?.addEventListener('click', (e) => {
      if (!isMobile()) return;
      if (!body.classList.contains('rh-mobile-expanded')) {
        e.preventDefault();
        openMobile();
      }
    });

    // แตะฉากดำเพื่อหุบ
    backdrop?.addEventListener('click', () => { if (isMobile()) closeMobile(); });

    // กด Esc เพื่อหุบ
    document.addEventListener('keydown', (e) => {
      if (isMobile() && e.key === 'Escape') closeMobile();
    });
  }

  /* ---------- Watch resize: สลับโหมดให้ถูกต้อง ---------- */
  function applyMode(){
    if (isMobile()){
      // ปิด hover/collapsed class ที่ใช้เฉพาะ desktop
      body.classList.remove('rh-hover');
      initMobile();
    }else{
      closeMobile();
      initDesktop();
    }
  }

  // เริ่มทำงาน
  document.addEventListener('DOMContentLoaded', () => {
    applyMode();
    // สลับโหมดเมื่อเปลี่ยนขนาดจอ
    const mq = window.matchMedia(`(max-width:${MOBILE_BP}px)`);
    const onChange = () => applyMode();
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else mq.addListener(onChange);
  });
})();
