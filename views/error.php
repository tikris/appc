<div class="card">
  <h2><?= h($pageTitle ?? 'Erreur') ?> <span class="muted">(<?= (int) $errorCode ?>)</span></h2>
  <p><?= h($message) ?></p>
  <p><a href="<?= h(url('')) ?>">Retour à l'accueil</a></p>
</div>
