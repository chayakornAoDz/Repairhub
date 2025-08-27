
function toast(msg, ok=true){
  const el = document.createElement('div');
  el.textContent = msg;
  el.style.position='fixed';
  el.style.bottom='20px'; el.style.right='20px';
  el.style.padding='10px 14px';
  el.style.borderRadius='12px';
  el.style.background= ok ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
  el.style.border = ok ? '1px solid #10b981' : '1px solid #ef4444';
  el.style.color='#e5e7eb'; el.style.zIndex=9999;
  document.body.appendChild(el);
  setTimeout(()=>{ el.remove(); }, 2800);
}

function autoHeight(e){
  e.style.height = 'auto';
  e.style.height = (e.scrollHeight+2)+'px';
}

document.addEventListener('input', (e)=>{
  if(e.target.matches('textarea.auto')) autoHeight(e.target);
});
