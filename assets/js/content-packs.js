(()=>{
 const TRUST_EPOCH=2;
 const enc=new TextEncoder();
 const b64u=s=>{s=s.replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';return Uint8Array.from(atob(s),c=>c.charCodeAt(0))};
 const hex=buf=>[...new Uint8Array(buf)].map(b=>b.toString(16).padStart(2,'0')).join('');
 const sha=async s=>hex(await crypto.subtle.digest('SHA-256',enc.encode(s)));
 const packBase=p=>({schema_version:p.schema_version,pack_id:p.pack_id,pillar:p.pillar,pillar_name:p.pillar_name,sequence:p.sequence,card_count:p.card_count,bank_version:p.bank_version,cards:p.cards});
 const manifestBase=m=>({schema_version:m.schema_version,trust_epoch:m.trust_epoch,distribution:m.distribution,generated_at:m.generated_at,bank_version:m.bank_version,bank_sha256:m.bank_sha256,total_cards:m.total_cards,pillar_count:m.pillar_count,pack_size:m.pack_size,pack_count:m.pack_count,signature_alg:m.signature_alg,signing_key_id:m.signing_key_id,public_key:m.public_key,packs:m.packs});
 async function keyFrom(raw){return crypto.subtle.importKey('raw',b64u(raw),{name:'Ed25519'},false,['verify'])}
 async function verifySig(publicKey,payload,sig){return crypto.subtle.verify({name:'Ed25519'},publicKey,b64u(sig),enc.encode(payload))}
 async function verifyManifest(m){
  if(!m||m.signature_alg!=='Ed25519'||!m.public_key||!m.manifest_signature)throw new Error('Unsigned content manifest');
  const epoch=Number(m.trust_epoch||1);if(!Number.isInteger(epoch)||epoch<1)throw new Error('Invalid content trust epoch');if(epoch>TRUST_EPOCH)throw new Error(`Udaan app update required for content trust epoch ${epoch}`);
  const payload=JSON.stringify(manifestBase(m)),digest=await sha(payload);if(digest!==m.manifest_sha256)throw new Error('Manifest hash mismatch');
  const pub=await keyFrom(m.public_key);if(!(await verifySig(pub,payload,m.manifest_signature)))throw new Error('Manifest signature invalid');return pub;
 }
 async function verifyPack(p,pub,expected){
  const payload=JSON.stringify(packBase(p)),digest=await sha(payload);if(digest!==p.sha256||digest!==expected.sha256)throw new Error(`Pack integrity failed: ${p.pack_id}`);
  if(p.signature!==expected.signature)throw new Error(`Pack signature mismatch: ${p.pack_id}`);if(!(await verifySig(pub,payload,p.signature)))throw new Error(`Pack signature invalid: ${p.pack_id}`);return true;
 }
 function selectPacks(m,maxCards){
  const groups={};for(const p of m.packs||[])(groups[p.pillar]??=[]).push(p);Object.values(groups).forEach(a=>a.sort((x,y)=>(x.sequence||0)-(y.sequence||0)));
  const pillars=Object.keys(groups).sort(),selected=[],idx=Object.fromEntries(pillars.map(p=>[p,0]));let total=0,progress=true;
  while(progress&&total<maxCards){progress=false;for(const pillar of pillars){const p=groups[pillar][idx[pillar]++];if(!p)continue;if(total+(p.cards||0)>maxCards&&selected.length)continue;selected.push(p);total+=p.cards||0;progress=true;if(total>=maxCards)break;}}
  return selected;
 }
 async function sync(opts={}){
  if(!window.UdaanVault||!navigator.onLine||!crypto.subtle)return {ok:false,reason:'offline'};await UdaanVault.ready();
  const manifestUrl=opts.manifestUrl||new URL('content/manifest',location.href).href,maxCards=Math.max(9,Number(opts.maxCards||1008));
  const r=await fetch(manifestUrl,{credentials:'same-origin',headers:{Accept:'application/json'}}),j=await r.json();if(!r.ok||!j.ok||!j.manifest)throw new Error(j.error||'Content manifest unavailable');const m=j.manifest;
  let pub;try{pub=await verifyManifest(m)}catch(e){if(String(e).includes('Ed25519'))throw new Error('This browser cannot verify Udaan signed packs');throw e}
  const manifestEpoch=Number(m.trust_epoch||1),trust=await UdaanVault.get('content-trust'),trustedEpoch=Number(trust?.trust_epoch||1);
  let rotated=false;
  if(trust?.public_key&&trust.public_key!==m.public_key){
   if(!(manifestEpoch===TRUST_EPOCH&&manifestEpoch>trustedEpoch))throw new Error('Udaan content signing key changed outside an approved trust epoch');
   rotated=true;await UdaanVault.del('content-pack-index');
  }
  const now=new Date().toISOString();
  await UdaanVault.set('content-trust',{version:2,trust_epoch:manifestEpoch,public_key:m.public_key,key_id:m.signing_key_id,first_trusted_at:rotated?now:(trust?.first_trusted_at||now),previous_key_id:rotated?(trust?.key_id||null):(trust?.previous_key_id||null),rotated_at:rotated?now:(trust?.rotated_at||null),last_verified_at:now,source:'https-origin'});
  const selected=selectPacks(m,maxCards),kept=[];let total=0;
  for(const meta of selected){
   const key=`content-pack:${meta.pack_id}:${meta.sha256}`;let cached=await UdaanVault.get(key);
   if(!cached){const pr=await fetch(meta.url,{credentials:'same-origin',headers:{Accept:'application/json'}}),pj=await pr.json();if(!pr.ok||!pj.ok||!pj.pack)throw new Error(pj.error||`Pack unavailable: ${meta.pack_id}`);await verifyPack(pj.pack,pub,meta);cached={version:2,trust_epoch:manifestEpoch,key_id:m.signing_key_id,pack:pj.pack,verified_at:new Date().toISOString()};await UdaanVault.set(key,cached)}
   kept.push({pack_id:meta.pack_id,sha256:meta.sha256,key,cards:meta.cards,pillar:meta.pillar});total+=meta.cards||0;
  }
  await UdaanVault.set('content-pack-index',{version:2,trust_epoch:manifestEpoch,manifest_sha256:m.manifest_sha256,bank_version:m.bank_version,key_id:m.signing_key_id,capacity:maxCards,card_count:total,packs:kept,updated_at:new Date().toISOString()});
  return {ok:true,cards:total,packs:kept.length,bank_version:m.bank_version,key_id:m.signing_key_id,trust_epoch:manifestEpoch,rotated};
 }
 async function stats(){if(!window.UdaanVault)return null;return UdaanVault.get('content-pack-index')}
 window.UdaanContentPacks={sync,stats,verifyManifest,selectPacks,trustEpoch:TRUST_EPOCH};
})();
