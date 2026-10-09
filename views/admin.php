<?php
use Appc\Csrf;

$slug = $in->slug;
$self = url($slug . '/admin');
$colors = [1 => 'Bleu', 2 => 'Vert', 3 => 'Orange', 4 => 'Violet', 5 => 'Rouge', 6 => 'Cyan', 7 => 'Jaune', 8 => 'Gris'];
$post = static fn(string $action, array $fields = []): string => Csrf::field()
    . '<input type="hidden" name="action" value="' . h($action) . '">'
    . implode('', array_map(
        static fn($k, $v) => '<input type="hidden" name="' . h((string) $k) . '" value="' . h((string) $v) . '">',
        array_keys($fields),
        $fields
    ));
$now = time();
?>

<?php if ($flash): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<?php if ($mustChange): ?>
  <div class="closed-banner">⚠️ Votre mot de passe est provisoire : changez-le dans la section « Mot de passe » ci-dessous.</div>
<?php endif; ?>

<div class="card">
  <h2>📊 Vue d'ensemble</h2>
  <p>
    <strong><?= (int) $stats['ideas'] ?></strong> proposition(s) ·
    <strong><?= (int) $stats['votes'] ?></strong> vote(s) ·
    <strong><?= (int) $stats['notes'] ?></strong> note(s) de contexte ·
    <strong><?= (int) $stats['participants'] ?></strong> participant(s) entré(s) avec un code
  </p>
  <p>État : <strong><?= ['before' => 'pas encore ouvert', 'open' => 'ouvert', 'closed' => 'clos'][$phase] ?></strong>
    <?php if ($phase === 'before'): ?>(ouverture : <?= h(fmt_datetime((int) $in->opensAt())) ?>)<?php endif; ?>
    <?php if ($in->closesAt() !== null): ?>(échéance : <?= h(fmt_datetime($in->closesAt())) ?>)<?php endif; ?>
  </p>
  <div class="btn-row">
    <?php if ($phase !== 'closed'): ?>
      <form method="post" action="<?= h($self) ?>"><?= $post('close_now') ?>
        <button type="submit" class="danger" data-confirm="Clôturer les votes maintenant ? Plus aucune proposition ni vote ne sera possible.">Clôturer les votes maintenant</button></form>
    <?php else: ?>
      <form method="post" action="<?= h($self) ?>"><?= $post('reopen') ?><button type="submit">Rouvrir les votes</button></form>
    <?php endif; ?>
    <a class="btn secondary" href="<?= h(url($slug . '/admin/export.csv')) ?>">Exporter (CSV)</a>
  </div>
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
            <form method="post" action="<?= h($self) ?>"><?= $post('revoke_code', ['code_id' => (int) $c['id']]) ?>
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
    <?= $post('create_code') ?>
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
  <h2>⚙️ Réglages</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= $post('save_settings') ?>
    <label for="s-name">Nom affiché</label>
    <input type="text" id="s-name" name="name" maxlength="80" value="<?= h($in->name()) ?>" required>
    <label for="s-intro">Message d'accueil (facultatif)</label>
    <textarea id="s-intro" name="intro" maxlength="600"><?= h($in->intro()) ?></textarea>
    <label for="s-start">Début des propositions et des votes (facultatif : vide = ouvert tout de suite)</label>
    <input type="datetime-local" id="s-start" name="opens_at" value="<?= h(input_datetime($in->opensAt())) ?>">
    <label for="s-end">Fin des propositions et des votes (facultatif)</label>
    <input type="datetime-local" id="s-end" name="closes_at" value="<?= h(input_datetime($in->closesAt())) ?>">
    <label for="s-max">Propositions maximum par participant</label>
    <input type="text" inputmode="numeric" id="s-max" name="max_ideas" pattern="[0-9]+" value="<?= $in->maxIdeas() ?>" required>
    <div class="btn-row"><button type="submit">Enregistrer</button></div>
  </form>
</div>

<!-- ── Thèmes ───────────────────────────────────────────────────────────── -->
<div class="card">
  <h2>🏷️ Thèmes</h2>
  <?php foreach ($themes as $t): $n = (int) ($themeCount[(int) $t['id']] ?? 0); ?>
    <form method="post" action="<?= h($self) ?>" class="theme-row">
      <?= $post('update_theme', ['theme_id' => (int) $t['id']]) ?>
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
        <?= $post('delete_theme', ['theme_id' => (int) $t['id']]) ?>
        <button type="submit" class="secondary btn-sm" data-confirm="Supprimer ce thème ?">Supprimer « <?= h($t['name']) ?> »</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>

  <form method="post" action="<?= h($self) ?>" class="sub-form">
    <?= $post('add_theme') ?>
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

<!-- ── Mot de passe ─────────────────────────────────────────────────────── -->
<div class="card">
  <h2>🔒 Mot de passe</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= $post('change_password') ?>
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
  <?= $post('logout') ?><button type="submit" class="secondary">Se déconnecter</button>
</form>
