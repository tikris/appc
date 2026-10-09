<div class="card">
  <h2>🔐 Console super-administrateur</h2>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url('_super')) ?>">
    <?= \Appc\Csrf::field() ?>
    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <div class="btn-row"><button type="submit">Connexion</button></div>
  </form>
</div>
