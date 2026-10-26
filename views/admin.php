<?php
use Appc\Activity;
use Appc\AdminPanel;

$slug = $in->slug;
$self = url($slug . '/admin');
$now = time();
$n = static fn(int $k, string $one, string $many): string => $k . ' ' . ($k > 1 ? $many : $one);
$p = $editPost ?? ['id' => 0, 'title' => '', 'body' => '', 'event_at' => null, 'location' => '', 'pinned' => 0];
?>

<?php if ($flash): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<?php if ($mustChange): ?>
  <div class="closed-banner">⚠️ Votre mot de passe est provisoire : changez-le dans la section « Mot de passe » ci-dessous.</div>
<?php endif; ?>

<div class="card">
  <h2>📊 Vue d'ensemble</h2>
  <p>
    <strong><?= (int) $stats['participants'] ?></strong> participant(s) entré(s) avec un code ·
    <strong><?= (int) $stats['activities'] ?></strong> activité(s) ·
    <strong><?= (int) $stats['posts'] ?></strong> annonce(s)
  </p>
  <p class="muted small">Pour donner l'accès aux parents, créez un code dans « Codes d'accès » ci-dessous et diffusez-le.</p>
</div>

<!-- ── Activités ────────────────────────────────────────────────────────── -->
<div class="card">
  <h2>🗂️ Activités</h2>
  <?php if ($activities): ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Activité</th><th>Type</th><th>État</th><th>Participation</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($activities as $a): $c = $counts[$a->id]; ?>
      <tr class="<?= $a->isPublished() && $a->phase() !== 'closed' ? '' : 'inactive' ?>">
        <td><a href="<?= h(url($slug . '/admin/activite/' . $a->id)) ?>"><?= h($a->title) ?></a></td>
        <td><?= $a->icon() ?> <?= h($a->label()) ?></td>
        <td><?= h($a->stateLabel()) ?></td>
        <td><?= h(match ($a->kind) {
            'consultation' => $n($c['ideas'], 'proposition', 'propositions') . ', ' . $n($c['votes'], 'vote', 'votes'),
            'poll'         => $n($c['respondents'], 'réponse', 'réponses'),
            'signup'       => $c['signups'] . ' / ' . $c['capacity'] . ' places',
        }) ?></td>
        <td><a class="btn secondary btn-sm" href="<?= h(url($slug . '/admin/activite/' . $a->id)) ?>">Gérer</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="muted">Aucune activité pour le moment.</p>
  <?php endif; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form">
    <?= post_fields('create_activity') ?>
    <h3>Créer une activité</h3>
    <fieldset class="kind-choice">
      <legend class="muted small">Type</legend>
      <?php foreach (Activity::KINDS as $kind => $k): ?>
        <label class="check"><input type="radio" name="kind" value="<?= h($kind) ?>" required>
          <span><strong><?= $k['icon'] ?> <?= h($k['label']) ?></strong> — <?= h($k['hint']) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <label for="a-title">Titre</label>
    <input type="text" id="a-title" name="title" maxlength="<?= Activity::TITLE_MAX ?>" placeholder="ex. Menus de la cantine, Kermesse de juin…" required>
    <p class="muted small">L'activité est créée en brouillon : vous la complétez, puis vous la publiez.</p>
    <div class="btn-row"><button type="submit">Créer</button></div>
  </form>
</div>

<!-- ── Annonces et agenda ───────────────────────────────────────────────── -->
<div class="card" id="annonces">
  <h2>📢 Annonces et agenda</h2>
  <?php foreach ($posts as $row): ?>
    <div class="post-row">
      <span><?= $row['pinned'] ? '📌 ' : '' ?><strong><?= h($row['title']) ?></strong>
        <span class="muted small"><?= $row['event_at'] !== null ? '📅 ' . h(fmt_day((int) $row['event_at'])) : 'publiée le ' . h(fmt_date((int) $row['created_at'])) ?></span></span>
      <span class="post-actions">
        <a class="btn secondary btn-sm" href="<?= h($self . '?post=' . (int) $row['id']) ?>#annonce-form">Modifier</a>
        <form method="post" action="<?= h($self) ?>"><?= post_fields('delete_post', ['post_id' => (int) $row['id']]) ?>
          <button type="submit" class="secondary btn-sm" data-confirm="Supprimer cette annonce ?">Supprimer</button></form>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (!$posts): ?><p class="muted">Aucune annonce pour le moment.</p><?php endif; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form" id="annonce-form">
    <?= post_fields('save_post', ['post_id' => (int) $p['id']]) ?>
    <h3><?= $p['id'] ? 'Modifier l\'annonce' : 'Publier une annonce' ?></h3>
    <label for="p-title">Titre</label>
    <input type="text" id="p-title" name="title" maxlength="<?= AdminPanel::POST_TITLE_MAX ?>" value="<?= h($p['title']) ?>" placeholder="ex. Conseil d'école du 2e trimestre" required>
    <label for="p-body">Texte (facultatif)</label>
    <textarea id="p-body" name="body" maxlength="<?= AdminPanel::POST_BODY_MAX ?>" data-counter="p-body-len"><?= h($p['body']) ?></textarea>
    <div class="char-hint"><span id="p-body-len">0</span>/<?= AdminPanel::POST_BODY_MAX ?></div>
    <label for="p-event">Date de l'événement (facultatif : l'annonce figure alors à l'agenda)</label>
    <input type="datetime-local" id="p-event" name="event_at" value="<?= h(input_datetime($p['event_at'] !== null ? (int) $p['event_at'] : null)) ?>">
    <label for="p-loc">Lieu (facultatif)</label>
    <input type="text" id="p-loc" name="location" maxlength="<?= AdminPanel::POST_LOCATION_MAX ?>" value="<?= h($p['location']) ?>" placeholder="ex. Préau de l'école">
    <label class="check"><input type="checkbox" name="pinned" value="1"<?= $p['pinned'] ? ' checked' : '' ?>> Épingler en tête des annonces</label>
    <div class="btn-row">
      <button type="submit"><?= $p['id'] ? 'Enregistrer' : 'Publier' ?></button>
      <?php if ($p['id']): ?><a class="btn secondary" href="<?= h($self) ?>#annonces">Annuler</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Codes d'accès ────────────────────────────────────────────────────── -->
<div class="card">
  <h2>🔑 Codes d'accès</h2>
  <p class="muted small">Un <strong>code partagé</strong> (nombre d'utilisations vide) convient à un groupe de parents.
    Un <strong>code individuel</strong> (1 utilisation) ne fonctionne qu'une fois. Révoquer un code coupe l'accès de tous ceux qui l'ont utilisé.</p>

  <?php if ($codes): ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Étiquette</th><th>Utilisations</th><th>Expire</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($codes as $c):
        $revoked = $c['revoked_at'] !== null;
        $expired = $c['expires_at'] !== null && (int) $c['expires_at'] <= $now;
        $full = $c['max_uses'] !== null && (int) $c['uses'] >= (int) $c['max_uses'];
        $state = $revoked ? 'révoqué' : ($expired ? 'expiré' : ($full ? 'épuisé' : 'actif'));
    ?>
      <tr class="<?= $state === 'actif' ? '' : 'inactive' ?>">
        <td><code><?= h($c['code']) ?></code></td>
        <td><?= h($c['label']) ?></td>
        <td><?= (int) $c['uses'] ?> / <?= $c['max_uses'] === null ? '∞' : (int) $c['max_uses'] ?></td>
        <td><?= $c['expires_at'] === null ? '—' : h(fmt_date((int) $c['expires_at'])) ?></td>
        <td><?= h($state) ?></td>
        <td>
          <?php if (!$revoked): ?>
            <form method="post" action="<?= h($self) ?>"><?= post_fields('revoke_code', ['code_id' => (int) $c['id']]) ?>
              <button type="submit" class="secondary btn-sm" data-confirm="Révoquer ce code ? Les personnes entrées avec ce code perdront l'accès.">Révoquer</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted small">Lien d'invitation : <code><?= h(url($slug . '/acces?code=')) ?></code> suivi du code (ouvre la page d'accès avec le code prérempli).</p>
  <?php else: ?>
    <p class="muted">Aucun code pour le moment.</p>
  <?php endif; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form">
    <?= post_fields('create_code') ?>
    <h3>Créer un code</h3>
    <label for="code-label">Étiquette (facultatif, ex. « Parents CE2 »)</label>
    <input type="text" id="code-label" name="label" maxlength="60">
    <label for="code-max">Nombre d'utilisations (vide = illimité)</label>
    <input type="text" inputmode="numeric" id="code-max" name="max_uses" pattern="[0-9]*">
    <label for="code-exp">Expiration (facultatif)</label>
    <input type="datetime-local" id="code-exp" name="expires_at">
    <div class="btn-row"><button type="submit">Générer un code</button></div>
  </form>
</div>

<!-- ── Réglages ─────────────────────────────────────────────────────────── -->
<div class="card">
  <h2>⚙️ Réglages du portail</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= post_fields('save_settings') ?>
    <label for="s-name">Nom affiché</label>
    <input type="text" id="s-name" name="name" maxlength="80" value="<?= h($in->name()) ?>" required>
    <label for="s-intro">Message d'accueil du tableau de bord (facultatif)</label>
    <textarea id="s-intro" name="intro" maxlength="600"><?= h($in->intro()) ?></textarea>
    <div class="btn-row"><button type="submit">Enregistrer</button></div>
  </form>
</div>

<!-- ── Mot de passe ─────────────────────────────────────────────────────── -->
<div class="card">
  <h2>🔒 Mot de passe</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= post_fields('change_password') ?>
    <label for="pw-cur">Mot de passe actuel</label>
    <input type="password" id="pw-cur" name="current_password" autocomplete="current-password" required>
    <label for="pw-new">Nouveau mot de passe (10 caractères minimum)</label>
    <input type="password" id="pw-new" name="new_password" autocomplete="new-password" minlength="10" required>
    <label for="pw-conf">Confirmation</label>
    <input type="password" id="pw-conf" name="confirm_password" autocomplete="new-password" minlength="10" required>
    <div class="btn-row"><button type="submit">Changer le mot de passe</button></div>
  </form>
</div>

<form method="post" action="<?= h($self) ?>" class="logout">
  <?= post_fields('logout') ?><button type="submit" class="secondary">Se déconnecter</button>
</form>
