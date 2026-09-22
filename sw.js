const VERSION='udaan-v0.14.0';
const STATIC=`${VERSION}-static`;
const CORE=['./offline.html','./manifest.webmanifest','./assets/css/app.css?v=0.14.0','./assets/js/theme.js?v=0.14.0','./assets/js/pwa.js?v=0.14.0','./assets/js/learning-progress.js?v=0.14.0','./assets/js/journey.js?v=0.14.0','./assets/js/vault.js?v=0.14.0','./assets/js/device-id.js?v=0.14.0','./assets/js/content-packs.js?v=0.14.0','./assets/js/offline-learning.js?v=0.14.0','./assets/js/qoi.js?v=0.14.0','./assets/js/share-card.js?v=0.14.0','./offline-learning.php','./assets/js/qr-local.js?v=0.14.0','./assets/icons/icon-192.png','./assets/icons/icon-512.png'];
self.addEventListener('install',e=>e.waitUntil(caches.open(STATIC).then(c=>c.addAll(CORE)).then(()=>self.skipWaiting())));
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('udaan-')&&k!==STATIC).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',e=>{
 const r=e.request;if(r.method!=='GET')return;const u=new URL(r.url);if(u.origin!==location.origin)return;
 if(u.pathname.includes('/admin/')||u.pathname.endsWith('/answer')||u.pathname.endsWith('/state')||u.pathname.endsWith('/reset')||u.pathname.endsWith('/demo-crowd'))return;
 if(r.mode==='navigate'){e.respondWith(fetch(r).catch(async()=>await caches.match('./offline-learning.php')||await caches.match('./offline.html')));return;}
 if(u.pathname.includes('/assets/')||u.pathname.endsWith('/manifest.webmanifest'))e.respondWith(caches.match(r).then(hit=>hit||fetch(r).then(resp=>{const copy=resp.clone();caches.open(STATIC).then(c=>c.put(r,copy));return resp})));
 // Signed content manifests/packs are intentionally not stored in Cache API. They are verified then encrypted into IndexedDB by content-packs.js.
});
