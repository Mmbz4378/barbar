/* فاصلهٔ تقریبی سالن‌ها از کاربر — فقط با اجازهٔ خودش و فقط روی دستگاه. */
(function () {
  var button = document.querySelector('[data-locate]');
  var status = document.querySelector('[data-location-status]');
  if (!button || !status) return;
  button.addEventListener('click', function () {
    if (!navigator.geolocation) { status.textContent = 'موقعیت‌یابی در این مرورگر پشتیبانی نمی‌شود؛ شهر را انتخاب کن.'; return; }
    status.textContent = 'در حال دریافت موقعیت…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var rad = function (n) { return n * Math.PI / 180; };
      var nf = new Intl.NumberFormat('fa', { maximumFractionDigits: 1 });
      Array.prototype.forEach.call(document.querySelectorAll('[data-salon-location]'), function (card) {
        if (!card.dataset.lat || !card.dataset.lng) return;
        var lat = +card.dataset.lat, lng = +card.dataset.lng, a = pos.coords.latitude, b = pos.coords.longitude;
        var h = Math.pow(Math.sin(rad(lat - a) / 2), 2) + Math.cos(rad(a)) * Math.cos(rad(lat)) * Math.pow(Math.sin(rad(lng - b) / 2), 2);
        var km = 6371 * 2 * Math.asin(Math.sqrt(Math.min(1, h)));
        var out = card.querySelector('[data-distance]');
        if (out) out.textContent = nf.format(km) + ' کیلومتر';
      });
      status.textContent = 'فاصله‌ها خط مستقیم و تقریبی‌اند، نه طول مسیر.';
    }, function () { status.textContent = 'موقعیت دریافت نشد. با انتخاب شهر و محله ادامه بده.'; }, { timeout: 10000, maximumAge: 60000 });
  });
})();
