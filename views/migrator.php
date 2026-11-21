<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<h1 style="margin:0 0 8px;">Migrator bazy danych z Invision Community 5</h1>
<p class="vf-muted" style="margin-top:0;">
  Przenosi grupy, konta użytkowników wraz z hashami haseł, strukturę działów i podkategorii, tematy oraz posty z zachowaniem relacji ID.
</p>

<?php if (!empty($error)): ?>
  <div class="vf-card" style="padding:16px;border-color:#dc2626;color:#991b1b;">
    <strong>Błąd migracji:</strong> <?= View::e($error) ?>
  </div>
<?php endif; ?>

<?php if (!empty($report)): ?>
  <div class="vf-card" style="padding:20px;border-color:#16a34a;">
    <strong>Migracja zakończona pomyślnie!</strong>
    <p class="vf-muted" style="margin:8px 0 0;">
      Grupy: <?= (int) $report['groups'] ?> ·
      Użytkownicy: <?= (int) $report['members'] ?> ·
      Działy/Podkategorie: <?= (int) $report['forums'] ?> ·
      Tematy: <?= (int) $report['topics'] ?> ·
      Posty: <?= (int) $report['posts'] ?> ·
      Reakcje: <?= (int) ($report['reactions'] ?? 0) ?> ·
      Macierz uprawnień IC5: <?= (int) ($report['permissions'] ?? 0) ?> ·
      Naprawione osierocenia: <?= (int) $report['orphans_repaired'] ?>
    </p>
  </div>
<?php endif; ?>

<section class="vf-card">
  <div class="vf-card-head">Parametry bazy źródłowej Invision Community 5</div>
  <form method="post" action="/migrate" style="padding:20px;display:grid;gap:14px;max-width:560px;">
    <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
    <label>Host MySQL IC5 <input type="text" name="host" class="vf-input" value="<?= View::e($ic5Config['host']) ?>" required></label>
    <label>Port <input type="number" name="port" class="vf-input" value="<?= (int) $ic5Config['port'] ?>" required></label>
    <label>Nazwa bazy IC5 <input type="text" name="database" class="vf-input" value="<?= View::e($ic5Config['database']) ?>" required></label>
    <label>Użytkownik bazy IC5 <input type="text" name="username" class="vf-input" value="<?= View::e($ic5Config['username']) ?>" required></label>
    <label>Hasło bazy IC5 <input type="password" name="password" class="vf-input" value="<?= View::e($ic5Config['password']) ?>"></label>
    <label>Prefiks tabel (np. ibf_ lub puste) <input type="text" name="prefix" class="vf-input" value="<?= View::e($ic5Config['prefix']) ?>"></label>
    <div><button type="submit" class="vf-btn">Rozpocznij migrację z IC5</button></div>
  </form>
</section>
