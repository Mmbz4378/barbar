/* Progressive enhancement: all services remain usable without JavaScript. */
document.querySelectorAll('[data-service-discovery]').forEach(root => {
  const search = root.querySelector('input[type="search"]');
  const cards = [...document.querySelectorAll('[data-service-card]')];
  const buttons = [...root.querySelectorAll('[data-category]')];
  const status = root.querySelector('[role="status"]');
  const empty = root.querySelector('[data-no-results]');
  let category = 'all';
  const normalize = text => text.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\s]+/g, ' ').trim().toLowerCase();
  function update() {
    const query = normalize(search.value);
    let count = 0;
    cards.forEach(card => {
      const shown = (category === 'all' || card.dataset.category === category) && normalize(card.dataset.serviceCard).includes(query);
      card.hidden = !shown;
      if (shown) count++;
    });
    status.textContent = new Intl.NumberFormat('fa').format(count) + ' خدمت';
    empty.hidden = count !== 0;
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.category === category)));
  }
  search.addEventListener('input', update);
  buttons.forEach(button => button.addEventListener('click', () => { category = button.dataset.category; update(); }));
  root.querySelector('[data-reset-search]').addEventListener('click', () => { search.value = ''; category = 'all'; update(); search.focus(); });
  root.hidden = false;
  update();
});
