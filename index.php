<?php
declare(strict_types=1);
session_start();
$loggedIn = !empty($_SESSION['user_id']);
$csrf = $_SESSION['csrf'] ?? '';
$nickname = $_SESSION['nickname'] ?? '';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Secret Santa — The Draw</title>
  <link rel="stylesheet" href="style.css">
  <script>window.SS_BOOT = <?= json_encode(['loggedIn'=>$loggedIn, 'nickname'=>$nickname, 'csrf'=>$csrf], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
  <script src="app.js" defer></script>
</head>
<body>
  <div class="snow" aria-hidden="true"></div>
  <main class="shell">
    <header class="brand">
      <div class="brand-mark">✳</div>
      <p class="eyebrow">A LITTLE CHRISTMAS MAGIC</p>
      <h1>Secret <em>Santa</em></h1>
      <p class="subhead">One name. One surprise. Keep it secret.</p>
    </header>

    <section id="login-panel" class="panel login-panel" <?= $loggedIn ? 'hidden' : '' ?>>
      <p class="eyebrow">YOUR INVITATION</p>
      <h2>Step into the circle</h2>
      <p class="muted">Sign in with your personal name and password.</p>
      <form id="login-form">
        <label for="nickname">Nickname</label>
        <select id="nickname" name="nickname" required>
          <option value="" disabled selected>Choose your name</option>
          <option>Sofija</option><option>Ance</option><option>Slava</option><option>August</option>
        </select>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Your password" required>
        <button class="button primary full" type="submit">Enter the circle <span>↗</span></button>
        <p id="login-error" class="error" role="alert"></p>
      </form>
      <p class="fine-print">Your draw is private. Don't share your login.</p>
    </section>

    <section id="room-panel" class="panel room-panel" <?= $loggedIn ? '' : 'hidden' ?>>
      <div class="room-top">
        <div>
          <p class="eyebrow">THE PRIVATE CIRCLE</p>
          <h2>Welcome, <span id="welcome-name"><?= htmlspecialchars((string)$nickname, ENT_QUOTES, 'UTF-8') ?></span></h2>
        </div>
        <button id="logout-button" class="text-button" type="button">Log out ↗</button>
      </div>

      <div class="attendance">
        <div class="attendance-copy">
          <span class="muted">Santa circle</span>
          <strong><span id="online-count">1</span><span class="count-divider"> / </span>4 <small>online</small></strong>
        </div>
        <div class="avatars" aria-label="Four participants">
          <span title="Sofija">S</span><span title="Ance">A</span><span title="Slava">S</span><span title="August">A</span>
        </div>
      </div>
      <div class="progress-track"><div id="progress-fill" class="progress-fill"></div></div>
      <div id="waiting-state" class="state-box">
        <span class="state-icon">✧</span>
        <div><strong>Waiting for everyone to join…</strong><p>Keep this page open. The draw unlocks when all four are here.</p></div>
      </div>

      <div id="ready-state" class="state-box ready" hidden>
        <span class="state-icon">✦</span>
        <div><strong>The whole circle is here.</strong><p>Everyone is online. The secret draw is ready.</p></div>
      </div>

      <button id="start-button" class="button primary full" type="button" hidden>Start randomizer <span>✳</span></button>
      <p id="status-note" class="fine-print centered">This room updates automatically.</p>
      <p id="room-error" class="error" role="alert"></p>

      <section id="result-panel" class="result-panel" hidden aria-live="polite">
        <div class="result-star">✳</div>
        <p class="eyebrow">YOUR SECRET RECIPIENT</p>
        <div id="roulette" class="roulette">Your name is waiting…</div>
        <h2 id="recipient-name" class="recipient-name" hidden></h2>
        <p class="result-note">Your person is yours to surprise. Keep their name a secret.</p>
        <button id="reveal-button" class="button secondary full" type="button" hidden>Reveal my person</button>
      </section>
    </section>
    <footer><span>MADE FOR YOUR LITTLE CIRCLE</span><span>✦</span><span>KEEP THE MAGIC SECRET</span></footer>
  </main>
</body>
</html>
