<?php
use Appc\Activity;

$a = $activity;
$self = url($in->slug . '/admin/activite/' . $a->id);
$phase = $a->phase();
?>
<p class="back"><a href="<?= h(url($in->slug . '/admin')) ?>">← Retour à l'administration</a></p>

<?php if ($flash): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

<div class="card">
  <span class="kind-tag"><?= $a->icon() ?> <?= h($a->label()) ?></span>
  <h2><?= h($a->title) ?></h2>
  <p>État : <strong><?= h($a->stateLabel()) ?></strong>
    <?php if ($a->isPublished() && $phase === 'before'): ?>(ouverture : <?= h(fmt_datetime((int) $a->opensAt)) ?>)<?php endif; ?>
    <?php if ($a->closesAt !== null): ?>(échéance : <?= h(fmt_datetime($a->closesAt)) ?>)<?php endif; ?>
  </p>
  <?php if (!$a->isPublished()): ?>
    <p class="muted small">Brouillon : invisible des participants. Complétez l'activité ci-dessous, puis publiez-la.</p>
  <?php endif; ?>
  <div class="btn-row">
    <?php if (!$a->isPublished()): ?>
      <form method="post" action="<?= h($self) ?>"><?= post_fields('publish') ?><button type="submit">Publier</button></form>
    <?php else: ?>
      <form method="post" action="<?= h($self) ?>"><?= post_fields('unpublish') ?>
        <button type="submit" class="secondary" data-confirm="Repasser en brouillon ? L'activité ne sera plus visible des participants (rien n'est supprimé).">Repasser en brouillon</button></form>
    <?php endif; ?>
    <?php if ($phase !== 'closed'): ?>
      <form method="post" action="<?= h($self) ?>"><?= post_fields('close_now') ?>
        <button type="submit" class="danger" data-confirm="Clôturer maintenant ? Plus aucune participation ne sera possible.">Clôturer maintenant</button></form>
    <?php else: ?>
      <form method="post" action="<?= h($self) ?>"><?= post_fields('reopen') ?><button type="submit">Rouvrir</button></form>
    <?php endif; ?>
    <a class="btn secondary" href="<?= h(url($in->slug . '/activite/' . $a->id)) ?>"><?= $a->isPublished() ? 'Voir la page' : 'Aperçu' ?></a>
    <a class="btn secondary" href="<?= h($self . '/export.csv') ?>">Exporter (CSV)</a>
  </div>
</div>

<!-- ── Réglages ─────────────────────────────────────────────────────────── -->
<div class="card">
  <h2>⚙️ Réglages</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= post_fields('save_activity') ?>
    <label for="a-title">Titre</label>
    <input type="text" id="a-title" name="title" maxlength="<?= Activity::TITLE_MAX ?>" value="<?= h($a->title) ?>" required>
    <label for="a-desc">Description (facultatif)</label>
    <textarea id="a-desc" name="description" maxlength="<?= Activity::DESC_MAX ?>" data-counter="a-desc-len"><?= h($a->description) ?></textarea>
    <div class="char-hint"><span id="a-desc-len">0</span>/<?= Activity::DESC_MAX ?></div>
    <label for="a-start">Ouverture (facultatif : vide = ouverte dès la publication)</label>
    <input type="datetime-local" id="a-start" name="opens_at" value="<?= h(input_datetime($a->opensAt)) ?>">
    <label for="a-end">Clôture (facultatif)</label>
    <input type="datetime-local" id="a-end" name="closes_at" value="<?= h(input_datetime($a->closesAt)) ?>">

    <?php if ($a->kind === 'consultation'): ?>
      <label for="a-max">Propositions maximum par participant</label>
      <input type="text" inputmode="numeric" id="a-max" name="max_ideas" pattern="[0-9]+" value="<?= $a->maxIdeas ?>" required>
    <?php elseif ($a->kind === 'poll'): ?>
      <label class="check"><input type="checkbox" name="multiple" value="1"<?= $a->multiple ? ' checked' : '' ?>> Plusieurs réponses possibles (ex. choix de dates)</label>
      <label for="a-results">Résultats</label>
      <select id="a-results" name="results">
        <?php foreach (Activity::RESULTS as $k => $label): ?>
          <option value="<?= h($k) ?>"<?= $a->results === $k ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    <?php else: ?>
      <label class="check"><input type="checkbox" name="show_names" value="1"<?= $a->showNames ? ' checked' : '' ?>> Afficher les prénoms des inscrits aux autres participants</label>
      <p class="muted small">Décoché (recommandé) : seul l'administrateur voit les prénoms ; les participants voient le nombre de places prises.</p>
    <?php endif; ?>
    <div class="btn-row"><button type="submit">Enregistrer</button></div>
  </form>
</div>

<?php require __DIR__ . '/admin_activity_' . $a->kind . '.php'; ?>

<form method="post" action="<?= h($self) ?>" class="logout">
  <?= post_fields('delete_activity') ?>
  <button type="submit" class="danger" data-confirm="Supprimer définitivement cette activité et tout son contenu (propositions, votes, réponses, inscriptions) ? Pensez à exporter avant.">Supprimer l'activité</button>
</form>
