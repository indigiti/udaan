(()=>{
 const key='udaan:device-install-id';
 const uuid=()=>crypto.randomUUID?crypto.randomUUID():'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=crypto.getRandomValues(new Uint8Array(1))[0]&15,v=c==='x'?r:(r&3|8);return v.toString(16)});
 let id='';try{id=localStorage.getItem(key)||''}catch(_){};
 if(!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(id)){id=uuid();try{localStorage.setItem(key,id)}catch(_){}}
 document.querySelectorAll('[data-device-install-id],#device-install-id').forEach(el=>{if('value' in el)el.value=id;else el.dataset.deviceInstallId=id});
 window.UdaanDevice={id};
})();
