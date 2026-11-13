/* VoltHub — js/app.js
   Small front-end behaviour shared by all pages. */

// 1. Fade out and remove the flash toasts after a few seconds.
document.querySelectorAll('.toast').forEach(function (t) {
  setTimeout(function () {
    t.classList.add('out');
    setTimeout(function () { t.remove(); }, 300);
  }, 2800);
});

// 2. Mobile admin menu (open / close the sidebar).
(function () {
  var sb = document.querySelector('.sidebar');
  var ov = document.querySelector('.sidebar-overlay');
  var b = document.getElementById('menuBtn');
  if (b && sb && ov) {
    var set = function (open) { sb.classList.toggle('open', open); ov.classList.toggle('show', open); };
    b.addEventListener('click', function () { set(!sb.classList.contains('open')); });
    ov.addEventListener('click', function () { set(false); });
  }
})();

// 3. Show / hide password buttons.
document.querySelectorAll('.toggle-pass').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = btn.previousElementSibling;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? '⦵' : '👁';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});

// 4. Confirm dialogs for forms/buttons marked with data-confirm="message".
document.addEventListener('submit', function (e) {
  var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
  if (m && !confirm(m)) e.preventDefault();
});
document.addEventListener('click', function (e) {
  var b = e.target.closest && e.target.closest('button[data-confirm]');
  if (b && !confirm(b.getAttribute('data-confirm'))) e.preventDefault();
});

// 5. Whole table row is clickable when it has data-href (inventory page).
document.querySelectorAll('tr[data-href]').forEach(function (row) {
  row.addEventListener('click', function (e) {
    if (!e.target.closest('a,button')) location.href = row.getAttribute('data-href');
  });
});
