<?php $locked = $respondents > 0; ?>
<div class="card">
  <h2>📊 Réponses proposées</h2>
  <p class="muted small"><?= (int) $respondents ?> participant(s) ont répondu.
    <?= $locked
        ? 'Les réponses proposées ne peuvent plus être modifiées : cela fausserait les réponses déjà données.'
        : 'Les réponses proposées seront verrouillées dès la première réponse d\'un participant.' ?></p>

  <?php foreach ($options as $o): ?>
    <?php if ($locked): ?>
      <div class="theme-row"><span class="narrow muted"><?= (int) $o['position'] ?></span> <span><?= h($o['label']) ?></span>
        <span class="muted small"><?= (int) $o['answers'] ?> réponse(s)</span></div>
    <?php else: ?>
      <form method="post" action="<?= h($self) ?>" class="theme-row">
        <?= post_fields('update_option', ['option_id' => (int) $o['id']]) ?>
        <input type="text" name="position" value="<?= (int) $o['position'] ?>" inputmode="numeric" pattern="[0-9]+" class="narrow" aria-label="Position" title="Position">
        <input type="text" name="label" value="<?= h($o['label']) ?>" maxlength="100" aria-label="Libellé" required>
        <button type="submit" class="btn-sm">Enregistrer</button>
      </form>
      <form method="post" action="<?= h($self) ?>" class="theme-delete">
        <?= post_fields('delete_option', ['option_id' => (int) $o['id']]) ?>
        <button type="submit" class="secondary btn-sm" data-confirm="Supprimer cette réponse ?">Supprimer « <?= h($o['label']) ?> »</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$options): ?><p class="muted">Aucune réponse : ajoutez-en au moins deux avant de publier.</p><?php endif; ?>

  <?php if (!$locked): ?>
    <form method="post" action="<?= h($self) ?>" class="sub-form">
      <?= post_fields('add_option') ?>
      <h3>Ajouter une réponse</h3>
      <label for="o-label">Libellé</label>
      <input type="text" id="o-label" name="label" maxlength="100" placeholder="ex. Jeudi 14 novembre à 18 h 30" required>
      <div class="btn-row"><button type="submit">Ajouter</button></div>
    </form>
  <?php endif; ?>
</div>
