# Guide de l'administrateur d'établissement

Ce guide s'adresse au **représentant des parents** qui anime le portail de son établissement. Aucune compétence technique n'est nécessaire.

## 1. Première connexion

1. L'équipe APPC vous transmet l'adresse de votre espace (ex. `https://appc.paris/ecole-etienne-marcel/admin`) et un **mot de passe provisoire**.
2. Connectez-vous, puis changez immédiatement le mot de passe dans la section **Mot de passe** (10 caractères minimum). Un gestionnaire de mots de passe est recommandé.
3. Dans **Réglages**, vérifiez le nom affiché, rédigez si besoin un **message d'accueil** (règles du jeu, calendrier) et fixez les dates : un **début** (facultatif : sans date, le portail est ouvert tout de suite) et une **fin** des propositions et des votes.

> Mot de passe perdu : demandez à l'équipe APPC de le réinitialiser (console `/_super`). Vous recevrez un nouveau mot de passe provisoire.

## 2. Donner l'accès : les codes

Personne ne peut entrer sans code. Un code a le format `ABCD-EFGH` (majuscules et chiffres, sans caractères ambigus : pas de O/0 ni I/1).

| Besoin | Réglage à choisir |
|---|---|
| Un groupe de parents (une classe, un conseil d'école) | **Code partagé** : laissez « Nombre d'utilisations » vide |
| Une personne précise | **Code individuel** : nombre d'utilisations = 1 |
| Une ouverture limitée dans le temps | Renseignez une **expiration** (elle n'empêche que les nouvelles entrées) |

Pour distribuer un code :

- Donnez-le directement (message, papier, réunion), ou envoyez le **lien d'invitation** : `https://appc.paris/<établissement>/acces?code=ABCD-EFGH`. La page s'ouvre avec le code prérempli.
- Préférez des étiquettes **collectives** (« Parents CE2 », « Conseil d'école ») : n'écrivez pas de noms de personnes dans les étiquettes.

> **Vote et codes partagés.** Un participant est un navigateur. Avec un code partagé, une personne qui vide ses cookies puis ressaisit le code redevient un nouveau participant et peut voter de nouveau. Pour un scrutin qui engage l'association, remettez des **codes individuels** (1 utilisation) ; pour une simple consultation, un code partagé suffit.

**Si un code a fuité** (transféré hors du groupe voulu) : cliquez sur **Révoquer**. Toutes les personnes entrées avec ce code perdent l'accès immédiatement ; créez un nouveau code pour le groupe légitime. Les propositions et votes déjà déposés restent en place.

## 3. Animer le portail

- **Thèmes** : renommez, recolorez, réordonnez ou ajoutez des thèmes (12 maximum). Un thème qui contient des propositions ne peut pas être supprimé.
- **Propositions maximum par participant** : 5 par défaut, pour éviter qu'une personne ne sature la liste.
- Chaque participant peut voter une fois par proposition (le vote est définitif, sans limite du nombre de propositions soutenues), sauf pour **sa propre** proposition. Il peut modifier ou supprimer **ses propres** propositions tant que le portail est ouvert, et ajouter **une note de contexte** par proposition.
- Une proposition comporte un thème, un **titre** (3 à 120 caractères) et une **description facultative** (400 caractères au plus). Une note de contexte compte 600 caractères au plus.

## 4. Modérer

Connecté en administrateur, vous voyez un bouton **Supprimer** sur chaque proposition et chaque note, quel qu'en soit l'auteur. La suppression est définitive (votes et notes associés compris).

À supprimer sans attendre : toute mention nominative d'un enfant, d'un enseignant ou d'un parent, toute information permettant d'identifier une famille, tout propos injurieux ou diffamatoire. Un rappel est affiché sous le formulaire de proposition.

> L'administrateur peut consulter le portail sans code, mais **ne peut ni proposer ni voter** avec le seul compte administrateur : pour participer, il entre avec un code comme tout le monde.

## 5. Clôturer et publier les résultats

- Avant la **date de début**, les participants peuvent entrer et lire, mais ni proposer ni voter (le bandeau indique la date d'ouverture).
- La clôture est automatique à la **date de fin** réglée, ou immédiate avec **Clôturer les votes maintenant**. Le bandeau affiche « J-N » jusqu'à l'échéance.
- Une fois clos : plus de proposition ni de vote ; les participants voient le classement final. Les **notes de contexte** restent possibles.
- **Rouvrir les votes** est possible à tout moment (si la date de fin est dépassée, elle est effacée).
- **Exporter (CSV)** produit un tableau (idées, thème, votes, dates, notes) lisible avec Excel ou LibreOffice, pour un compte rendu ou une présentation en conseil d'école.

## 6. En fin d'année scolaire

1. Exportez les résultats et conservez-les dans les archives de l'association.
2. Demandez à l'équipe APPC d'archiver ou de supprimer l'instance (voir [SECURITE-RGPD.md](SECURITE-RGPD.md) pour les durées de conservation).
3. Ne réutilisez pas les anciens codes pour la rentrée suivante : créez-en de nouveaux.
