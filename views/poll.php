<?php
$open = $activity->isOpen();
$canAnswer = $open && $me !== null;
$type = $activity->multiple ? 'checkbox' : 'radio';
require __DIR__ . '/_activity_head.php';
?>

<div class="card">
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <?php if (!$options): ?>
    <p class="muted">Aucune réponse proposée pour le moment.</p>
  <?php else: ?>
    <form method="post" action="<?= h(url($in->slug . '/activite/' . $activity->id)) ?>">
      <?= post_fields('answer') ?>
      <fieldset class="poll-options"<?= $canAnswer ? '' : ' disabled' ?>>
        <legend class="muted small"><?= $activity->multiple ? 'Plusieurs réponses possibles.' : 'Une seule réponse.' ?></legend>
        <?php foreach ($options as $o):
            $id = (int) $o['id'];
            $count = (int) $o['answers'];
        ?>
          <label class="poll-option<?= in_array($id, $mine, true) ? ' chosen' : '' ?>">
            <input type="<?= $type ?>" name="options[]" value="<?= $id ?>"<?= in_array($id, $mine, true) ? ' checked' : '' ?>>
            <span class="poll-label"><?= h($o['label']) ?></span>
            <?php if ($showResults): ?>
              <span class="poll-count"><?= $count ?></span>
              <progress max="<?= max(1, $respondents) ?>" value="<?= $count ?>"><?= $count ?></progress>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <?php if ($canAnswer): ?>
        <div class="btn-row"><button type="submit"><?= $mine ? 'Modifier ma réponse' : 'Répondre' ?></button></div>
      <?php endif; ?>
    </form>
  <?php endif; ?>

  <p class="muted small poll-footer">
    <?= $respondents ?> participant<?= $respondents > 1 ? 's ont' : ' a' ?> répondu.
    <?php if (!$showResults): ?>
      Résultats visibles <?= $activity->results === 'voted' ? 'après avoir répondu' : 'à la clôture du sondage' ?>.
    <?php elseif ($open && $mine): ?>
      Vous pouvez modifier votre réponse jusqu'à la clôture.
    <?php endif; ?>
  </p>
</div>

<?php require __DIR__ . '/_leave.php'; ?>
