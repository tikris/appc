<?php
/**
 * En-tête commun des pages d'activité : retour, titre, description, bandeau d'état.
 * Variables : $in, $activity, $admin, $me, $flash (facultatif).
 */
?>
<p class="back"><a href="<?= h(url($in->slug . '/')) ?>">← Toutes les activités</a></p>

<?php if (!empty($flash)): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>

<?php if (!$activity->isPublished()): ?>
  <div class="closed-banner">📝 Brouillon : cette activité n'est visible que de l'administrateur.
    <a href="<?= h(url($in->slug . '/admin/activite/' . $activity->id)) ?>">La gérer</a></div>
<?php endif; ?>

<div class="card activity-head">
  <span class="kind-tag"><?= $activity->icon() ?> <?= h($activity->label()) ?></span>
  <h2><?= h($activity->title) ?></h2>
  <?php if ($activity->description !== ''): ?><p><?= nl2br(h($activity->description)) ?></p><?php endif; ?>
  <?php if ($admin): ?>
    <p class="small"><a href="<?= h(url($in->slug . '/admin/activite/' . $activity->id)) ?>">Gérer cette activité</a></p>
  <?php endif; ?>
</div>

<?php if ($activity->phase() === 'open'): ?>
  <?php if ($activity->closesAt !== null): ?>
    <div class="notice">🗓️ <?= h(sprintf($activity->text('open'), fmt_date($activity->closesAt))) ?> — <strong>J-<?= (int) $activity->daysLeft() ?></strong></div>
  <?php endif; ?>
<?php elseif ($activity->phase() === 'before'): ?>
  <div class="closed-banner">🕐 <?= h($activity->text('before')) ?>. Ouverture le <?= h(fmt_datetime((int) $activity->opensAt)) ?>.</div>
<?php else: ?>
  <div class="closed-banner">🔒 <?= h($activity->text('closed')) ?><?= $activity->closedOn() !== null ? ' (clôture le ' . h(fmt_date($activity->closedOn())) . ')' : '' ?>.</div>
<?php endif; ?>

<?php if ($me === null && $admin): ?>
  <div class="notice">Vous consultez cette page en tant qu'administrateur. Pour participer, entrez avec un code participant.</div>
<?php endif; ?>
