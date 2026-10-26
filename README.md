# appc

L'association Parents à Paris Centre met à disposition des représentants de parents d'élèves un **portail prêt à l'emploi pour chaque établissement**, pour animer la vie des parents tout au long de l'année.

Chaque établissement dispose de sa propre instance (`appc.paris/<établissement>/`), dont le tableau de bord réunit :

| Module | Usage |
|---|---|
| 💡 **Consultations** | Les parents proposent des idées classées par thème, votent et ajoutent des notes de contexte (cadre légal, réglementaire…). Plusieurs consultations par établissement, chacune avec ses dates et ses thèmes. |
| 📊 **Sondages** | Une question à choix unique ou multiple (date de réunion, oui/non…), réponse modifiable jusqu'à la clôture, résultats visibles en direct, après avoir répondu ou à la clôture. |
| 🙋 **Inscriptions bénévoles** | Des créneaux à capacité limitée (stand de kermesse, accompagnement de sortie) ; chacun s'inscrit avec un prénom ou un pseudo. |
| 📢 **Annonces et agenda** | Les informations et les dates de l'année ; les échéances des activités s'ajoutent automatiquement à l'agenda. |

L'accès est réservé aux personnes munies d'un **code délivré par l'administrateur** de l'instance, généralement le représentant des parents d'élèves. À la création, l'instance est remplie selon un **modèle d'établissement** (maternelle, élémentaire, collège ou vide) : message d'accueil, annonce de bienvenue, une consultation publiée, et un sondage et des inscriptions en brouillon prêts à adapter.

| Adresse | Rôle |
|---|---|
| `appc.paris/master` | Instance de référence (première instance créée) |
| `appc.paris/ecole-etienne-marcel` | Exemple d'instance d'établissement : tableau de bord |
| `appc.paris/<slug>/activite/<id>` | Une activité (consultation, sondage ou inscriptions) |
| `appc.paris/<slug>/admin` | Espace de l'administrateur de l'instance |
| `appc.paris/_super` | Console de création des instances (équipe technique APPC) |

## Principes

- **Données personnelles minimales** : ni compte, ni e-mail. Un participant est un navigateur ayant saisi un code valide (cookie technique). Seules les inscriptions bénévoles demandent un prénom ou un pseudo, **non relié** en base aux propositions ni aux votes, et visible de l'administrateur seul par défaut.
- **Un fichier SQLite par établissement** : isolation naturelle, sauvegarde et suppression triviales.
- **Hébergement mutualisé suffisant** : PHP ≥ 8.1, aucune dépendance, aucune compilation.
- **Code ouvert** (GPL-3.0) : les données de chaque instance restent privées, le code est public.

## Démarrage rapide (développement)

```bash
git clone https://github.com/tikris/appc.git && cd appc
cp config/config.example.php config/config.php
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # à coller dans app_secret
php bin/create-instance.php master "Portail maître" elementaire  # modèle facultatif ; affiche le mot de passe provisoire
php -S 127.0.0.1:8000 -t public public/index.php
# → http://127.0.0.1:8000/master/admin
```

Tests de bout en bout (serveur PHP temporaire, base jetable) :

```bash
bash tests/smoke.sh
```

## Documentation

| Document | Pour qui |
|---|---|
| [docs/ADMINISTRATION.md](docs/ADMINISTRATION.md) | Représentants des parents : codes, activités, annonces, clôture, export |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en ligne sur `appc.paris`, sauvegardes, mises à jour |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Choix techniques, modèle de données, écarts avec le portail d'origine, limites |
| [docs/SECURITE-RGPD.md](docs/SECURITE-RGPD.md) | Mesures de sécurité, données traitées, points à traiter |

## Arborescence

```
public/        racine web : index.php (contrôleur frontal), assets/
src/           code PHP (routeur, accès par code, activités, modèles d'établissement, dépôts de données, contrôleurs)
views/         gabarits HTML
migrations/    schéma SQLite, appliqué automatiquement à l'ouverture d'une base
config/        config.example.php (config.php est ignoré par git)
data/          bases SQLite (ignorées par git, accès web interdit)
bin/           create-instance, hash-password, backup
tests/         smoke.sh
docs/          documentation
```

## Licence

GPL-3.0 — voir [LICENSE](LICENSE).
