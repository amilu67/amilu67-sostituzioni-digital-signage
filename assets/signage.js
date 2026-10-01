(()=>{
  const esc=(v)=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const rangeLabel=(a,b)=>Number(a)===Number(b)?`${a}ª ora`:`${a}ª–${b}ª ora`;
  const rowHtml=(r)=>{
    const meta=[r.room?`Aula ${r.room}`:'',r.note||''].filter(Boolean).join(' · ')||'—';
    return `<article class="amilu67-sds-board-row${r.status==='pending'?' is-pending':''}">
      <div class="amilu67-sds-period"><strong>${esc(r.period)}ª</strong>${r.start_time?`<small>${esc(r.start_time)}</small>`:''}</div>
      <div class="amilu67-sds-class"><strong>${esc(r.class_name)}</strong>${r.subject?`<small>${esc(r.subject)}</small>`:''}</div>
      <div class="amilu67-sds-person">${esc(r.absent)}</div>
      <div class="amilu67-sds-person amilu67-sds-substitute">${esc(r.substitute||'Da assegnare')}</div>
      <div class="amilu67-sds-meta">${esc(meta)}</div>
    </article>`;
  };
  const updateClock=(root)=>{
    const d=new Date(); const el=root.querySelector('.amilu67-sds-clock-time');
    if(el) el.textContent=d.toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'});
  };
  const refresh=async(root)=>{
    const endpoint=root.dataset.endpoint; if(!endpoint)return;
    const fixed=root.dataset.fixedDate; const url=new URL(endpoint,window.location.origin); if(fixed)url.searchParams.set('date',fixed);
    try{
      const res=await fetch(url.toString(),{headers:{'Accept':'application/json'},cache:'no-store'}); if(!res.ok)return;
      const data=await res.json();
      const rows=root.querySelector('.amilu67-sds-board-rows');
      if(rows){rows.innerHTML=(data.substitutions||[]).length?(data.substitutions||[]).map(rowHtml).join(''):'<div class="amilu67-sds-empty"><strong>Nessuna sostituzione da visualizzare.</strong><span>Il prospetto si aggiorna automaticamente.</span></div>';}
      const absent=root.querySelector('.amilu67-sds-absent-classes'); const chips=root.querySelector('.amilu67-sds-class-chips');
      if(absent&&chips){const cs=data.absent_classes||[]; absent.hidden=!cs.length; chips.innerHTML=cs.map(c=>`<span class="amilu67-sds-chip">${esc(c.class_name)} · ${rangeLabel(c.from_period,c.to_period)}</span>`).join('');}
      const dl=root.querySelector('.amilu67-sds-date-label'); if(dl)dl.textContent=data.date_label||'';
      const ga=root.querySelector('.amilu67-sds-generated-at'); if(ga)ga.textContent=data.generated_at||'';
      const note=root.querySelector('.amilu67-sds-screen-note'); if(note)note.textContent=data.screen_note||'';
    }catch(e){}
  };
  document.querySelectorAll('.amilu67-sds-screen-root').forEach(root=>{
    updateClock(root); setInterval(()=>updateClock(root),1000);
    const seconds=Math.max(10,Number(root.dataset.refresh)||30); setInterval(()=>refresh(root),seconds*1000);
  });
})();

// Fullscreen is intentionally user-triggered: browser security policies reject
// automatic Fullscreen API requests without a user gesture.
(()=>{
  const roots=document.querySelectorAll('.amilu67-sds-screen-root--standalone');
  if(!roots.length)return;
  const button=document.querySelector('.amilu67-sds-fullscreen-toggle');
  if(!button)return;
  const syncState=()=>{
    const active=Boolean(document.fullscreenElement);
    button.setAttribute('aria-label',active?'Esci da schermo intero':'Attiva schermo intero');
    button.setAttribute('title',active?'Esci da schermo intero':'Attiva schermo intero');
  };
  button.addEventListener('click',async()=>{
    try{
      if(document.fullscreenElement){
        await document.exitFullscreen();
      }else if(document.documentElement.requestFullscreen){
        await document.documentElement.requestFullscreen({navigationUI:'hide'});
      }
    }catch(e){
      // The layout already fills the available browser viewport if fullscreen is unavailable.
    }
    syncState();
  });
  document.addEventListener('fullscreenchange',syncState);
  syncState();
})();
