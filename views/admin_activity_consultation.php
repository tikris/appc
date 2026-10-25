<?php
$colors = [1 => 'Bleu', 2 => 'Vert', 3 => 'Orange', 4 => 'Violet', 5 => 'Rouge', 6 => 'Cyan', 7 => 'Jaune', 8 => 'Gris'];
?>
<div class="card">
  <h2>🏷️ Thèmes</h2>
  <p class="muted small"><?= (int) $counts['ideas'] ?> proposition(s), <?= (int) $counts['votes'] ?> vote(s).
    Pour supprimer une proposition, ouvrez la page de la consultation : chaque proposition y a un bouton « Supprimer ».</p>
  <?php foreach ($themes as $t): $n = (int) ($themeCount[(int) $t['id']] ?? 0); ?>
    <form method="post" action="<?= h($self) ?>" class="theme-row">
      <?= post_fields('update_theme', ['theme_id' => (int) $t['id']]) ?>
      <input type="text" name="position" value="<?= (int) $t['position'] ?>" inputmode="numeric" pattern="[0-9]+" class="narrow" aria-label="Position" title="Position">
      <input type="text" name="name" value="<?= h($t['name']) ?>" maxlength="60" aria-label="Nom du thème" required>
      <select name="color" aria-label="Couleur">
        <?php foreach ($colors as $k => $label): ?>
          <option value="<?= $k ?>"<?= (int) $t['color'] === $k ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="muted small"><?= $n ?> idée(s)</span>
      <button type="submit" class="btn-sm">Enregistrer</button>
    </form>
    <?php if ($n === 0 && count($themes) > 1): ?>
      <form method="post" action="<?= h($self) ?>" class="theme-delete">
        <?= post_fields('delete_theme', ['theme_id' => (int) $t['id']]) ?>
        <button type="submit" class="secondary btn-sm" data-confirm="Supprimer ce thème ?">Supprimer « <?= h($t['name']) ?> »</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$themes): ?><p class="muted">Aucun thème : ajoutez-en au moins un avant de publier.</p><?php endif; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form">
    <?= post_fields('add_theme') ?>
    <h3>Ajouter un thème</h3>
    <label for="t-name">Nom</label>
    <input type="text" id="t-name" name="name" maxlength="60" required>
    <label for="t-color">Couleur</label>
    <select id="t-color" name="color">
      <?php foreach ($colors as $k => $label): ?><option value="<?= $k ?>"><?= h($label) ?></option><?php endforeach; ?>
    </select>
    <div class="btn-row"><button type="submit">Ajouter</button></div>
  </form>
</div>
