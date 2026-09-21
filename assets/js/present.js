(()=>{
 const root=document.documentElement,stateUrl=root.dataset.stateUrl;
 const qr=document.querySelector('[data-qr-value]');
 if(qr){try{if(!window.UdaanQR)throw new Error('local QR engine unavailable');window.UdaanQR.render(qr,qr.dataset.qrValue,{label:'Scan to join this Udaan session'});}catch(e){qr.innerHTML='<div class=\"qr-error\"><strong>Open join link</strong><small>QR could not render in this browser.</small></div>';}}
 const esc=s=>String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
 const draw=async()=>{try{const r=await fetch(stateUrl,{cache:'no-store',credentials:'same-origin'}),j=await r.json();if(!j.ok)return;const s=j.state;
  document.querySelector('[data-participants]').textContent=s.participants;document.querySelector('[data-completed]').textContent=s.completed;document.querySelector('[data-progress]').textContent=s.avg_progress_pct+'%';
  const labels=s.future_labels||{},entries=Object.entries(s.future||{}).sort((a,b)=>b[1]-a[1]).slice(0,6),total=entries.reduce((a,x)=>a+x[1],0)||1,bars=document.querySelector('[data-bars]');bars.innerHTML=entries.length?'':'<div class="small-note">Future-interest signals appear as learners reach those cards.</div>';
  entries.forEach(([k,n])=>{const p=Math.round(n/total*100),label=labels[k]||k.replace(/[-_]/g,' ');bars.insertAdjacentHTML('beforeend',`<div class="bar-row"><span>${esc(label)}</span><div class="bar-track"><i style="width:${p}%"></i></div><b>${p}%</b></div>`)});
  const ladder=document.querySelector('[data-ladder]');ladder.innerHTML=s.ladder.length?s.ladder.map(x=>`<div class="ladder-row"><span>${String(x.rank).padStart(2,'0')}</span><strong>${esc(x.nickname)}${x.completed?' · ✓':''}<small>${x.progress}/${x.total}</small></strong><b>${x.score}</b></div>`).join(''):'<div class="small-note">Waiting for participants…</div>';
  document.querySelector('[data-top-future]').textContent=s.top_future?(labels[s.top_future]||s.top_future):'—';
 }catch(e){}};
 draw();setInterval(draw,1500);
 document.querySelector('[data-copy]')?.addEventListener('click',async e=>{try{await navigator.clipboard.writeText(e.currentTarget.dataset.copy);e.currentTarget.textContent='Copied';setTimeout(()=>e.currentTarget.textContent='Copy join link',1000)}catch(_){}});
})();
