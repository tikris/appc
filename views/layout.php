<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($pageTitle ?? 'APPC') ?></title>
<link rel="stylesheet" href="<?= h(url('assets/style.css')) ?>">
</head>
<body>

<header>
  <h1>💡 <?= h($heading ?? 'APPC') ?></h1>
  <?php if (!empty($headerLink)): ?>
    <a href="<?= h($headerLink[0]) ?>"><?= h($headerLink[1]) ?></a>
  <?php endif; ?>
</header>

<div class="container">
<?= $content ?>
</div>

<footer>
  <p>Portail proposé par l'association Parents à Paris Centre (APPC) pour faciliter les discussions et délibérations locales.</p>
  <p>Aucun nom ni adresse e-mail n'est demandé. Un cookie technique mémorise votre accès sur cet appareil.</p>
  <p><a href="https://github.com/tikris/appc">Code source libre (GPL-3.0)</a></p>
  <?= $footerExtra ?? '' ?>
</footer>

<script src="<?= h(url('assets/app.js')) ?>"></script>
</body>
</html>
