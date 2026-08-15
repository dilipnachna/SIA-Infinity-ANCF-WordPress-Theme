document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.sia-menu-toggle');
  var navigation = document.getElementById('site-navigation');

  if (!toggle || !navigation) {
    return;
  }

  toggle.addEventListener('click', function () {
    var open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    navigation.classList.toggle('is-open', !open);
  });

  document.addEventListener('keyup', function (event) {
    if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
      navigation.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });
});
