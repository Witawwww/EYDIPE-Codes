/* Login / register page (demo auth stored in localStorage) */
(function () {
  const { $, $$ } = GS;

  if (GS.session()) { location.replace('admin.html'); return; }

  const loginForm = $('#loginForm'), regForm = $('#regForm');
  const copy = {
    login: ['Welcome back', 'Sign in to manage your store.'],
    register: ['Create your account', 'Set up an admin account for the dashboard.']
  };

  function showTab(name) {
    $$('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    loginForm.hidden = name !== 'login';
    regForm.hidden = name !== 'register';
    $('#title').textContent = copy[name][0];
    $('#subtitle').textContent = copy[name][1];
    $('#loginError').hidden = true;
    $('#regError').hidden = true;
  }
  $$('.tab').forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));

  $$('.toggle-pass').forEach(b => b.addEventListener('click', () => {
    const input = b.previousElementSibling;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.textContent = show ? '🙈' : '👁️';
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));

  const fail = (id, msg) => { const e = $(id); e.textContent = msg; e.hidden = false; };

  function enter(user) {
    GS.setSession({ name: user.name, username: user.username });
    location.href = 'admin.html';
  }

  loginForm.addEventListener('submit', e => {
    e.preventDefault();
    const u = $('#lUser').value.trim().toLowerCase(), p = $('#lPass').value;
    if (!u || !p) return fail('#loginError', 'Please enter your username and password.');
    const user = GS.admins().find(a => a.username.toLowerCase() === u && a.password === p);
    if (!user) return fail('#loginError', 'Incorrect username or password.');
    enter(user);
  });

  regForm.addEventListener('submit', e => {
    e.preventDefault();
    const name = $('#rName').value.trim(), u = $('#rUser').value.trim().toLowerCase();
    const p = $('#rPass').value, p2 = $('#rPass2').value;
    if (!name || !u || !p) return fail('#regError', 'Please fill in all fields.');
    if (!/^[a-z0-9_.-]{3,}$/.test(u)) return fail('#regError', 'Username needs 3+ letters, numbers, dot, dash or underscore.');
    if (p.length < 6) return fail('#regError', 'Password must be at least 6 characters.');
    if (p !== p2) return fail('#regError', 'Passwords do not match.');
    if (GS.admins().some(a => a.username.toLowerCase() === u)) return fail('#regError', 'That username is already taken.');
    const user = { name, username: u, password: p };
    GS.addAdmin(user);
    enter(user);
  });
})();
