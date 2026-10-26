<?php
use Appc\SignupRepo;

$self = url($in->slug . '/activite/' . $activity->id);
$canJoin = $activity->isOpen() && $me !== null;
require __DIR__ . '/_activity_head.php';
?>

<div class="card">
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <?php if (!$slots): ?>
    <p class="muted">Aucun créneau pour le moment.</p>
  <?php else: ?>
    <form method="post" action="<?= h($self) ?>" id="join-form">
      <?= post_fields('join') ?>
      <?php if ($canJoin): ?>
        <label for="signup-name">Votre prénom ou pseudo</label>
        <input type="text" id="signup-name" name="name" maxlength="<?= SignupRepo::NAME_MAX ?>" value="<?= h($name) ?>" autocomplete="given-name" required>
        <p class="muted small">
          <?= $activity->showNames
              ? 'Visible des autres participants et de l\'organisateur.'
              : 'Visible de l\'organisateur seulement : les autres participants ne voient que le nombre de places prises.' ?>
          N'indiquez pas votre nom de famille.
        </p>
      <?php endif; ?>

      <ul class="slots">
        <?php foreach ($slots as $s):
            $sid = (int) $s['id'];
            $taken = (int) $s['taken'];
            $cap = (int) $s['capacity'];
            $mine = in_array($sid, $mySlots, true);
        ?>
          <li class="slot<?= $mine ? ' mine' : '' ?><?= $taken >= $cap ? ' full' : '' ?>" id="slot-<?= $sid ?>">
            <div class="slot-main">
              <span class="slot-label"><?= h($s['label']) ?></span>
              <?php if ($s['starts_at'] !== null): ?><span class="muted small"><?= h(fmt_day((int) $s['starts_at'])) ?></span><?php endif; ?>
              <span class="slot-places"><?= $taken ?> / <?= $cap ?></span>
              <progress max="<?= $cap ?>" value="<?= $taken ?>"><?= $taken ?> / <?= $cap ?></progress>
            </div>
            <?php if (isset($signups[$sid])): ?>
              <p class="slot-names small"><?= h(implode(', ', array_column($signups[$sid], 'name'))) ?></p>
            <?php endif; ?>
            <div class="slot-action">
              <?php if ($mine): ?>
                <span class="done-tag">✓ Vous êtes inscrit(e)</span>
                <?php if ($canJoin): ?>
                  <button type="submit" form="leave-<?= $sid ?>" class="secondary btn-sm" data-confirm="Annuler votre inscription sur ce créneau ?">Annuler</button>
                <?php endif; ?>
              <?php elseif ($canJoin && $taken < $cap): ?>
                <button type="submit" name="slot_id" value="<?= $sid ?>" class="btn-sm">Je m'inscris</button>
              <?php elseif ($taken >= $cap): ?>
                <span class="muted small">Complet</span>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </form>
    <?php foreach ($mySlots as $sid): ?>
      <form method="post" action="<?= h($self) ?>" id="leave-<?= (int) $sid ?>" hidden><?= post_fields('leave', ['slot_id' => (int) $sid]) ?></form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_leave.php'; ?>
