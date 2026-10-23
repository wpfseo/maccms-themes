/* Original Nightfall interaction layer. No trackers, remote loaders or licensing. */
(() => {
  'use strict';
  const toggle=document.querySelector('.menu-toggle'), nav=document.querySelector('#site-nav');
  toggle?.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';toggle.setAttribute('aria-expanded',String(open));nav?.classList.toggle('is-open',open);});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'){nav?.classList.remove('is-open');toggle?.setAttribute('aria-expanded','false');}});
  const slides=[...document.querySelectorAll('.hero-slide')];let active=0;
  document.querySelectorAll('[data-slide]').forEach(button=>button.addEventListener('click',()=>{
    if(!slides.length)return;active=(active+(button.dataset.slide==='next'?1:-1)+slides.length)%slides.length;
    slides.forEach((slide,i)=>{slide.hidden=i!==active;slide.classList.toggle('is-active',i===active);});
    const counter=document.querySelector('[data-slide-count]');if(counter)counter.textContent=`${active+1} / ${slides.length}`;
  }));
  document.querySelectorAll('img').forEach(img=>img.addEventListener('error',()=>{img.classList.add('image-unavailable');img.removeAttribute('src');},{once:true}));
  document.querySelector('[data-password-form]')?.addEventListener('submit',async event=>{
    event.preventDefault();const form=event.currentTarget,status=form.querySelector('[data-password-status]'),button=form.querySelector('button');button.disabled=true;
    try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});const result=await response.json();
      if(result.code===1){location.reload();return;}status.textContent=result.msg||'验证失败，请稍后重试。';
    }catch(_){status.textContent='暂时无法验证，请检查网络后重试。';}finally{button.disabled=false;}
  });
  const current=location.pathname;document.querySelectorAll('.site-nav a').forEach(a=>{try{if(new URL(a.href).pathname===current)a.setAttribute('aria-current','page');}catch(_){}});
})();
