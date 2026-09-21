(()=>{
 const DB='udaan-learning-vault',VER=1,KEY='aes-gcm-v1';let dbp=null,keyp=null;
 const open=()=>dbp||(dbp=new Promise((resolve,reject)=>{const r=indexedDB.open(DB,VER);r.onupgradeneeded=()=>{const d=r.result;if(!d.objectStoreNames.contains('keys'))d.createObjectStore('keys');if(!d.objectStoreNames.contains('records'))d.createObjectStore('records')};r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error)}));
 const tx=(store,mode='readonly')=>open().then(db=>db.transaction(store,mode).objectStore(store));
 const getRaw=async(store,k)=>new Promise(async(res,rej)=>{const s=await tx(store),r=s.get(k);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)});
 const putRaw=async(store,k,v)=>new Promise(async(res,rej)=>{const s=await tx(store,'readwrite'),r=s.put(v,k);r.onsuccess=()=>res(true);r.onerror=()=>rej(r.error)});
 const delRaw=async(store,k)=>new Promise(async(res,rej)=>{const s=await tx(store,'readwrite'),r=s.delete(k);r.onsuccess=()=>res(true);r.onerror=()=>rej(r.error)});
 const key=()=>keyp||(keyp=(async()=>{let k=await getRaw('keys',KEY);if(k)return k;k=await crypto.subtle.generateKey({name:'AES-GCM',length:256},false,['encrypt','decrypt']);await putRaw('keys',KEY,k);return k})());
 const enc=new TextEncoder(),dec=new TextDecoder();
 const bytesToB64=b=>btoa(String.fromCharCode(...new Uint8Array(b))),b64ToBytes=s=>Uint8Array.from(atob(s),c=>c.charCodeAt(0));
 const aadFor=k=>enc.encode('udaan-vault:v1:'+k);
 async function set(k,v){const iv=crypto.getRandomValues(new Uint8Array(12)),ct=await crypto.subtle.encrypt({name:'AES-GCM',iv,additionalData:aadFor(k)},await key(),enc.encode(JSON.stringify(v)));return putRaw('records',k,{v:1,iv:bytesToB64(iv),ct:bytesToB64(ct),updated_at:new Date().toISOString()})}
 async function get(k){const row=await getRaw('records',k);if(!row)return null;try{const pt=await crypto.subtle.decrypt({name:'AES-GCM',iv:b64ToBytes(row.iv),additionalData:aadFor(k)},await key(),b64ToBytes(row.ct));return JSON.parse(dec.decode(pt))}catch(e){return null}}
 async function del(k){return delRaw('records',k)}
 async function ready(){if(!window.crypto?.subtle||!window.indexedDB)throw new Error('Encrypted offline vault is unavailable in this browser');await key();return true}
 window.UdaanVault={ready,set,get,del,encrypted:true,db:DB};
})();
