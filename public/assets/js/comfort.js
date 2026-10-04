(() => {
  const walkin=document.getElementById('walkin-box'),toggle=document.querySelector('[data-walkin-toggle]');
  function setWalkin(open){if(!walkin||!toggle)return;walkin.classList.toggle('hidden',!open);walkin.hidden=!open;toggle.setAttribute('aria-expanded',String(open));if(open){walkin.scrollIntoView({block:'nearest'});walkin.querySelector('input:not([type=hidden])')?.focus();}else toggle.focus();}
  toggle?.addEventListener('click',()=>setWalkin(walkin.classList.contains('hidden')));
  document.querySelector('[data-walkin-close]')?.addEventListener('click',()=>setWalkin(false));
  const tabsHost=document.querySelector('[data-settings-tabs]');
  if(tabsHost){
    const main=tabsHost.closest('main');
    const panels=[...main.querySelectorAll('h2.card-title')].map(h=>({title:h.textContent.trim(),node:h.closest('.glass')})).filter((p,i,all)=>p.node&&all.findIndex(x=>x.node===p.node)===i);
    if(panels.length){
      tabsHost.className='settings-tabs';tabsHost.setAttribute('role','tablist');tabsHost.setAttribute('aria-label','بخش‌های تنظیمات');main.classList.add('settings-enhanced');
      let active=0;try{active=Math.min(panels.length-1,Math.max(0,Number(sessionStorage.getItem('reshen-settings-tab'))||0));}catch(_){}
      function select(index,focus=false){panels.forEach((p,i)=>{p.node.hidden=i!==index;p.tab.setAttribute('aria-selected',String(i===index));p.tab.tabIndex=i===index?0:-1;});try{sessionStorage.setItem('reshen-settings-tab',String(index));}catch(_){}if(focus)panels[index].tab.focus();}
      panels.forEach((p,i)=>{p.node.id='settings-panel-'+i;p.node.setAttribute('role','tabpanel');p.node.setAttribute('aria-labelledby','settings-tab-'+i);p.node.tabIndex=0;const tab=document.createElement('button');tab.type='button';tab.id='settings-tab-'+i;tab.textContent=p.title;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',p.node.id);tab.addEventListener('click',()=>select(i));tab.addEventListener('keydown',e=>{let next=i;if(e.key==='ArrowLeft')next=(i+1)%panels.length;else if(e.key==='ArrowRight')next=(i-1+panels.length)%panels.length;else if(e.key==='Home')next=0;else if(e.key==='End')next=panels.length-1;else return;e.preventDefault();select(next,true);});p.tab=tab;tabsHost.append(tab);});select(active);
    }
  }
  const paymentTotal=document.querySelector('[data-payment-total]');
  if(paymentTotal){const form=paymentTotal.closest('form');const update=()=>{const amount=Number(form.elements.amount_toman.value)||0,tip=Number(form.elements.tip_toman.value)||0;paymentTotal.textContent=new Intl.NumberFormat('fa').format(amount+tip)+' تومان';};form.addEventListener('input',update);update();}
  document.querySelectorAll('form[data-require-services]').forEach(form=>{
    const button=form.querySelector('.sticky-action button');
    const update=()=>{button.disabled=!form.querySelector('input[name="service_ids[]"]:checked');};form.addEventListener('change',update);update();
  });
  const timeRadio=document.querySelector('input[name=time][type=radio]');
  if(timeRadio){const form=timeRadio.form,button=form.querySelector('.sticky-action button');const update=()=>{button.disabled=!form.querySelector('input[name=time]:checked');};form.addEventListener('change',update);update();}
  // Keep named submit values intact (moderation forms use them server-side).
  document.addEventListener('submit',event=>{
    const form=event.target;if(!(form instanceof HTMLFormElement)||form.method.toLowerCase()!=='post'||event.defaultPrevented||!navigator.onLine)return;
    if(form.dataset.submitting==='true'){event.preventDefault();return;}
    form.dataset.submitting='true';form.setAttribute('aria-busy','true');
    const button=event.submitter;if(button){button.dataset.originalLabel=button.innerHTML;button.setAttribute('aria-disabled','true');button.textContent='در حال ثبت…';}
  });
  window.addEventListener('pageshow',()=>{document.querySelectorAll('form[data-submitting]').forEach(form=>{delete form.dataset.submitting;form.removeAttribute('aria-busy');form.querySelectorAll('[data-original-label]').forEach(button=>{button.innerHTML=button.dataset.originalLabel;button.removeAttribute('aria-disabled');delete button.dataset.originalLabel;});});});
})();
// Appointment views remain fully visible if JavaScript is unavailable.
document.querySelectorAll('[data-appointment-filters]').forEach(root => {
  const upcoming = document.querySelector('[data-appointment-section="upcoming"]');
  const past = document.querySelector('[data-appointment-section="past"]');
  const empty = document.querySelector('[data-appointment-empty]');
  const status = document.querySelector('[data-appointment-status]');
  const buttons = [...root.querySelectorAll('[data-appointment-filter]')];
  const cards = [...(past?.querySelectorAll('[data-appointment-group]') || [])];
  function select(group) {
    upcoming.hidden = group !== 'upcoming';
    if (past) past.hidden = group === 'upcoming';
    let count = 0;
    cards.forEach(card => { card.hidden = card.dataset.appointmentGroup !== group; if (!card.hidden) count++; });
    empty.hidden = group === 'upcoming' || count > 0;
    if (past && count === 0) past.hidden = true;
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.appointmentFilter === group)));
    status.textContent = group === 'upcoming' ? 'نوبت‌های پیش رو نمایش داده می‌شوند.' : new Intl.NumberFormat('fa').format(count) + ' نوبت در این بخش';
  }
  buttons.forEach(button => button.addEventListener('click', () => select(button.dataset.appointmentFilter)));
  root.hidden = false;
  select('upcoming');
});

// Conditional fields retain their values when their section is closed.
document.querySelectorAll('[data-working-day]').forEach(day=>{
  const toggle=day.querySelector('[data-day-closed]');
  if(!toggle)return;
  const update=()=>{day.querySelectorAll('[data-day-times]').forEach(group=>{group.hidden=toggle.checked;});};
  toggle.addEventListener('change',update);update();
});
document.querySelectorAll('[data-timeoff-form]').forEach(form=>{
  const label=form.querySelector('[data-timeoff-all-day-label]'),toggle=form.querySelector('[data-timeoff-all-day]'),times=form.querySelector('[data-timeoff-times]');
  if(!label||!toggle||!times)return;
  const fields=[...times.querySelectorAll('select')];
  toggle.checked=fields.every(field=>field.value==='');
  const update=()=>{times.hidden=toggle.checked;fields.forEach(field=>{field.disabled=toggle.checked;field.required=!toggle.checked;});};
  label.hidden=false;toggle.addEventListener('change',update);update();
});
