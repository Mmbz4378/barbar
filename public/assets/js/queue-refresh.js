(() => {
 let busy=false;
 const status=document.getElementById('queue-sync-status');
 async function refresh(){
  if(busy||document.hidden)return;
  const queue=document.getElementById('queue-live-content');
  if(queue?.contains(document.activeElement))return;
  busy=true;
  try {
   const response=await fetch(location.href,{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
   if(!response.ok||response.redirected)throw Error('unavailable');
   const doc=new DOMParser().parseFromString(await response.text(),'text/html');
   if(!doc.getElementById('queue-live-content'))throw Error('invalid');
   for(const id of ['queue-summary','queue-live-content'])document.getElementById(id)?.replaceWith(doc.getElementById(id));
   status.textContent='آخرین دریافت: '+new Intl.DateTimeFormat('fa',{hour:'2-digit',minute:'2-digit'}).format(new Date());
  }catch(_){status.textContent='به‌روزرسانی انجام نشد؛ اطلاعات نمایش‌داده‌شده ممکن است قدیمی باشد.';}
  finally{busy=false;}
 }
 window.reshenRefreshQueue=refresh;
 setInterval(refresh,30000);
})();
