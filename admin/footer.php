
  </main>
</div>
<script>
  // เปิด/ปิดเมนูย่อยด้วย "แตะ" สำหรับอุปกรณ์ไม่มี hover
  document.querySelectorAll('.menu .has-sub > a').forEach(a => {
    a.addEventListener('click', (e) => {
      if (window.matchMedia('(hover: none)').matches) {
        e.preventDefault();
        a.parentElement.classList.toggle('open');
      }
    });
  });
  document.querySelectorAll('a[href^="?page="]').forEach(a=>{
    const file = location.pathname.split('/').pop() || 'index.php';
    a.href = file + a.getAttribute('href');
  });
</script>
</body></html>
