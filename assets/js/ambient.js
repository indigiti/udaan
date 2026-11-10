/* Homepage-only ambient animation controls; no network requests or animation loop. */
(()=>{
 const body=document.body,button=document.querySelector('[data-ambient-toggle]');
 if(!body||!button)return;
 const key='udaan-ambient-paused';
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 let manual=null;
 try{const saved=localStorage.getItem(key);if(saved==='true'||saved==='false')manual=saved==='true'}catch(_){}
 const update=()=>{
  const paused=manual===null?reduced.matches:manual;
  body.dataset.ambientPaused=paused?'true':'false';
  button.setAttribute('aria-pressed',paused?'true':'false');
  button.setAttribute('aria-label',paused?'Play background animation':'Pause background animation');
  const label=button.querySelector('[data-ambient-label]');if(label)label.textContent=paused?'Play motion':'Pause motion';
 };
 button.addEventListener('click',()=>{
  manual=body.dataset.ambientPaused!=='true';
  try{localStorage.setItem(key,manual?'true':'false')}catch(_){}
  update();
 });
 if(reduced.addEventListener)reduced.addEventListener('change',()=>{if(manual===null)update()});
 else if(reduced.addListener)reduced.addListener(()=>{if(manual===null)update()});
 update();
})();
