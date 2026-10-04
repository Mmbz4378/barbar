(() => {
 const banner=document.createElement('p');banner.className='network-status';banner.setAttribute('role','status');
 banner.textContent='اتصال اینترنت قطع است؛ اطلاعات ممکن است قدیمی باشد. برای ثبت نوبت دوباره متصل شو.';
 document.body.prepend(banner);
 const update=()=>{banner.hidden=navigator.onLine;};update();addEventListener('online',update);addEventListener('offline',update);
 document.addEventListener('submit',event=>{
  if(!navigator.onLine && event.target.method.toLowerCase()==='post'){event.preventDefault();banner.hidden=false;banner.scrollIntoView();}
 });
})();
