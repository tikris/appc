<?php
use Appc\Activity;

$slug = $in->slug;
$n = static fn(int $k, string $one, string $many): string => $k . ' ' . ($k > 1 ? $many : $one);
$summary = static function (Activity $a) use ($counts, $n): string {
    $c = $counts[$a->id];
    return match ($a->kind) {
        'consultation' => $n($c['ideas'], 'proposition', 'propositions') . ' · ' . $n($c['votes'], 'vote', 'votes'),
        'poll'         => $n($c['respondents'], 'réponse', 'réponses'),
        'signup'       => $c['signups'] . ' / ' . $c['capacity'] . ' places pourvues',
    };
};
$when = static function (Activity $a): string {
    return match ($a->phase()) {
        'open'   => $a->closesAt !== null ? 'Jusqu\'au ' . fmt_date($a->closesAt) . ' (J-' . $a->daysLeft() . ')' : 'Ouverte',
        'before' => 'Ouverture le ' . fmt_date((int) $a->opensAt),
        default  => $a->closedOn() !== null ? 'Terminée le ' . fmt_date($a->closedOn()) : 'Terminée',
    };
};
$card = static function (Activity $a) use ($slug, $summary, $when, $done): string {
    $out = '<a class="activity-card state-' . h($a->phase()) . '" href="' . h(url($slug . '/activite/' . $a->id)) . '">'
        . '<span class="kind-tag">' . $a->icon() . ' ' . h($a->label()) . '</span>'
        . '<span class="activity-title">' . h($a->title) . '</span>'
        . '<span class="activity-meta">' . h($when($a)) . ' · ' . h($summary($a)) . '</span>';
    if (isset($done[$a->id])) {
        $out .= '<span class="done-tag">✓ ' . h($done[$a->id]) . '</span>';
    }
    return $out . '</a>';
};
$current = [...$activities['open'], ...$activities['before']];
?>

<?php if ($me === null && $admin): ?>
  <div class="notice">Vous consultez le portail en tant qu'administrateur. Pour participer, entrez avec un code participant.</div>
<?php endif; ?>

<?php if ($in->intro() !== ''): ?>
  <div class="card intro"><?= nl2br(h($in->intro())) ?></div>
<?php endif; ?>

<?php if ($agenda): ?>
  <div class="card">
    <h2>📅 À venir</h2>
    <ul class="agenda">
      <?php foreach ($agenda as $e): ?>
        <li>
          <span class="agenda-date"><?= h(fmt_day($e['at'])) ?></span>
          <span><a href="<?= h($e['href']) ?>"><?= h($e['title']) ?></a><?php if ($e['location'] !== ''): ?> <span class="muted">— <?= h($e['location']) ?></span><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2 class="section-title">🗂️ Activités</h2>
<?php if (!$current && !$activities['closed']): ?>
  <div class="empty">Aucune activité pour le moment.</div>
<?php endif; ?>
<div class="activities">
  <?php foreach ($current as $a): ?><?= $card($a) ?><?php endforeach; ?>
</div>
<?php if ($activities['closed']): ?>
  <details class="closed-activities"<?= $current ? '' : ' open' ?>>
    <summary>Activités terminées (<?= count($activities['closed']) ?>)</summary>
    <div class="activities">
      <?php foreach ($activities['closed'] as $a): ?><?= $card($a) ?><?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>

<?php if ($posts): ?>
  <h2 class="section-title">📢 Annonces</h2>
  <?php foreach ($posts as $p): ?>
    <article class="card post<?= $p['pinned'] ? ' pinned' : '' ?>" id="post-<?= (int) $p['id'] ?>">
      <h3><?= $p['pinned'] ? '📌 ' : '' ?><?= h($p['title']) ?></h3>
      <p class="post-meta muted small">
        Publiée le <?= h(fmt_date((int) $p['created_at'])) ?>
        <?php if ($p['event_at'] !== null): ?> · 📅 <?= h(fmt_day((int) $p['event_at'])) ?><?php endif; ?>
        <?php if ($p['location'] !== ''): ?> · 📍 <?= h($p['location']) ?><?php endif; ?>
      </p>
      <?php if ($p['body'] !== ''): ?><div class="post-body"><?= nl2br(h($p['body'])) ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_leave.php'; ?>
