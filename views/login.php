<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php if (!empty($error)): ?>
  <div class="vf-card" style="padding:16px;border-color:#dc2626;color:#991b1b;">
    <?= View::e($error) ?>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
  <section class="vf-card">
    <div class="vf-card-head">Logowanie (obsługuje hasła z IC5)</div>
    <form method="post" action="/login" style="padding:20px;display:grid;gap:12px;">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <input type="text" name="login" class="vf-input" placeholder="Nazwa użytkownika lub e-mail" required>
      <input type="password" name="password" class="vf-input" placeholder="Hasło" required>
      <button type="submit" class="vf-btn">Zaloguj się</button>
    </form>
  </section>

  <section class="vf-card">
    <div class="vf-card-head">Nowe konto (Argon2id)</div>
    <form method="post" action="/register" style="padding:20px;display:grid;gap:12px;">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <input type="text" name="username" class="vf-input" placeholder="Nazwa użytkownika" required>
      <input type="email" name="email" class="vf-input" placeholder="Adres e-mail" required>
      <input type="password" name="password" class="vf-input" placeholder="Hasło (min. 8 znaków)" required>
      <button type="submit" class="vf-btn">Utwórz konto</button>
    </form>
  </section>
</div>
