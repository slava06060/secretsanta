(() => {
  const $ = (id) => document.getElementById(id);
  const names = ['Sofija', 'Ance', 'Slava', 'August'];
  let csrf = window.SS_BOOT?.csrf || '';
  let loggedIn = Boolean(window.SS_BOOT?.loggedIn);
  let drawing = false;
  let recipient = null;
  let revealed = false;
  let pollHandle = null;

  async function api(action, data = {}, method = 'POST') {
    const options = { method, headers: { 'Accept': 'application/json' } };
    if (method !== 'GET') {
      options.headers['Content-Type'] = 'application/json';
      if (csrf) options.headers['X-CSRF-Token'] = csrf;
      options.body = JSON.stringify({ action, ...data });
    }
    const url = method === 'GET' ? `api.php?action=${encodeURIComponent(action)}` : 'api.php';
    const response = await fetch(url, options);
    const result = await response.json().catch(() => ({ ok: false, error: 'Unexpected server response.' }));
    if (!response.ok || !result.ok) throw new Error(result.error || 'Something went wrong.');
    if (result.csrf) csrf = result.csrf;
    return result;
  }

  function showRoom(nickname) {
    loggedIn = true;
    $('login-panel').hidden = true;
    $('room-panel').hidden = false;
    $('welcome-name').textContent = nickname;
    $('login-error').textContent = '';
    startPolling();
  }

  function showLogin() {
    loggedIn = false;
    if (pollHandle) clearInterval(pollHandle);
    $('login-panel').hidden = false;
    $('room-panel').hidden = true;
    $('password').value = '';
    csrf = '';
  }

  $('login-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    $('login-error').textContent = '';
    try {
      const result = await api('login', {
        nickname: $('nickname').value,
        password: $('password').value
      });
      csrf = result.csrf;
      showRoom(result.nickname);
      await refreshStatus();
    } catch (error) {
      $('login-error').textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });

  $('logout-button').addEventListener('click', async () => {
    try { await api('logout'); } catch (_) {}
    showLogin();
  });

  $('start-button').addEventListener('click', async () => {
    if (drawing) return;
    drawing = true;
    $('start-button').disabled = true;
    $('room-error').textContent = '';
    $('status-note').textContent = 'The names are being mixed…';
    try {
      const result = await api('start');
      if (result.recipient) {
        recipient = result.recipient;
        await runRoulette(recipient);
      } else {
        await refreshStatus();
      }
    } catch (error) {
      $('room-error').textContent = error.message;
    } finally {
      drawing = false;
      $('start-button').disabled = false;
      $('status-note').textContent = 'This room updates automatically.';
    }
  });

  $('reveal-button').addEventListener('click', async () => {
    if (recipient) revealRecipient(recipient);
  });

  async function refreshStatus() {
    if (!loggedIn) return;
    try {
      const status = await api('status');
      $('online-count').textContent = status.online;
      $('progress-fill').style.width = `${Math.min(100, status.online / 4 * 100)}%`;
      $('waiting-state').hidden = status.all_online || status.drawn;
      $('ready-state').hidden = !status.all_online || status.drawn;
      $('start-button').hidden = !status.all_online || status.drawn;
      if (status.drawn) {
        $('status-note').textContent = 'The draw is complete. Only your own recipient is shown here.';
        if (status.recipient && !recipient) {
          recipient = status.recipient;
          // On a refresh, let the user reveal their saved result rather than re-running the draw.
          $('result-panel').hidden = false;
          $('roulette').textContent = 'Your secret is ready';
          $('recipient-name').hidden = true;
          $('reveal-button').hidden = false;
        }
      } else {
        $('result-panel').hidden = true;
      }
    } catch (error) {
      if (error.message.toLowerCase().includes('log in')) showLogin();
      else $('room-error').textContent = error.message;
    }
  }

  function startPolling() {
    if (pollHandle) clearInterval(pollHandle);
    refreshStatus();
    pollHandle = setInterval(refreshStatus, 3000);
  }

  async function runRoulette(finalName) {
    $('result-panel').hidden = false;
    $('recipient-name').hidden = true;
    $('reveal-button').hidden = true;
    const roulette = $('roulette');
    roulette.classList.add('spinning');
    const start = Date.now();
    const duration = 3300;
    let tick = 0;
    while (Date.now() - start < duration) {
      roulette.textContent = names[tick % names.length];
      tick++;
      const elapsed = Date.now() - start;
      const delay = elapsed < 1400 ? 75 : elapsed < 2300 ? 145 : 260;
      await new Promise(resolve => setTimeout(resolve, delay));
    }
    roulette.classList.remove('spinning');
    revealRecipient(finalName);
  }

  function revealRecipient(name) {
    recipient = name;
    revealed = true;
    $('result-panel').hidden = false;
    $('roulette').textContent = 'Your person is…';
    $('recipient-name').textContent = name;
    $('recipient-name').hidden = false;
    $('reveal-button').hidden = true;
    $('status-note').textContent = 'The draw is complete. Keep your recipient a secret.';
  }

  if (loggedIn) {
    showRoom(window.SS_BOOT.nickname);
  }
})();
