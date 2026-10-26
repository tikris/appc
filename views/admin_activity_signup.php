<?php use Appc\SignupRepo; ?>
<div class="card">
  <h2>🙋 Créneaux et inscrits</h2>
  <p class="muted small"><?= (int) $counts['signups'] ?> inscription(s) pour <?= (int) $counts['capacity'] ?> place(s).
    L'export CSV donne la liste par créneau, places libres comprises.</p>

  <?php foreach ($slots as $s): $sid = (int) $s['id']; ?>
    <div class="slot-admin" id="slot-<?= $sid ?>">
      <form method="post" action="<?= h($self) ?>" class="theme-row">
        <?= post_fields('update_slot', ['slot_id' => $sid]) ?>
        <input type="text" name="position" value="<?= (int) $s['position'] ?>" inputmode="numeric" pattern="[0-9]+" class="narrow" aria-label="Position" title="Position">
        <input type="text" name="label" value="<?= h($s['label']) ?>" maxlength="100" aria-label="Libellé" required>
        <input type="datetime-local" name="starts_at" value="<?= h(input_datetime($s['starts_at'] !== null ? (int) $s['starts_at'] : null)) ?>" aria-label="Date (facultatif)" class="date">
        <input type="text" name="capacity" value="<?= (int) $s['capacity'] ?>" inputmode="numeric" pattern="[0-9]+" class="narrow" aria-label="Places" title="Places">
        <button type="submit" class="btn-sm">Enregistrer</button>
      </form>
      <ul class="signup-list">
        <?php foreach ($signups[$sid] ?? [] as $g): ?>
          <li><?= h($g['name']) ?> <span class="muted small">(<?= h(fmt_date((int) $g['created_at'])) ?>)</span>
            <form method="post" action="<?= h($self) ?>" class="inline"><?= post_fields('remove_signup', ['signup_id' => (int) $g['id']]) ?>
              <button type="submit" class="link-btn" data-confirm="Retirer cette inscription ?">retirer</button></form></li>
        <?php endforeach; ?>
        <li class="muted small"><?= (int) $s['taken'] ?> / <?= (int) $s['capacity'] ?> place(s) prise(s)</li>
      </ul>
      <div class="btn-row">
        <?php if ((int) $s['taken'] < (int) $s['capacity']): ?>
          <form method="post" action="<?= h($self) ?>" class="inline-form">
            <?= post_fields('add_signup', ['slot_id' => $sid]) ?>
            <input type="text" name="name" maxlength="<?= SignupRepo::NAME_MAX ?>" placeholder="Prénom (inscription reçue hors portail)" aria-label="Prénom" required>
            <button type="submit" class="secondary btn-sm">Inscrire</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= h($self) ?>"><?= post_fields('delete_slot', ['slot_id' => $sid]) ?>
          <button type="submit" class="secondary btn-sm" data-confirm="Supprimer ce créneau et ses <?= (int) $s['taken'] ?> inscription(s) ?">Supprimer le créneau</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$slots): ?><p class="muted">Aucun créneau : ajoutez-en au moins un avant de publier.</p><?php endif; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form">
    <?= post_fields('add_slot') ?>
    <h3>Ajouter un créneau</h3>
    <label for="s-label">Libellé</label>
    <input type="text" id="s-label" name="label" maxlength="100" placeholder="ex. Stand gâteaux, 14 h – 16 h" required>
    <label for="s-start">Date et heure (facultatif)</label>
    <input type="datetime-local" id="s-start" name="starts_at">
    <label for="s-cap">Nombre de places</label>
    <input type="text" inputmode="numeric" id="s-cap" name="capacity" pattern="[0-9]+" value="2" required>
    <div class="btn-row"><button type="submit">Ajouter</button></div>
  </form>
</div>
