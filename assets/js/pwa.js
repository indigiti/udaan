(()=>{
 if(!('serviceWorker' in navigator))return;
 const manifest=document.querySelector('link[rel="manifest"]');
 const swUrl=manifest?new URL('sw.js',manifest.href).href:'sw.js';
 addEventListener('load',()=>navigator.serviceWorker.register(swUrl,{scope:'./'}).catch(()=>{}));
 let promptEvent=null;
 addEventListener('beforeinstallprompt',e=>{e.preventDefault();promptEvent=e;document.querySelectorAll('[data-pwa-install]').forEach(b=>b.hidden=false)});
 document.addEventListener('click',async e=>{const b=e.target.closest('[data-pwa-install]');if(!b||!promptEvent)return;promptEvent.prompt();try{await promptEvent.userChoice}catch(_){}promptEvent=null;b.hidden=true});
 addEventListener('appinstalled',()=>document.querySelectorAll('[data-pwa-install]').forEach(b=>b.hidden=true));
})();
