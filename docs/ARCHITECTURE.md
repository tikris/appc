# Architecture

## Origine et périmètre

Ce portail reprend l'**interface et les règles** du portail « Convention citoyenne » (`christophe-amalric.fr/cc/`, un fichier PHP unique) : idées classées par thème, votes, notes de contexte, administration par mot de passe, fenêtre de vote datée. Le code d'origine a été lu pour aligner les règles ; les écarts qui subsistent sont listés et justifiés en [§ Écarts avec le portail d'origine](#écarts-avec-le-portail-dorigine).

Ajouts par rapport au portail d'origine : **accès par code** délivré par l'administrateur, **multi-établissements** (une instance par URL), console de création d'instances, export, sauvegarde, durcissement sécurité.

Deuxième version (migration `002_activites.sql`) : un établissement n'a plus une seule consultation mais des **activités** (plusieurs consultations, des sondages, des inscriptions bénévoles), chacune avec ses dates et son état brouillon/publié, plus des **annonces** et un **agenda** sur un tableau de bord. Les instances sont créées à partir de **modèles d'établissement** (`src/Templates.php`).

## Vue d'ensemble

```
Navigateur ──HTTPS──► Apache (.htaccess) ──► public/index.php ──► App (routeur)
                                                                    ├─ Portal            /<slug>/ (tableau de bord), /acces, /quitter
                                                                    │   └─ ConsultationPage | PollPage | SignupPage   /<slug>/activite/<id>
                                                                    ├─ AdminPanel        /<slug>/admin (activités, annonces, codes, réglages)
                                                                    ├─ ActivityAdmin     /<slug>/admin/activite/<id>, export.csv
                                                                    └─ SuperAdmin        /_super
                                          data/<slug>.sqlite  (une base par établissement)
```

Données : `Repo` (activités, consultations, annonces, codes), `PollRepo` (sondages), `SignupRepo` (inscriptions) ; `Activity` est l'objet valeur d'une activité (état, fenêtre d'ouverture) ; `Instance` porte les réglages de l'établissement ; `Templates` remplit une instance neuve.

- **PHP 8.1+, sans framework ni dépendance** : tient sur un mutualisé, rien à compiler ni à installer.
- **SQLite, un fichier par instance** : isolation des données entre établissements, sauvegarde par copie, suppression = effacer un fichier. Pas de registre central : la liste des instances est la liste des fichiers.
- **Rendu côté serveur**, aucun script en ligne ni style en ligne : `app.js` ne fait que compter les caractères et confirmer les actions sensibles. Cela permet une CSP stricte (`default-src 'none'; style-src 'self'; script-src 'self'`). Les barres de résultats et de remplissage utilisent l'élément natif `<progress>`, qui ne demande aucun style en ligne.

## Routes

| Méthode | Chemin | Accès | Rôle |
|---|---|---|---|
| GET | `/` | public | Page d'accueil |
| GET/POST | `/_super` | mot de passe super-admin | Créer une instance (avec un modèle), réinitialiser un mot de passe administrateur |
| GET | `/<slug>/` | participant ou admin | Tableau de bord : message d'accueil, agenda, activités publiées, annonces |
| GET | `/<slug>/activite/<id>` | participant ou admin | Page d'une activité publiée (brouillon : admin seulement) ; consultation : `?theme=`, `?edit=`, `?note=` |
| POST | `/<slug>/activite/<id>` | participant ou admin | Consultation : `add_idea`, `edit_idea`, `delete_idea`, `vote`, `save_note`, `delete_note` ; sondage : `answer` ; inscriptions : `join`, `leave` |
| GET/POST | `/<slug>/acces` | public | Saisie du code (`?code=` préremplit) |
| POST | `/<slug>/quitter` | participant | Oublier cet appareil |
| GET/POST | `/<slug>/admin` | mot de passe de l'instance | Activités (création), annonces, codes, réglages, mot de passe (`?post=` : modifier une annonce) |
| GET/POST | `/<slug>/admin/activite/<id>` | admin | Réglages, publication, clôture, suppression ; thèmes, réponses proposées ou créneaux et inscrits |
| GET | `/<slug>/admin/activite/<id>/export.csv` | admin | Export de l'activité |

## Modèle d'accès

1. L'administrateur crée un **code** `XXXX-XXXX` (8 caractères sur 32 → 40 bits), partagé ou à usage limité, avec expiration facultative.
2. Un visiteur saisit le code → un **participant** est créé et un jeton aléatoire (256 bits) est posé dans un cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, limité au chemin `/<slug>`. Seul le **hachage SHA-256** du jeton est stocké.
3. **Révoquer un code** coupe l'accès de tous les participants entrés avec lui (la vérification joint `participants` à `codes` à chaque requête). **Expirer** un code n'empêche que de nouvelles entrées.
4. L'**administrateur** s'authentifie par mot de passe (`password_hash`), reçoit une session de 8 h (jeton haché en base). Il peut consulter et modérer, mais n'a pas d'identité de participant : il ne vote pas et ne propose pas sans code.
5. Anti-force-brute : 8 échecs par IP (hachée avec `app_secret`) et par fenêtre de 15 minutes, pour les codes comme pour les mots de passe. Pas de plafond global (il permettrait un déni de service par verrouillage).

Le **cloisonnement entre établissements** tient à trois choses : une base par instance (un code n'existe que dans sa base), un cookie limité au chemin de l'instance, et des identifiants d'instance validés par expression régulière (aucun chemin de fichier construit à partir d'une saisie non validée).

## Modèle de données

Voir [`migrations/001_init.sql`](../migrations/001_init.sql) et [`migrations/002_activites.sql`](../migrations/002_activites.sql).

| Table | Contenu |
|---|---|
| `settings` | Réglages de l'établissement : nom, message d'accueil, hash du mot de passe administrateur |
| `codes`, `participants`, `admin_sessions` | Accès (voir ci-dessus) |
| `activities` | Une ligne par activité : type (`consultation`, `poll`, `signup`), titre, description, ouverture, échéance, clôture manuelle, publication (`published_at` NULL = brouillon) et options propres au type |
| `themes`, `ideas`, `votes`, `notes` | Consultations ; thèmes et idées portent `activity_id` (votes et notes suivent leur idée) |
| `poll_options`, `poll_answers` | Sondages : une ligne par réponse cochée, clé `(option, participant)` |
| `slots`, `signups` | Inscriptions : créneaux à capacité ; inscrits avec prénom et `owner_hash` (voir [SECURITE-RGPD.md](SECURITE-RGPD.md)) |
| `posts` | Annonces ; avec `event_at`, elles figurent à l'agenda |

Supprimer une activité supprime tout son contenu (clés étrangères `ON DELETE CASCADE`). Une table technique `attempts` est créée à la demande par `Limiter`.

Les migrations sont versionnées par `PRAGMA user_version`, appliquées automatiquement et sous verrou à l'ouverture d'une base. Règle : ne jamais modifier une migration publiée, en ajouter une. La migration 002 transforme la consultation unique d'une base existante en activité n° 1, publiée, avec ses dates, son plafond de propositions, ses thèmes, idées, votes et notes.

## Décisions et compromis

| Décision | Raison | Compromis assumé |
|---|---|---|
| SQLite par instance plutôt que MySQL partagé | Isolation, sauvegarde et suppression simples, aucune configuration | Pas de requête transverse ; écritures sérialisées (largement suffisant pour des centaines d'utilisateurs) |
| Cookie anonyme plutôt que compte | Aucune donnée personnelle, friction minimale pour les parents | Un participant qui efface ses cookies perd l'accès à ses propres propositions (re-saisie du code possible, mais il redevient un nouveau participant) |
| Code visible en clair pour l'administrateur | Un bénévole doit pouvoir le retrouver et le rediffuser | Une fuite de la base expose des codes d'accès à un forum à faible enjeu ; la base est hors web et non versionnée |
| Couleurs de thème limitées à 8 teintes | Pas de style en ligne → CSP stricte | Moins de liberté graphique |
| Un vote par idée, définitif, sans plafond global, jamais pour sa propre idée | Identique au portail d'origine, simple à expliquer | Pas de « budget de voix » ; vote public en cours de scrutin (effet moutonnier possible) |
| Pas de pré-modération | Identique à l'origine, fluidité | La modération est a posteriori ; voir [SECURITE-RGPD.md](SECURITE-RGPD.md) |
| Console `/_super` en plus du CLI | Beaucoup de mutualisés n'ont pas de SSH | Surface d'attaque supplémentaire (mot de passe fort, limitation d'essais, désactivable en laissant `super_password_hash` vide) |
| Activités dans la base de l'établissement plutôt qu'une instance par activité | Un seul code d'accès et un seul tableau de bord pour toute l'année | Révoquer un code coupe l'accès à toutes les activités |
| Brouillon puis publication | L'administrateur prépare une activité (réponses, créneaux) sans que les parents voient une page vide | Un état de plus à expliquer ; une activité ne se publie pas sans contenu minimal (1 thème, 2 réponses, 1 créneau) |
| Réponse à un sondage modifiable jusqu'à la clôture, contrairement au vote d'une consultation | Un sondage sert à trouver une date ou un consensus : on change d'avis quand les contraintes changent | Deux règles différentes selon le type d'activité |
| Réponses proposées verrouillées dès la première réponse | Renommer ou supprimer une réponse fausserait celles déjà données | Une faute de frappe découverte tard impose de recréer le sondage |
| Prénom des inscrits non relié au participant (`owner_hash` dérivé du jeton du navigateur), date d'inscription arrondie au jour | Une copie de la base ne donne pas de lien direct entre un prénom et des propositions ou des votes | Effacer ses cookies empêche d'annuler soi-même son inscription (l'administrateur le peut) ; un recoupement par dates reste possible dans un très petit établissement (voir [SECURITE-RGPD.md](SECURITE-RGPD.md)) |
| Prénoms visibles de l'administrateur seul par défaut | Protection des données dès la conception (RGPD, art. 25) | L'administrateur doit cocher l'option si les bénévoles veulent savoir avec qui ils seront |
| Modèles d'établissement dans le code (`Templates.php`) plutôt qu'en base | Versionnés, relus, identiques pour tous ; aucune interface à maintenir | Modifier un modèle demande un déploiement |

## Écarts avec le portail d'origine

Règles **constatées** dans le code d'origine et reprises à l'identique :

- Titre de 3 à 120 caractères, description facultative de 400 caractères au plus, note de contexte de 600 caractères au plus
- Thème obligatoire ; 4 thèmes par défaut identiques (Rythmes scolaires, Organisation périscolaire, Encadrement des enfants, Protection des enfants)
- Un vote par idée et par participant, définitif ; compteurs et classement visibles pendant le scrutin ; classement par votes décroissants puis ancienneté
- Fenêtre de vote datée (« pas encore commencé », « J-N », « terminée ») ; propositions, modifications et votes impossibles hors fenêtre ; notes de contexte toujours possibles
- Un participant modifie ou supprime ses propres propositions, tant que le scrutin est ouvert

Écarts **voulus** (à valider avec l'APPC) :

| Sujet | Portail d'origine | appc | Raison |
|---|---|---|---|
| Identité du participant | Empreinte de l'adresse IP (premier élément de `X-Forwarded-For` ou, à défaut, `REMOTE_ADDR`) | Cookie aléatoire délivré par un code | Une adresse IP n'identifie pas une personne (Wi-Fi d'établissement, réseau mobile partagé) et un en-tête `X-Forwarded-For` peut être choisi par le visiteur |
| Accès | Libre | Code d'accès de l'administrateur | Objet de la demande |
| Vote pour sa propre idée | Bouton masqué, mais accepté par le serveur | Refusé par le serveur (403) | Règle appliquée au bon endroit |
| Notes de contexte | Une note par idée, modifiable par tous | Une note par participant et par idée ; supprimable par son auteur ou l'administrateur | Évite l'écrasement silencieux d'une note par une autre |
| Plafond de propositions | Aucun | 5 par participant (réglable de 1 à 50) | Limite le spam depuis un même navigateur |
| Suppression | Logique (`deleted = 1`, la ligne reste) | Définitive, avec votes et notes | Droit à l'effacement ; pas de données mortes |
| Modification d'une idée | Titre et description | Thème, titre et description | Corriger une erreur de classement sans tout recréer |
| Fenêtre de vote | Constantes dans le fichier (début + nombre de jours) | Début, fin et clôture manuelle réglables par l'administrateur, pour chaque activité | Plusieurs établissements et plusieurs consultations, sans modifier de code |
| Administration | Mot de passe unique dans le fichier, suppression d'idées | Mot de passe haché par instance, codes, thèmes, réglages, export, clôture | Plusieurs administrateurs indépendants |
| Journal SQLite | Mode WAL | Journal par défaut | Certains hébergements mutualisés (disques réseau) gèrent mal le mode WAL |

## Limites connues

- Pas de notification, pas de e-mail (volontaire : aucune adresse n'est collectée).
- Pas de recherche textuelle ni de pagination (adapté à quelques dizaines à quelques centaines d'idées ou d'annonces).
- Pas d'export de l'agenda vers un calendrier (fichier `.ics`) ni d'événement récurrent.
- Pas de duplication d'une activité (reprendre la kermesse d'une année sur l'autre), ni d'archivage d'instance en lecture seule, ni de purge automatique.
- Pas de journal d'audit des actions d'administration ; un seul compte administrateur par établissement.
- Pas de limite de débit des actions autres que la saisie de code et de mot de passe.
- L'auto-hébergement derrière un proxy exige de régler `ip_header` (voir [DEPLOIEMENT.md](DEPLOIEMENT.md)).

Ces points sont candidats à des tickets dans le dépôt.
