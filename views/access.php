<div class="card">
  <h2>🔑 Accès au portail</h2>
  <p class="muted">Saisissez le code d'accès remis par le représentant des parents de <?= h($in->name()) ?>.</p>

  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

  <form method="post" action="<?= h(url($in->slug . '/acces')) ?>" autocomplete="off">
    <?= \Appc\Csrf::field() ?>
    <label for="code">Code d'accès</label>
    <input type="text" id="code" name="code" value="<?= h($prefill) ?>" maxlength="20"
           placeholder="XXXX-XXXX" autocapitalize="characters" spellcheck="false" required>
    <div class="btn-row"><button type="submit">Entrer</button></div>
  </form>
  <p class="muted small">Pas de code ? Demandez-le au représentant des parents de votre classe ou de votre établissement.</p>
</div>

<?php $footerExtra = '<p><a href="' . h(url($in->slug . '/admin')) . '">Administration</a></p>'; ?>
