(() => {
 const status=document.querySelector('[data-location-status]');
 document.querySelector('[data-locate]')?.addEventListener('click',()=>{
  if(!navigator.geolocation){status.textContent='موقعیت‌یابی در این مرورگر پشتیبانی نمی‌شود؛ شهر را انتخاب کن.';return;}
  status.textContent='در حال دریافت موقعیت…';
  navigator.geolocation.getCurrentPosition(position=>{
   const rad=n=>n*Math.PI/180;
   document.querySelectorAll('[data-salon-location]').forEach(card=>{
    if(card.dataset.lat===''||card.dataset.lng==='')return;
    const lat=+card.dataset.lat,lng=+card.dataset.lng,a=position.coords.latitude,b=position.coords.longitude;
    const h=Math.sin(rad(lat-a)/2)**2+Math.cos(rad(a))*Math.cos(rad(lat))*Math.sin(rad(lng-b)/2)**2;
    const distance=6371*2*Math.asin(Math.sqrt(Math.min(1,h)));
    card.querySelector('[data-distance]').textContent=new Intl.NumberFormat('fa',{maximumFractionDigits:1}).format(distance)+' کیلومتر';
   });status.textContent='فاصله‌ها تقریبی و مستقیم هستند؛ طول مسیر خیابان نیستند.';
  },()=>{status.textContent='موقعیت دریافت نشد. می‌توانی با انتخاب شهر و محله ادامه بدهی.';},{timeout:10000,maximumAge:60000});
 });
 document.querySelectorAll('[data-map]').forEach(button=>button.addEventListener('click',()=>{
  const card=button.closest('[data-salon-location]'),lat=+card.dataset.lat,lng=+card.dataset.lng;
  const panel=document.querySelector('[data-map-panel]');
  panel.querySelector('iframe').src='https://www.openstreetmap.org/export/embed.html?bbox='+[lng-.015,lat-.01,lng+.015,lat+.01].join(',')+'&layer=mapnik&marker='+lat+','+lng;
  panel.querySelector('[data-map-link]').href='https://www.openstreetmap.org/?mlat='+lat+'&mlon='+lng+'#map=16/'+lat+'/'+lng;
  panel.hidden=false;panel.scrollIntoView({block:'start'});
 }));
})();
