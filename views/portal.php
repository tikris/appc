<?php
use Appc\Csrf;

$slug = $in->slug;
$home = url($slug . '/');
$themeQuery = $active !== null ? '&theme=' . (int) $active['id'] : '';
$myId = $me !== null ? (int) $me['id'] : 0;
$plural = static fn(int $n): string => $n > 1 ? 'votes' : 'vote';
$hidden = static function (array $fields) use ($active): string {
    $out = Csrf::field();
    foreach ($fields as $k => $v) {
        $out .= '<input type="hidden" name="' . h((string) $k) . '" value="' . h((string) $v) . '">';
    }
    if ($active !== null) {
        $out .= '<input type="hidden" name="theme" value="' . (int) $active['id'] . '">';
    }
    return $out;
};
$headerLink = $admin ? [url($slug . '/admin'), 'Administration'] : [url($slug . '/admin'), 'Admin'];
?>

<?php if ($flash): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>

<?php if ($in->intro() !== ''): ?>
  <div class="card intro"><?= nl2br(h($in->intro())) ?></div>
<?php endif; ?>

<!-- ── Bandeau d'état ───────────────────────────────────────────────────── -->
<?php if ($phase === 'open'): ?>
  <?php if ($in->closesAt() !== null): ?>
    <div class="notice">🗓️ Vote ouvert jusqu'au <?= h(fmt_date($in->closesAt())) ?> — <strong>J-<?= (int) $in->daysLeft() ?></strong></div>
  <?php endif; ?>
<?php elseif ($phase === 'before'): ?>
  <div class="closed-banner">🕐 La période de vote n'a pas encore commencé. Ouverture le <?= h(fmt_date((int) $in->opensAt())) ?>.</div>
<?php else: ?>
  <div class="closed-banner">🔒 La période de vote est terminée<?= $in->closedOn() !== null ? ' (clôturée le ' . h(fmt_date($in->closedOn())) . ')' : '' ?>. Résultats ci-dessous.</div>
<?php endif; ?>

<?php if ($me === null && $admin): ?>
  <div class="notice">Vous consultez le portail en tant qu'administrateur. Pour proposer ou voter, entrez avec un code participant.</div>
<?php endif; ?>

<!-- ── Ajout d'une idée ─────────────────────────────────────────────────── -->
<?php if ($open && $me !== null && $themes): ?>
  <?php $addErr = $form === 'add'; ?>
  <div class="card">
    <h2>💡 Proposer une idée</h2>
    <?php if ($addErr && $formError): ?><div class="error"><?= h($formError) ?></div><?php endif; ?>
    <form method="post" action="<?= h($home) ?>">
      <?= $hidden(['action' => 'add_idea']) ?>
      <label for="theme_id">Thème</label>
      <select id="theme_id" name="theme_id" required>
        <?php foreach ($themes as $t):
            $sel = $addErr ? ((int) ($old['theme_id'] ?? 0) === (int) $t['id']) : ($active !== null && (int) $active['id'] === (int) $t['id']);
        ?>
          <option value="<?= (int) $t['id'] ?>"<?= $sel ? ' selected' : '' ?>><?= (int) $themeNumber[(int) $t['id']] ?>. <?= h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="title">Titre</label>
      <input type="text" id="title" name="title" minlength="<?= $in->titleMin() ?>" maxlength="<?= $in->titleMax() ?>" placeholder="Résumez votre proposition en une phrase" value="<?= h($addErr ? ($old['title'] ?? '') : '') ?>" data-counter="title-len" required>
      <div class="char-hint"><span id="title-len">0</span>/<?= $in->titleMax() ?></div>

      <label for="desc">Description (facultatif)</label>
      <textarea id="desc" name="description" maxlength="<?= $in->descMax() ?>" placeholder="Contexte, bénéfices attendus…" data-counter="desc-len"><?= h($addErr ? ($old['description'] ?? '') : '') ?></textarea>
      <div class="char-hint"><span id="desc-len">0</span>/<?= $in->descMax() ?></div>

      <p class="muted small">Restez factuel : ne citez ni enfant ni personne nommément. Les propositions sont visibles de tous les participants.</p>
      <div class="btn-row"><button type="submit">Publier</button></div>
    </form>
  </div>
<?php endif; ?>

<!-- ── Onglets thématiques ──────────────────────────────────────────────── -->
<div class="theme-tabs">
  <a href="<?= h($home) ?>" class="theme-tab tab-all<?= $active === null ? ' active' : '' ?>">
    Toutes <span class="count"><?= (int) $total ?></span>
  </a>
  <?php foreach ($themes as $t): ?>
    <a href="<?= h($home . '?theme=' . (int) $t['id']) ?>" class="theme-tab c<?= (int) $t['color'] ?><?= ($active !== null && (int) $active['id'] === (int) $t['id']) ? ' active' : '' ?>">
      <?= h($t['name']) ?> <span class="count"><?= (int) ($counts[(int) $t['id']] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- ── Liste des idées ──────────────────────────────────────────────────── -->
<div class="ideas">
<?php if (!$ideas): ?>
  <div class="empty"><?= $active !== null ? 'Aucune proposition dans cette thématique' : 'Aucune proposition pour le moment' ?><?= $open && $me !== null ? ' — soyez le premier !' : '.' ?></div>
<?php endif; ?>

<?php foreach ($ideas as $rank => $idea):
    $id = (int) $idea['id'];
    $own = $me !== null && (int) $idea['participant_id'] === $myId;
    $votes = (int) $idea['vote_count'];
    $voted = (bool) $idea['voted'];
    $editing = $own && $open && $editId === $id;
    $ideaNotes = $notes[$id] ?? [];
    $myNote = null;
    foreach ($ideaNotes as $n) {
        if ((int) $n['participant_id'] === $myId) {
            $myNote = $n;
        }
    }
    $noting = $me !== null && $noteId === $id;
?>
  <div class="idea c<?= (int) $idea['theme_color'] ?><?= $voted ? ' voted-idea' : '' ?><?= $own ? ' own-idea' : '' ?>" id="idea-<?= $id ?>">

    <?php if ($admin): ?>
      <form class="admin-bar" method="post" action="<?= h($home) ?>">
        <?= $hidden(['action' => 'delete_idea', 'idea_id' => $id]) ?>
        <button type="submit" class="danger btn-sm" data-confirm="Supprimer définitivement cette proposition, ses votes et ses notes ?">Supprimer</button>
      </form>
    <?php endif; ?>

    <div class="vote-col">
      <span class="vote-count"><?= $votes ?></span>
      <span class="vote-label"><?= $plural($votes) ?></span>
      <?php if ($open && $own): ?>
        <span class="own-tag">ma<br>prop.</span>
      <?php elseif ($open && $me !== null): ?>
        <form method="post" action="<?= h($home) ?>">
          <?= $hidden(['action' => 'vote', 'idea_id' => $id]) ?>
          <button type="submit" class="vote-btn"<?= $voted ? ' disabled' : '' ?>><?= $voted ? '✓ Voté' : 'Voter' ?></button>
        </form>
      <?php endif; ?>
    </div>

    <div class="idea-body">
      <div class="rank">#<?= $rank + 1 ?></div>
      <span class="theme-badge"><?= (int) $themeNumber[(int) $idea['theme_id']] ?>. <?= h($idea['theme_name']) ?></span>

      <?php if ($editing): ?>
        <?php $err = $form === 'edit:' . $id; ?>
        <form class="edit-form" method="post" action="<?= h($home) ?>#idea-<?= $id ?>">
          <?= $hidden(['action' => 'edit_idea', 'idea_id' => $id]) ?>
          <?php if ($err && $formError): ?><div class="error"><?= h($formError) ?></div><?php endif; ?>
          <label for="edit-theme-<?= $id ?>">Thème</label>
          <select id="edit-theme-<?= $id ?>" name="theme_id" required>
            <?php foreach ($themes as $t):
                $cur = $err ? (int) ($old['theme_id'] ?? 0) : (int) $idea['theme_id'];
            ?>
              <option value="<?= (int) $t['id'] ?>"<?= $cur === (int) $t['id'] ? ' selected' : '' ?>><?= (int) $themeNumber[(int) $t['id']] ?>. <?= h($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="edit-title-<?= $id ?>">Titre</label>
          <input type="text" id="edit-title-<?= $id ?>" name="title" minlength="<?= $in->titleMin() ?>" maxlength="<?= $in->titleMax() ?>" value="<?= h($err ? ($old['title'] ?? '') : $idea['title']) ?>" data-counter="edit-title-len-<?= $id ?>" required>
          <div class="char-hint"><span id="edit-title-len-<?= $id ?>">0</span>/<?= $in->titleMax() ?></div>
          <label for="edit-desc-<?= $id ?>">Description (facultatif)</label>
          <textarea id="edit-desc-<?= $id ?>" name="description" maxlength="<?= $in->descMax() ?>" data-counter="edit-desc-len-<?= $id ?>"><?= h($err ? ($old['description'] ?? '') : $idea['description']) ?></textarea>
          <div class="char-hint"><span id="edit-desc-len-<?= $id ?>">0</span>/<?= $in->descMax() ?></div>
          <div class="btn-row">
            <button type="submit">Enregistrer</button>
            <a class="btn secondary btn-sm" href="<?= h($home . ($active !== null ? '?theme=' . (int) $active['id'] : '')) ?>#idea-<?= $id ?>">Annuler</a>
          </div>
        </form>
      <?php else: ?>
        <div class="idea-title"><?= h($idea['title']) ?></div>
        <?php if ($idea['description'] !== ''): ?><div class="idea-desc"><?= nl2br(h($idea['description'])) ?></div><?php endif; ?>
        <div class="idea-meta">
          <span><?= h(fmt_date((int) $idea['created_at'])) ?></span>
          <?php if ($idea['updated_at']): ?><span class="edited-tag">modifiée</span><?php endif; ?>
          <?php if ($own): ?><span class="edited-tag">votre proposition</span><?php endif; ?>
        </div>
        <?php if ($own && $open): ?>
          <div class="owner-actions">
            <a class="btn secondary btn-sm" href="<?= h($home . '?edit=' . $id . $themeQuery) ?>#idea-<?= $id ?>">Modifier</a>
            <form method="post" action="<?= h($home) ?>">
              <?= $hidden(['action' => 'delete_idea', 'idea_id' => $id]) ?>
              <button type="submit" class="secondary btn-sm" data-confirm="Supprimer votre proposition ?">Supprimer</button>
            </form>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Notes de contexte -->
      <?php foreach ($ideaNotes as $n):
          $mine = $me !== null && (int) $n['participant_id'] === $myId;
      ?>
        <div class="community-note">
          <strong>⚖️ Note de contexte</strong>
          <?= nl2br(h($n['body'])) ?>
          <?php if ($mine || $admin): ?>
            <div class="note-actions">
              <?php if ($mine): ?>
                <a class="btn secondary btn-sm" href="<?= h($home . '?note=' . $id . $themeQuery) ?>#idea-<?= $id ?>">Modifier</a>
              <?php endif; ?>
              <form method="post" action="<?= h($home) ?>">
                <?= $hidden(['action' => 'delete_note', 'note_id' => (int) $n['id']]) ?>
                <button type="submit" class="secondary btn-sm" data-confirm="Supprimer cette note ?">Supprimer</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($noting): ?>
        <?php $err = $form === 'note:' . $id; ?>
        <form class="note-form" method="post" action="<?= h($home) ?>#idea-<?= $id ?>">
          <?= $hidden(['action' => 'save_note', 'idea_id' => $id]) ?>
          <?php if ($err && $formError): ?><div class="error"><?= h($formError) ?></div><?php endif; ?>
          <label for="note-<?= $id ?>">⚖️ Note de contexte (cadre légal, réglementaire…)</label>
          <textarea id="note-<?= $id ?>" name="body" maxlength="<?= $in->noteMax() ?>" data-counter="note-len-<?= $id ?>" required><?= h($err ? ($old['body'] ?? '') : ($myNote['body'] ?? '')) ?></textarea>
          <div class="char-hint"><span id="note-len-<?= $id ?>">0</span>/<?= $in->noteMax() ?></div>
          <div class="btn-row">
            <button type="submit">Publier la note</button>
            <a class="btn secondary btn-sm" href="<?= h($home . ($active !== null ? '?theme=' . (int) $active['id'] : '')) ?>#idea-<?= $id ?>">Annuler</a>
          </div>
        </form>
      <?php elseif ($me !== null && $myNote === null): ?>
        <div class="note-cta">
          <a href="<?= h($home . '?note=' . $id . $themeQuery) ?>#idea-<?= $id ?>" class="btn secondary btn-sm">⚖️ Ajouter une note de contexte</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php if ($me !== null): ?>
  <?php ob_start(); ?>
  <form method="post" action="<?= h(url($slug . '/quitter')) ?>" class="inline">
    <?= Csrf::field() ?>
    <button type="submit" class="link-btn">Oublier cet appareil</button>
  </form>
  <?php $footerExtra = ob_get_clean(); ?>
<?php endif; ?>
