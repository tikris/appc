<?php
use Appc\Csrf;

$self = url('_super');
?>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

<?php if ($secrets): ?>
  <div class="card secret">
    <h2><?= $secrets['kind'] === 'created' ? '✅ Instance créée' : '🔄 Mot de passe réinitialisé' ?> : <?= h($secrets['slug']) ?></h2>
    <p><strong>À transmettre maintenant à l'administrateur de l'établissement — ce mot de passe ne sera plus affiché.</strong></p>
    <p>Adresse : <code><?= h(url($secrets['slug'] . '/admin')) ?></code></p>
    <p>Mot de passe provisoire : <code class="big"><?= h($secrets['password']) ?></code></p>
    <p class="muted small">Il devra le changer à sa première connexion, puis créer les codes d'accès depuis son espace.</p>
  </div>
<?php endif; ?>

<div class="card">
  <h2>🏫 Instances</h2>
  <?php if ($instances): ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Adresse</th><th>Nom</th><th>État</th><th>Idées</th><th>Votes</th><th>Participants</th><th>Créée</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($instances as $i): ?>
      <tr>
        <td><a href="<?= h(url($i['slug'] . '/')) ?>"><code>/<?= h($i['slug']) ?>/</code></a></td>
        <td><?= h($i['name']) ?></td>
        <td><?= ['before' => 'pas encore ouverte', 'open' => 'ouverte', 'closed' => 'close'][$i['phase']] ?></td>
        <td><?= (int) $i['stats']['ideas'] ?></td>
        <td><?= (int) $i['stats']['votes'] ?></td>
        <td><?= (int) $i['stats']['participants'] ?></td>
        <td><?= $i['created'] ? h(fmt_date($i['created'])) : '—' ?></td>
        <td>
          <form method="post" action="<?= h($self) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="slug" value="<?= h($i['slug']) ?>">
            <button type="submit" class="secondary btn-sm" data-confirm="Réinitialiser le mot de passe administrateur de cette instance ?">Réinitialiser le mot de passe</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="muted">Aucune instance. Créez d'abord <code>master</code>.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>➕ Créer une instance</h2>
  <form method="post" action="<?= h($self) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create">
    <label for="slug">Identifiant dans l'URL (minuscules, chiffres, tirets)</label>
    <input type="text" id="slug" name="slug" pattern="[a-z0-9][a-z0-9\-]{1,38}[a-z0-9]" placeholder="ecole-etienne-marcel" required>
    <label for="name">Nom affiché</label>
    <input type="text" id="name" name="name" maxlength="80" placeholder="École Étienne Marcel" required>
    <div class="btn-row"><button type="submit">Créer l'instance</button></div>
  </form>
</div>

<form method="post" action="<?= h($self) ?>" class="logout">
  <?= Csrf::field() ?><input type="hidden" name="action" value="logout">
  <button type="submit" class="secondary">Se déconnecter</button>
</form>
