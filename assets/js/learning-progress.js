(()=>{
  const TOTAL_DEFAULT=3000;
  const reduced=()=>window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let timerId=null,finishId=null,activeResolve=null;

  const ensure=()=>{
    let root=document.querySelector('[data-learning-progress]');
    if(root)return root;
    root=document.createElement('div');
    root.className='learning-progress';
    root.dataset.learningProgress='';
    root.setAttribute('aria-hidden','true');
    root.innerHTML='<div class="learning-progress-status"><span data-learning-progress-label>Reflect</span><b><span data-learning-progress-seconds>3</span>s</b></div><div class="learning-progress-track"><i data-learning-progress-bar></i></div>';
    document.body.appendChild(root);
    return root;
  };

  const stop=(resolve=true)=>{
    clearInterval(timerId);clearTimeout(finishId);timerId=finishId=null;
    const root=document.querySelector('[data-learning-progress]');
    if(root){root.classList.remove('visible','fade');root.setAttribute('aria-hidden','true');}
    if(resolve&&activeResolve){const r=activeResolve;activeResolve=null;r();}
  };

  const start=(duration=TOTAL_DEFAULT,label='Reflect · next card')=>{
    stop(false);
    duration=Math.max(350,Number(duration)||TOTAL_DEFAULT);
    const root=ensure(),bar=root.querySelector('[data-learning-progress-bar]'),seconds=root.querySelector('[data-learning-progress-seconds]'),labelEl=root.querySelector('[data-learning-progress-label]');
    labelEl.textContent=label;
    let remaining=Math.ceil(duration/1000);
    seconds.textContent=String(remaining);
    root.classList.toggle('reduced-motion',reduced());
    root.classList.remove('fade');root.classList.add('visible');root.setAttribute('aria-hidden','false');
    bar.style.transition='none';bar.style.width='100%';
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      if(!reduced())bar.style.transition=`width ${duration}ms linear`;
      bar.style.width=reduced()?'100%':'0%';
    }));
    const started=Date.now();
    timerId=setInterval(()=>{
      remaining=Math.max(0,Math.ceil((duration-(Date.now()-started))/1000));
      seconds.textContent=String(remaining);
    },200);
    finishId=setTimeout(()=>{root.classList.add('fade');setTimeout(()=>stop(true),260)},Math.max(100,duration-180));
  };

  const wait=(duration=TOTAL_DEFAULT,label='Reflect · next card')=>new Promise(resolve=>{activeResolve=resolve;start(duration,label)});

  document.addEventListener('submit',e=>{
    const form=e.target.closest('form[data-progress-submit]');
    if(!form)return;
    start(Number(form.dataset.progressDuration||TOTAL_DEFAULT),form.dataset.progressLabel||'Working · please wait');
  });

  window.UdaanProgress={start,wait,stop};
})();
