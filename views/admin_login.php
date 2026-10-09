<div class="card">
  <h2>🔐 Connexion administrateur</h2>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url($in->slug . '/admin')) ?>">
    <?= \Appc\Csrf::field() ?>
    <input type="hidden" name="action" value="login">
    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <div class="btn-row"><button type="submit">Connexion</button></div>
  </form>
  <p class="muted small"><a href="<?= h(url($in->slug . '/')) ?>">Retour au portail</a></p>
</div>
