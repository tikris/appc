# appc

L'association Parents à Paris Centre met à disposition un portail collaboratif dédié à chaque établissement, pour faciliter les discussions et délibérations locales.

Chaque établissement dispose de sa propre instance (`appc.paris/<établissement>/`), où les parents peuvent **proposer des idées, voter et ajouter des notes de contexte** (cadre légal, réglementaire…), classées par thème. L'accès est réservé aux personnes munies d'un **code délivré par l'administrateur** de l'instance, généralement le représentant des parents d'élèves.

| Adresse | Rôle |
|---|---|
| `appc.paris/master` | Instance de référence (première instance créée) |
| `appc.paris/ecole-etienne-marcel` | Exemple d'instance d'établissement |
| `appc.paris/<slug>/admin` | Espace de l'administrateur de l'instance |
| `appc.paris/_super` | Console de création des instances (équipe technique APPC) |

## Principes

- **Aucune donnée personnelle collectée** : ni nom, ni e-mail, ni compte. Un participant est un navigateur ayant saisi un code valide (cookie technique).
- **Un fichier SQLite par établissement** : isolation naturelle, sauvegarde et suppression triviales.
- **Hébergement mutualisé suffisant** : PHP ≥ 8.1, aucune dépendance, aucune compilation.
- **Code ouvert** (GPL-3.0) : les données de chaque instance restent privées, le code est public.

## Démarrage rapide (développement)

```bash
git clone https://github.com/tikris/appc.git && cd appc
cp config/config.example.php config/config.php
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # à coller dans app_secret
php bin/create-instance.php master "Portail maître"  # affiche le mot de passe administrateur provisoire
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
| [docs/ADMINISTRATION.md](docs/ADMINISTRATION.md) | Représentants des parents : gérer codes, thèmes, clôture, export |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | Mise en ligne sur `appc.paris`, sauvegardes, mises à jour |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Choix techniques, modèle de données, écarts avec le portail d'origine, limites |
| [docs/SECURITE-RGPD.md](docs/SECURITE-RGPD.md) | Mesures de sécurité, données traitées, points à traiter |

## Arborescence

```
public/        racine web : index.php (contrôleur frontal), assets/
src/           code PHP (routeur, accès par code, dépôt de données, contrôleurs)
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
