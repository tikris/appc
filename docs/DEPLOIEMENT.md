# Déploiement sur appc.paris

Cible : hébergement mutualisé (Apache + PHP), domaine `appc.paris`, portail maître en `appc.paris/master`, une instance par établissement en `appc.paris/<slug>/`.

## 1. Prérequis

| Élément | Exigence |
|---|---|
| PHP | ≥ 8.1 avec `pdo_sqlite` et `mbstring` (vérifiable avec `php -m` ou `phpinfo()`) |
| SQLite | ≥ 3.27 (`VACUUM INTO` pour les sauvegardes) — inclus avec PHP 8.1+ sur tout hébergeur récent |
| Apache | `mod_rewrite` et fichiers `.htaccess` autorisés (`AllowOverride`) |
| HTTPS | Certificat actif sur `appc.paris` (Let's Encrypt généralement inclus) |
| Accès | FTP/SFTP au minimum ; SSH et cron fortement recommandés (création d'instances, sauvegardes) |

Si l'hébergeur n'offre pas Apache + `.htaccess` (Nginx seul, par exemple), la règle de réécriture est à reprendre dans la configuration serveur : toute URL qui n'est pas un fichier existant de `public/` doit être servie par `public/index.php`.

## 2. Domaine et racine web

1. **DNS** : faire pointer `appc.paris` (et `www`, si souhaité) vers l'hébergement (enregistrement A/AAAA ou CNAME fourni par l'hébergeur).
2. **Racine web** (docroot) du domaine — deux options :
   - **Recommandée** : docroot = dossier `public/` du dépôt. Les dossiers `src/`, `config/`, `data/` sont alors hors de portée du web.
   - **Repli** si le docroot est imposé : déposer tout le dépôt dans le docroot ; le `.htaccess` racine réécrit tout vers `public/` et `data/.htaccess` interdit l'accès aux bases.
3. Activer la redirection **HTTP → HTTPS** dans le panneau de l'hébergeur.

## 3. Installation

```bash
# Sur le serveur (SSH) — ou déposer l'archive du dépôt par SFTP
git clone https://github.com/tikris/appc.git
cd appc

cp config/config.example.php config/config.php
```

Éditer `config/config.php` :

| Clé | Valeur |
|---|---|
| `app_secret` | Chaîne aléatoire ≥ 32 caractères : `php -r 'echo bin2hex(random_bytes(32));'` |
| `super_password_hash` | Résultat de `php bin/hash-password.php` (vide = console `/_super` désactivée) |
| `data_dir` | Idéalement **hors du docroot**, ex. `/home/<compte>/appc-data` (dossier inscriptible par PHP) |
| `secure_cookies` | `true` en production |
| `ip_header` | `null`, sauf si l'hébergeur place un proxy devant PHP **et** garantit l'en-tête (voir ci-dessous) |
| `debug` | `false` |

> `config/config.php` contient des secrets : il est ignoré par git et **ne doit jamais être commité** (le dépôt est public).

**Adresse IP réelle** : sur certains mutualisés, `REMOTE_ADDR` est celle d'un répartiteur de charge. La limitation d'essais de saisie de code s'appliquerait alors à tout le monde en bloc. Test : se connecter depuis deux réseaux différents et comparer. Si besoin, renseigner `ip_header` (ex. `HTTP_X_FORWARDED_FOR`) ; l'application lit la **dernière** valeur de la liste, celle ajoutée par le proxy de l'hébergeur.

### Créer les instances

```bash
php bin/create-instance.php master "Portail maître APPC"
php bin/create-instance.php ecole-etienne-marcel "École Étienne Marcel" elementaire
```

Le troisième argument facultatif choisit le **modèle d'établissement** : `elementaire` (par défaut), `maternelle`, `college` ou `vide` (voir `src/Templates.php`). Le modèle prépare un portail prêt à l'emploi : message d'accueil, annonce de bienvenue, une consultation publiée avec ses thèmes, un sondage et des inscriptions en brouillon.

Chaque commande affiche le **mot de passe administrateur provisoire** (à transmettre au représentant, qui le changera à sa première connexion). Sans SSH : utiliser la console `https://appc.paris/_super` (même résultat, choix du modèle dans une liste, mot de passe affiché une seule fois).

Règles pour l'identifiant (`slug`) : 3 à 40 caractères, minuscules, chiffres et tirets ; les mots réservés (`admin`, `assets`, `public`, `acces`…) sont refusés.

### Vérifications après mise en ligne

- [ ] `https://appc.paris/` affiche la page d'accueil
- [ ] `https://appc.paris/master/` redirige vers `/master/acces` (portail protégé)
- [ ] `https://appc.paris/master/admin` : connexion administrateur, puis création d'un code et entrée avec ce code depuis un autre navigateur
- [ ] Publier le sondage du modèle, y répondre depuis l'autre navigateur, vérifier le résultat ; puis le repasser en brouillon
- [ ] Les fichiers sensibles ne sont **pas** servis : `https://appc.paris/data/master.sqlite`, `/config/config.php`, `/src/App.php`, `/migrations/001_init.sql` répondent 403 ou 404
- [ ] Les cookies portent les drapeaux `Secure`, `HttpOnly`, `SameSite=Lax` (outils de développement du navigateur)
- [ ] En-têtes présents : `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`
- [ ] Un essai de 9 codes faux de suite déclenche le blocage (« Trop d'essais »)

## 3 bis. Variante OVHcloud : déploiement Git par webhook

Sur un hébergement web OVHcloud, chaque `git push` sur `main` peut mettre le site à jour automatiquement ([documentation OVHcloud](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/git-integration-webhosting)).

1. **Préparer l'hébergement** : PHP ≥ 8.1 sélectionné pour le site ; domaine `appc.paris` attaché au site (multisite) ; dossier d'installation **vide** (OVHcloud refuse l'association sinon).
2. **Associer le dépôt** : espace client OVHcloud, *Web Cloud > Hébergements > Mes sites > ⁝ > Configurer Git* ; dépôt `https://github.com/tikris/appc` (public : aucune clé SSH), branche `main`. **Valider le formulaire** : copier l'URL du webhook ne suffit pas. L'encadré *Informations sur le dernier déploiement* donne la cause d'un échec.
3. **Ajouter le webhook dans GitHub** : *Settings > Webhooks > Add webhook* ; *Payload URL* = URL du webhook affichée par OVHcloud ; *Content type* = `application/json` ; événement *Just the push event*.
4. **Tester** : onglet *Recent Deliveries* du webhook. Réponse **200** = déploiement lancé. Réponse **404** « could not retrieve the website » = l'association Git n'est pas (encore) active côté OVHcloud : reprendre l'étape 2, attendre quelques minutes, puis *Redeliver*.
5. **Après le premier déploiement** : déposer `config/config.php` (SFTP, fichier non versionné), vérifier `https://appc.paris/`, puis créer les instances depuis `/_super`.

> **L'URL du webhook est un secret** : ne la collez jamais dans un ticket, un commit ou un message public. Si elle a fuité, régénérez-la dans OVHcloud (dissocier puis réassocier Git) et mettez à jour le webhook GitHub.

Points d'attention :

- Ne modifiez pas les fichiers versionnés directement sur le serveur : un conflit bloquerait les déploiements suivants.
- `config/config.php` et `data/` sont ignorés par git, mais OVHcloud ne précise pas son comportement face aux fichiers non versionnés lors d'un déploiement forcé : **planifiez `bin/backup.php`** (tâche planifiée) et gardez une copie hors du serveur.
- Le dossier `data/` reste protégé par `data/.htaccess` ; ne le déplacez hors du docroot (`data_dir`) que si vous connaissez le chemin absolu exact.

## 4. Sauvegardes

Les données d'un établissement tiennent dans **un fichier** `data/<slug>.sqlite`. Sauvegarde cohérente, y compris site en ligne :

```bash
php bin/backup.php /home/<compte>/appc-backups 14      # conserve les 14 dernières copies par instance
```

Tâche cron quotidienne (exemple) :

```
17 3 * * * /usr/bin/php /home/<compte>/appc/bin/backup.php /home/<compte>/appc-backups 14
```

- Le dossier de sauvegarde doit être **hors du docroot**.
- Faire de temps en temps une **copie hors hébergeur** (téléchargement SFTP).
- **Restauration** : remplacer `data/<slug>.sqlite` par la copie voulue (renommée sans l'horodatage). Les sessions et participants de la copie sont restaurés avec elle.
- Tester une restauration **avant** d'ouvrir le portail aux parents.

## 5. Mises à jour

```bash
cd appc && git pull
```

- Les migrations de schéma (`migrations/NNN_*.sql`) sont appliquées automatiquement à la première ouverture de chaque base. **Sauvegarder avant** toute mise à jour qui ajoute une migration.
- **Migration 002 (activités)** : la consultation unique de chaque instance existante devient l'activité « Propositions des parents », publiée, avec ses dates, son plafond de propositions, ses thèmes, idées, votes et notes. Les codes et le mot de passe administrateur sont inchangés. Les instances existantes ne reçoivent pas le contenu d'exemple des modèles (réservé aux nouvelles instances). L'ancien export `/<slug>/admin/export.csv` est remplacé par un export par activité.
- Aucun build, aucun gestionnaire de dépendances.
- `config/config.php` et `data/` ne sont pas touchés par `git pull`.

## 6. Dépannage

| Symptôme | Piste |
|---|---|
| Page blanche / erreur 500 | Consulter le journal d'erreurs PHP de l'hébergeur ; passer momentanément `debug` à `true` **puis le remettre à `false`** |
| « Configuration manquante » | `config/config.php` absent ou `app_secret` < 32 caractères |
| 404 sur `/master/` mais `/` fonctionne | Réécriture d'URL inactive : vérifier `mod_rewrite` / `AllowOverride All` |
| « unable to open database file » | `data_dir` inexistant ou non inscriptible par PHP |
| Impossible de se connecter / CSRF « session expirée » | Cookies bloqués, ou `secure_cookies: true` alors que le site est servi en HTTP |
| Tout le monde est bloqué après quelques essais | `REMOTE_ADDR` identique pour tous : voir « Adresse IP réelle » |

## 7. Suppression d'une instance

Supprimer le fichier `data/<slug>.sqlite` (et ses copies dans le dossier de sauvegarde). Il n'y a aucune autre trace : pas de registre central, pas de table partagée.
