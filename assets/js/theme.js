(()=>{
 const root=document.documentElement,key='udaan-theme';
 const current=()=>root.dataset.theme==='dark'?'dark':'light';
 const apply=t=>{root.dataset.theme=t;try{localStorage.setItem(key,t)}catch(e){};document.querySelectorAll('[data-theme-toggle]').forEach(b=>{b.setAttribute('aria-label',t==='dark'?'Switch to light mode':'Switch to dark mode');b.dataset.mode=t})};
 document.addEventListener('click',e=>{const b=e.target.closest('[data-theme-toggle]');if(!b)return;apply(current()==='dark'?'light':'dark')});
 apply(current());
})();
