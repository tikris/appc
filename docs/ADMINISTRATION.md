# Guide de l'administrateur d'établissement

Ce guide s'adresse au **représentant des parents** qui anime le portail de son établissement. Aucune compétence technique n'est nécessaire.

## 1. Première connexion

1. L'équipe APPC vous transmet l'adresse de votre espace (ex. `https://appc.paris/ecole-etienne-marcel/admin`) et un **mot de passe provisoire**.
2. Connectez-vous, puis changez immédiatement le mot de passe dans la section **Mot de passe** (10 caractères minimum). Un gestionnaire de mots de passe est recommandé.
3. Votre portail est **déjà prêt** : l'équipe APPC l'a créé à partir d'un modèle (maternelle, élémentaire ou collège). Vous y trouvez :
   - un **message d'accueil** et une **annonce de bienvenue**, à relire ;
   - une **consultation publiée** (« Idées pour l'école ») avec quatre thèmes ;
   - un **sondage** (date de la prochaine réunion) et des **inscriptions** (fête de l'école) **en brouillon** : invisibles des parents tant que vous ne les publiez pas.
4. Dans **Réglages du portail**, vérifiez le nom affiché et le message d'accueil.

> Mot de passe perdu : demandez à l'équipe APPC de le réinitialiser (console `/_super`). Vous recevrez un nouveau mot de passe provisoire.

## 2. Donner l'accès : les codes

Personne ne peut entrer sans code. Un code a le format `ABCD-EFGH` (majuscules et chiffres, sans caractères ambigus : pas de O/0 ni I/1). Un code donne accès à **tout le portail** de l'établissement (toutes les activités).

| Besoin | Réglage à choisir |
|---|---|
| Un groupe de parents (une classe, un conseil d'école) | **Code partagé** : laissez « Nombre d'utilisations » vide |
| Une personne précise | **Code individuel** : nombre d'utilisations = 1 |
| Une ouverture limitée dans le temps | Renseignez une **expiration** (elle n'empêche que les nouvelles entrées) |

Pour distribuer un code :

- Donnez-le directement (message, papier, réunion), ou envoyez le **lien d'invitation** : `https://appc.paris/<établissement>/acces?code=ABCD-EFGH`. La page s'ouvre avec le code prérempli.
- Préférez des étiquettes **collectives** (« Parents CE2 », « Conseil d'école ») : n'écrivez pas de noms de personnes dans les étiquettes.

> **Vote et codes partagés.** Un participant est un navigateur. Avec un code partagé, une personne qui vide ses cookies puis ressaisit le code redevient un nouveau participant et peut voter de nouveau. Pour un scrutin qui engage l'association, remettez des **codes individuels** (1 utilisation) ; pour une simple consultation ou un sondage de date, un code partagé suffit.

**Si un code a fuité** (transféré hors du groupe voulu) : cliquez sur **Révoquer**. Toutes les personnes entrées avec ce code perdent l'accès immédiatement ; créez un nouveau code pour le groupe légitime. Les propositions, votes et inscriptions déjà déposés restent en place.

## 3. Le tableau de bord : annonces et agenda

Les parents arrivent sur un tableau de bord qui affiche, dans l'ordre : le message d'accueil, l'**agenda** (« À venir »), les **activités** en cours et les **annonces**.

- **Publier une annonce** (section *Annonces et agenda*) : un titre, un texte facultatif. Si vous indiquez une **date d'événement** (et un lieu), l'annonce figure aussi à l'agenda. Cochez **Épingler** pour la garder en tête.
- L'agenda ajoute **tout seul** les dates d'ouverture et de clôture de vos activités (« Clôture : Fête de l'école »).
- Une annonce se modifie ou se supprime à tout moment.

## 4. Les activités

Une activité est une **consultation**, un **sondage** ou des **inscriptions**. Pour en créer une : section *Activités*, choisissez le type, donnez un titre, puis **Créer**. Vous arrivez sur sa page de gestion.

**Cycle de vie d'une activité :**

1. **Brouillon** : invisible des parents. Complétez-la (thèmes, réponses ou créneaux), réglez les dates. Le bouton **Aperçu** montre la page telle que les parents la verront.
2. **Publier** : l'activité apparaît sur le tableau de bord. Une activité vide ne peut pas être publiée (il faut au moins un thème, deux réponses ou un créneau).
3. **Ouverture et clôture** : sans date d'ouverture, elle est ouverte dès la publication. Avant l'ouverture, les parents voient l'activité mais ne peuvent pas encore participer. La clôture est automatique à la date de fin, ou immédiate avec **Clôturer maintenant** ; **Rouvrir** est possible à tout moment.
4. **Repasser en brouillon** la masque sans rien effacer. **Supprimer l'activité** efface définitivement tout son contenu : exportez avant.

### Consultation

- Chaque participant propose des idées (5 au plus par défaut, réglable de 1 à 50), vote une fois par idée (vote définitif, jamais pour sa propre idée) et ajoute une **note de contexte** par idée.
- **Thèmes** : renommez, recolorez, réordonnez ou ajoutez des thèmes (12 maximum). Un thème qui contient des propositions ne peut pas être supprimé. Chaque consultation a ses propres thèmes.
- Une proposition comporte un thème, un **titre** (3 à 120 caractères) et une **description facultative** (400 caractères au plus). Une note de contexte compte 600 caractères au plus.
- Une fois close, plus de proposition ni de vote ; les parents voient le classement final. Les notes de contexte restent possibles.

### Sondage

- **Réponses proposées** : ajoutez-en au moins deux. Elles sont **verrouillées dès la première réponse** d'un parent (les modifier fausserait le résultat) : relisez-les avant de publier.
- **Plusieurs réponses possibles** : à cocher pour un choix de dates (chacun coche toutes les dates qui lui conviennent).
- **Résultats** : visibles de tous pendant le sondage, seulement après avoir répondu (par défaut, limite l'effet de suivisme), ou seulement après la clôture.
- Chaque parent peut **modifier sa réponse** jusqu'à la clôture.

### Inscriptions bénévoles

- **Créneaux** : un libellé (« Buvette 14 h – 16 h »), une date facultative et un nombre de places. On ne peut pas réduire les places en dessous du nombre d'inscrits.
- Le parent s'inscrit avec un **prénom ou un pseudo**. Par défaut, **vous seul** voyez les prénoms ; les autres parents voient le nombre de places prises. Cochez *Afficher les prénoms des inscrits aux autres participants* si les bénévoles veulent savoir avec qui ils seront.
- Une inscription reçue **hors du portail** (papier, téléphone) s'ajoute avec le champ *Inscrire* sous le créneau ; **retirer** supprime une inscription.
- Le parent peut annuler lui-même son inscription tant que les inscriptions sont ouvertes.
- L'**export CSV** donne la liste par créneau, places libres comprises : pratique à imprimer le jour J.

## 5. Modérer

Connecté en administrateur, vous voyez un bouton **Supprimer** sur chaque proposition et chaque note de contexte, quel qu'en soit l'auteur. La suppression est définitive (votes et notes associés compris).

À supprimer sans attendre : toute mention nominative d'un enfant, d'un enseignant ou d'un parent, toute information permettant d'identifier une famille, tout propos injurieux ou diffamatoire. Un rappel est affiché sous le formulaire de proposition.

> L'administrateur peut consulter le portail sans code, mais **ne peut ni proposer, ni voter, ni répondre, ni s'inscrire** avec le seul compte administrateur : pour participer, il entre avec un code comme tout le monde.

## 6. Exporter les résultats

Chaque activité a son bouton **Exporter (CSV)**, lisible avec Excel ou LibreOffice : idées, thèmes, votes et notes pour une consultation ; nombre de réponses par choix pour un sondage ; inscrits par créneau. Utile pour un compte rendu ou une présentation en conseil d'école.

## 7. En fin d'année scolaire

1. Exportez les résultats et conservez-les dans les archives de l'association.
2. **Supprimez les inscriptions bénévoles** une fois l'événement passé : ce sont les seules données nominatives (prénoms) du portail.
3. Demandez à l'équipe APPC d'archiver ou de supprimer l'instance (voir [SECURITE-RGPD.md](SECURITE-RGPD.md) pour les durées de conservation).
4. Ne réutilisez pas les anciens codes pour la rentrée suivante : créez-en de nouveaux.
