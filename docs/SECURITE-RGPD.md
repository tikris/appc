# Sécurité et protection des données

> Ce document décrit l'état technique du projet. Il ne constitue pas un avis juridique : la mise en conformité RGPD (mentions, registre, base légale) relève du responsable de traitement, c'est-à-dire de l'association APPC.

## Données traitées

| Donnée | Où | Finalité | Personnelle ? |
|---|---|---|---|
| Idées, descriptions, notes de contexte | `ideas`, `notes` | Délibération | Seulement si un participant y saisit des informations personnelles (risque principal) |
| Votes | `votes` | Classement | Non rattachés à une identité (jeton anonyme) |
| Jeton de participant (haché SHA-256), date d'entrée, dernière activité | `participants` | Maintenir l'accès et le droit de modifier ses propositions | Identifiant pseudonyme du navigateur |
| Étiquette d'un code (ex. « Parents CE2 ») | `codes` | Savoir à qui un code a été remis | Non, si l'administrateur n'y met pas de noms |
| IP hachée (HMAC), horodatage des échecs | `attempts` | Anti-force-brute | Oui (pseudonymisée) ; purgée automatiquement après 24 h (à la tentative de connexion suivante) |
| Hash du mot de passe administrateur | `settings` | Authentification | Non |

**Non collectés** : nom, e-mail, téléphone, compte, adresse IP en clair, traceurs tiers, statistiques de fréquentation. Aucune ressource externe n'est chargée (pas de CDN, polices ou scripts tiers).

**Cookies** : uniquement des cookies strictement nécessaires au fonctionnement (accès participant, session administrateur, jeton anti-CSRF). Ils ne requièrent pas de bandeau de consentement, mais doivent être mentionnés dans l'information donnée aux utilisateurs (le pied de page l'indique).

## Mesures de sécurité en place

| Risque | Mesure |
|---|---|
| Accès non autorisé | Code obligatoire ; codes à 40 bits ; révocation immédiate ; expiration ; usages limités |
| Force brute (codes, mots de passe) | 8 échecs / 15 min / IP, message d'erreur identique pour code inconnu, expiré, révoqué ou épuisé |
| Vol de session | Jetons de 256 bits, stockés **hachés** ; cookies `HttpOnly`, `SameSite=Lax`, `Secure` |
| CSRF | Cookie + champ caché comparés (double soumission) sur tous les POST, en plus de `SameSite=Lax` |
| XSS | Échappement systématique (`h()`), aucun script ni style en ligne, CSP `default-src 'none'; script-src 'self'; style-src 'self'` |
| Injection SQL | Requêtes préparées exclusivement |
| Injection de formules (export CSV) | Préfixe `'` sur les cellules commençant par `= + - @` |
| Clickjacking / sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff` |
| Indexation | `X-Robots-Tag: noindex`, `robots.txt` |
| Fuite de fichiers | Bases dans `data/` (`.htaccess` « Require all denied », idéalement hors docroot), config ignorée par git, réécriture vers `public/` |
| Cloisonnement des établissements | Une base par instance ; cookies limités au chemin de l'instance ; identifiants validés par expression régulière |
| Mots de passe | `password_hash` (bcrypt/argon2 selon PHP), 10 caractères minimum, mot de passe provisoire à changer |
| Cache | `Cache-Control: no-store` sur toutes les pages |

## Points de vigilance (à traiter)

1. **Contenu saisi** : le risque réel n'est pas technique mais éditorial — mention d'enfants, d'enseignants ou de familles, propos diffamatoires. Le portail affiche un rappel et l'administrateur peut supprimer, mais **il n'y a pas de pré-modération ni de signalement** par les participants. À prévoir : charte d'usage, procédure de retrait.
2. **Mentions d'information** : le pied de page résume l'essentiel ; une page de politique de confidentialité complète (responsable de traitement, finalités, durée, droits, contact) reste à rédiger.
3. **Durées de conservation** : aucune purge automatique. Recommandation : supprimer ou anonymiser chaque instance au plus tard à la fin de l'année scolaire suivant son usage, après export des résultats.
4. **Sauvegardes** : elles contiennent les mêmes données que la base ; mêmes durées de conservation, dossier hors web, droits `0600`.
5. **Codes lisibles en base** (voir [ARCHITECTURE.md](ARCHITECTURE.md)) : acceptable pour ce niveau d'enjeu, à réévaluer si le portail héberge des sujets sensibles.
6. **Réseau partagé** : sur un Wi-Fi d'établissement, toutes les IP peuvent apparaître identiques ; la limitation d'essais est alors commune (inconvénient mineur).
7. **Cookie et appareil partagé** : « Oublier cet appareil » (pied de page) supprime l'accès sur un poste commun.
8. **Intégrité du scrutin** : un participant est un navigateur. Avec un code partagé, une personne qui efface ses cookies et ressaisit le code redevient un nouveau participant et peut voter de nouveau ; le portail ne l'empêche pas (il ne collecte ni adresse IP ni identité). Pour un scrutin qui engage, utiliser des codes individuels. Le portail d'origine, qui identifiait le votant par son adresse IP, avait le défaut inverse : plusieurs personnes derrière une même adresse ne comptaient que pour une.
9. **Hébergeur** : vérifier les journaux d'accès conservés par l'hébergeur (adresses IP) et sa localisation des données.

## Signaler une faille

Ouvrir un ticket **sans détail exploitable** dans le dépôt, ou contacter directement le responsable du projet. Ne jamais publier de secret (`app_secret`, hash, base) dans un ticket ou un commit : en cas de fuite, remplacer `app_secret` (ce qui invalide la session super-admin) et réinitialiser les mots de passe administrateurs.
