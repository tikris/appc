// Aucun script en ligne (CSP stricte) : tout passe par ce fichier.
(function () {
  // Compteurs de caractères : <input data-counter="id-du-span">
  document.querySelectorAll('[data-counter]').forEach(function (el) {
    var out = document.getElementById(el.getAttribute('data-counter'));
    if (!out) return;
    var update = function () { out.textContent = el.value.length; };
    el.addEventListener('input', update);
    update();
  });

  // Confirmation avant action sensible : <button data-confirm="message">
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });
})();
