#!/usr/bin/env bash
# Test de bout en bout : démarre le serveur PHP intégré sur une base temporaire et rejoue les parcours
# clés (accès par code, tableau de bord, consultations, sondages, inscriptions, annonces, administration,
# isolation entre instances, console super-admin, migration d'une base de la première version).
#
# Usage : bash tests/smoke.sh        (nécessite php, curl et grep)
set -u

ROOT=$(cd "$(dirname "$0")/.." && pwd)
TMP=$(mktemp -d)
PORT=${PORT:-8099}
B="http://127.0.0.1:$PORT"
export APPC_CONFIG="$TMP/config.php"
SUPER_PW="superpass-123456"
PASS=0
FAIL=0

cleanup() { [ -n "${SERVER_PID:-}" ] && kill "$SERVER_PID" 2>/dev/null; rm -rf "$TMP"; }
trap cleanup EXIT

php -r '
$c = ["app_secret" => bin2hex(random_bytes(32)), "data_dir" => $argv[1], "super_password_hash" => password_hash($argv[3], PASSWORD_DEFAULT)];
file_put_contents($argv[2], "<?php return " . var_export($c, true) . ";");
' "$TMP/data" "$APPC_CONFIG" "$SUPER_PW"

ADMIN_PW=$(php "$ROOT/bin/create-instance.php" master "Portail maître" | sed -n 's/^Mot de passe provisoire.*: //p')
[ -n "$ADMIN_PW" ] || { echo "Création de l'instance impossible"; exit 1; }

php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/index.php" >"$TMP/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "$B/" && break; sleep 0.1; done

# ── Outils ──────────────────────────────────────────────────────────────────
req() { # jar url [options curl] → CODE, $TMP/body, $TMP/hdr
  local jar=$1 url=$2; shift 2
  CODE=$(curl -s -o "$TMP/body" -D "$TMP/hdr" -w '%{http_code}' -c "$jar" -b "$jar" "$url" "$@")
}
token() { curl -s -c "$1" -b "$1" "$2" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
post() { # jar page-pour-le-jeton url [--data-urlencode ...]
  local jar=$1 page=$2 url=$3; shift 3
  local tok; tok=$(token "$jar" "$page")
  req "$jar" "$url" --data-urlencode "_csrf=$tok" "$@"
}
check() { # nom attendu obtenu
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  ÉCHEC  $1 : attendu « $2 », obtenu « $3 »"; fi
}
body_has() { if grep -qF -- "$2" "$TMP/body"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  ÉCHEC  $1 : « $2 » absent de la page"; fi; }
body_lacks() { if grep -qF -- "$2" "$TMP/body"; then FAIL=$((FAIL+1)); echo "  ÉCHEC  $1 : « $2 » présent dans la page"; else PASS=$((PASS+1)); fi; }
location() { grep -i '^location:' "$TMP/hdr" | tr -d '\r' | sed 's/^[Ll]ocation: //'; }
newest_code() { grep -o '<code>[A-Z2-9]\{4\}-[A-Z2-9]\{4\}</code>' "$TMP/body" | head -1 | sed 's/<[^>]*>//g'; }
aid() { grep -o "admin/activite/[0-9]*\">$1" "$TMP/body" | head -1 | sed 's|admin/activite/||;s|".*||'; } # identifiant d'activité (page admin)
sql() { php -r '$d = new PDO("sqlite:" . $argv[1]); echo implode(",", $d->query($argv[2])->fetchAll(PDO::FETCH_COLUMN));' "$1" "$2"; }
nonempty() { if [ -n "$2" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  ÉCHEC  $1 : valeur vide"; fi; }

A="$TMP/admin.jar"; P1="$TMP/p1.jar"; P2="$TMP/p2.jar"; P3="$TMP/p3.jar"; P4="$TMP/p4.jar"; X="$TMP/x.jar"; S="$TMP/super.jar"

D="$TMP/data"

echo "── Routage et accès ──"
req "$X" "$B/";                         check "accueil" 200 "$CODE"; body_has "accueil" "Parents à Paris Centre"
req "$X" "$B/master";                   check "slash final" 301 "$CODE"; check "slash final (cible)" "/master/" "$(location)"
req "$X" "$B/master/";                  check "portail sans code" 303 "$CODE"; check "portail sans code (cible)" "/master/acces" "$(location)"
req "$X" "$B/master/activite/1";        check "activité sans code" 303 "$CODE"
req "$X" "$B/inconnu/";                 check "instance inconnue" 404 "$CODE"
req "$X" "$B/Bad_Slug/";                check "slug invalide" 404 "$CODE"
req "$X" "$B/../../etc/passwd" --path-as-is; check "traversée de chemin" 404 "$CODE"
req "$X" "$B/master/acces";             check "page d'accès" 200 "$CODE"; body_has "en-tête CSP" "Accès au portail"
grep -qi "content-security-policy: default-src 'none'" "$TMP/hdr" && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "  ÉCHEC  en-tête CSP absent"; }

echo "── CSRF ──"
req "$X" "$B/master/acces" --data-urlencode "code=ABCD-EFGH"; check "POST sans jeton CSRF" 403 "$CODE"

echo "── Administration : connexion, modèle, codes ──"
req "$A" "$B/master/admin";             check "login admin (formulaire)" 200 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=login" --data-urlencode "password=mauvais"
check "mauvais mot de passe admin" 401 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=login" --data-urlencode "password=$ADMIN_PW"
check "bon mot de passe admin" 303 "$CODE"
req "$A" "$B/master/admin";             check "panneau admin" 200 "$CODE"; body_has "panneau admin" "Codes d'accès"; body_has "mot de passe provisoire" "provisoire"
body_has "modèle : consultation" "Idées pour l&#039;école"; body_has "modèle : sondage" "Date de la prochaine réunion"; body_has "modèle : inscriptions" "Fête de l&#039;école"
CONS=$(aid "Idées pour l"); POLL=$(aid "Date de la prochaine"); SIGN=$(aid "Fête de l")
nonempty "identifiant de la consultation" "$CONS"; nonempty "identifiant du sondage" "$POLL"; nonempty "identifiant des inscriptions" "$SIGN"
C="$B/master/activite/$CONS"; CA="$B/master/admin/activite/$CONS"
PO="$B/master/activite/$POLL"; POA="$B/master/admin/activite/$POLL"
SG="$B/master/activite/$SIGN"; SGA="$B/master/admin/activite/$SIGN"

post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=Parents CE2" --data-urlencode "max_uses=" --data-urlencode "expires_at="
check "création code partagé" 303 "$CODE"
req "$A" "$B/master/admin"; SHARED=$(newest_code)
nonempty "code partagé" "$SHARED"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=Individuel" --data-urlencode "max_uses=1" --data-urlencode "expires_at="
req "$A" "$B/master/admin"; SINGLE=$(newest_code)
check "codes distincts" "1" "$([ "$SHARED" != "$SINGLE" ] && echo 1 || echo 0)"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=x" --data-urlencode "max_uses=abc" --data-urlencode "expires_at="
check "max_uses invalide" 422 "$CODE"

echo "── Participants et tableau de bord ──"
post "$P1" "$B/master/acces" "$B/master/acces" --data-urlencode "code=ZZZZ-ZZZZ"
check "code inconnu" 401 "$CODE"; body_has "message code" "Code invalide"
post "$P1" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$(echo "$SHARED" | tr 'A-Z' 'a-z' | tr -d '-')"
check "code valide (saisie minuscule sans tiret)" 303 "$CODE"
req "$P1" "$B/master/";                 check "tableau de bord avec code" 200 "$CODE"
body_has "consultation publiée visible" "Idées pour l&#039;école"; body_lacks "brouillon de sondage invisible" "Date de la prochaine réunion"
body_has "annonce de bienvenue" "Bienvenue sur le portail des parents"; body_has "message d'accueil" "espace des parents"
req "$P1" "$PO";                        check "brouillon inaccessible au participant" 404 "$CODE"
req "$P1" "$C";                         check "page de la consultation" 200 "$CODE"; body_has "formulaire d'idée" "Proposer une idée"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea"; check "POST sur le tableau de bord" 400 "$CODE"

post "$P2" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SHARED"; check "2e participant (code partagé)" 303 "$CODE"
post "$P3" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"; check "code individuel, 1re utilisation" 303 "$CODE"
post "$P4" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"; check "code individuel, 2e utilisation" 401 "$CODE"

echo "── Consultation : idées, votes, notes ──"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=4" --data-urlencode "title=Liste des adultes autorisés" --data-urlencode "description=Une liste à la sortie de l'école."
check "ajout d'idée" 303 "$CODE"; IDEA=$(location | sed -n 's/.*#idea-//p')
nonempty "identifiant d'idée" "$IDEA"; IDEA=${IDEA:-1}
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=99" --data-urlencode "title=t" --data-urlencode "description=d"
check "thème invalide" 422 "$CODE"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=ab" --data-urlencode "description=x"
check "titre trop court (< 3)" 422 "$CODE"; body_has "message titre" "entre 3 et 120"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=$(printf 'a%.0s' $(seq 1 121))" --data-urlencode "description=x"
check "titre trop long (> 120)" 422 "$CODE"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Titre valide" --data-urlencode "description=$(printf 'a%.0s' $(seq 1 401))"
check "description trop longue (> 400)" 422 "$CODE"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Sans description" --data-urlencode "description="
check "description facultative" 303 "$CODE"
post "$P1" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=<script>alert(1)</script>" --data-urlencode "description=x"
req "$P1" "$C"; body_lacks "échappement XSS" "<script>alert(1)"; body_has "échappement XSS" "&lt;script&gt;alert(1)"
post "$P2" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title==HYPERLINK(1)" --data-urlencode "description=injection tableur"

post "$P1" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "vote pour sa propre idée refusé" 403 "$CODE"
req "$P1" "$C"; body_has "pas de bouton de vote sur sa propre idée" 'ma<br>prop.'
post "$P2" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "vote" 303 "$CODE"
post "$P2" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "double vote (idempotent)" 303 "$CODE"
post "$P3" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
req "$P2" "$C"; body_has "2 votes (1 par participant)" '<span class="vote-count">2</span>'
post "$P2" "$C" "$C" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=Voir le code de la sécurité intérieure."
check "note de contexte" 303 "$CODE"
req "$P1" "$C"; body_has "note affichée" "Voir le code de la sécurité intérieure."
post "$P2" "$C" "$C" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=$(printf 'x%.0s' $(seq 1 601))"
check "note trop longue" 422 "$CODE"
req "$P1" "$B/master/"; body_has "tableau de bord : compteur d'idées" "4 propositions · 2 votes"

echo "── Plusieurs consultations ──"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_activity" --data-urlencode "kind=consultation" --data-urlencode "title=Cantine"
check "création d'une 2e consultation" 303 "$CODE"; CONS2=$(location | sed -n 's|.*/admin/activite/\([0-9]*\).*|\1|p')
nonempty "identifiant de la 2e consultation" "$CONS2"; C2="$B/master/activite/$CONS2"; C2A="$B/master/admin/activite/$CONS2"
post "$A" "$C2A" "$C2A" --data-urlencode "action=publish"; check "publication sans thème refusée" 422 "$CODE"; body_has "message publication" "au moins un thème"
post "$A" "$C2A" "$C2A" --data-urlencode "action=add_theme" --data-urlencode "name=Menus" --data-urlencode "color=3"; check "thème ajouté" 303 "$CODE"
post "$A" "$C2A" "$C2A" --data-urlencode "action=publish"; check "publication" 303 "$CODE"
req "$P1" "$C2"; check "2e consultation visible" 200 "$CODE"; body_has "thème propre à la consultation" "Menus"; body_lacks "thèmes non partagés" "Rythmes scolaires"
body_lacks "idées non partagées" "Liste des adultes autorisés"
post "$P1" "$C2" "$C2" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Thème d'une autre consultation" --data-urlencode "description="
check "thème d'une autre consultation refusé" 422 "$CODE"
post "$P2" "$C2" "$C2" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "voter dans la mauvaise consultation" 404 "$CODE"
post "$A" "$C2A" "$C2A" --data-urlencode "action=update_theme" --data-urlencode "theme_id=1" --data-urlencode "name=Piraté" --data-urlencode "color=1" --data-urlencode "position=1"
check "modifier le thème d'une autre consultation" 422 "$CODE"

echo "── Droits ──"
post "$P2" "$C" "$C" --data-urlencode "action=edit_idea" --data-urlencode "idea_id=$IDEA" --data-urlencode "theme_id=1" --data-urlencode "title=piraté" --data-urlencode "description=x"
check "modifier l'idée d'autrui" 403 "$CODE"
post "$P2" "$C" "$C" --data-urlencode "action=delete_idea" --data-urlencode "idea_id=$IDEA"
check "supprimer l'idée d'autrui" 403 "$CODE"
post "$P1" "$C" "$C" --data-urlencode "action=edit_idea" --data-urlencode "idea_id=$IDEA" --data-urlencode "theme_id=4" --data-urlencode "title=Liste des adultes autorisés (v2)" --data-urlencode "description=Une liste à la sortie."
check "modifier sa propre idée" 303 "$CODE"
req "$A" "$C";                          check "admin sans code : consultation" 200 "$CODE"; body_has "admin : bouton supprimer" "Supprimer"
post "$A" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "admin sans code ne vote pas" 403 "$CODE"
req "$P1" "$CA";                        check "gestion d'activité réservée à l'admin" 303 "$CODE"
post "$P1" "$C" "$B/master/admin/activite/$CONS" --data-urlencode "action=close_now"; check "clôture par un participant" 303 "$CODE"
req "$P1" "$C"; body_lacks "clôture par un participant sans effet" "La période de vote est terminée"

echo "── Export ──"
req "$A" "$CA/export.csv";              check "export CSV" 200 "$CODE"; body_has "export : idée" "Liste des adultes autorisés (v2)"; body_has "export : anti-injection de formule" "'=HYPERLINK(1)"
req "$A" "$C2A/export.csv";             body_lacks "export limité à l'activité" "Liste des adultes autorisés"
req "$P1" "$CA/export.csv";             check "export interdit aux participants" 403 "$CODE"

echo "── Calendrier d'ouverture ──"
SET=(--data-urlencode action=save_activity --data-urlencode "title=Idées pour l'école" --data-urlencode "description=" --data-urlencode max_ideas=5)
FUTURE=$(php -r 'echo date("Y-m-d\\TH:i", time() + 10 * 86400);')
FAR=$(php -r 'echo date("Y-m-d\\TH:i", time() + 40 * 86400);')
post "$A" "$CA" "$CA" "${SET[@]}" --data-urlencode "opens_at=$FUTURE" --data-urlencode "closes_at=$FAR"
check "début dans le futur enregistré" 303 "$CODE"
req "$P2" "$C"; body_has "bandeau avant ouverture" "n&#039;a pas encore commencé"
req "$P2" "$B/master/"; body_has "agenda : ouverture" "Ouverture : Idées pour l"
post "$P2" "$C" "$C" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Avant ouverture" --data-urlencode "description="
check "dépôt refusé avant l'ouverture" 403 "$CODE"; body_has "message avant ouverture" "pas encore commencé"
post "$P2" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "vote refusé avant l'ouverture" 403 "$CODE"
post "$A" "$CA" "$CA" "${SET[@]}" --data-urlencode "opens_at=$FAR" --data-urlencode "closes_at=$FUTURE"
check "fin antérieure au début refusée" 422 "$CODE"
post "$A" "$CA" "$CA" "${SET[@]}" --data-urlencode "opens_at=" --data-urlencode "closes_at=$FUTURE"
check "fenêtre ouverte avec échéance" 303 "$CODE"
req "$P2" "$C"; body_has "compte à rebours" "J-"; body_has "formulaire visible" "Proposer une idée"
req "$P2" "$B/master/"; body_has "agenda : clôture" "Clôture : Idées pour l"
post "$A" "$CA" "$CA" "${SET[@]}" --data-urlencode "opens_at=" --data-urlencode "closes_at="
check "retour à la fenêtre ouverte sans échéance" 303 "$CODE"

echo "── Sondage ──"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_activity" --data-urlencode "kind=poll" --data-urlencode "title=Sondage vide"
EMPTY=$(location | sed -n 's|.*/admin/activite/\([0-9]*\).*|\1|p')
post "$A" "$B/master/admin/activite/$EMPTY" "$B/master/admin/activite/$EMPTY" --data-urlencode "action=publish"
check "sondage sans réponses non publiable" 422 "$CODE"
post "$A" "$POA" "$POA" --data-urlencode "action=save_activity" --data-urlencode "title=Date de la prochaine réunion des parents" --data-urlencode "description=" --data-urlencode "opens_at=" --data-urlencode "closes_at=" --data-urlencode "results=voted"
check "sondage : une seule réponse, résultats après réponse" 303 "$CODE"
post "$A" "$POA" "$POA" --data-urlencode "action=publish"; check "publication du sondage" 303 "$CODE"
req "$P1" "$B/master/"; body_has "sondage publié au tableau de bord" "Date de la prochaine réunion"
req "$P1" "$PO"; check "page du sondage" 200 "$CODE"; body_has "bouton répondre" ">Répondre<"; body_lacks "résultats masqués avant réponse" "<progress"
OPTS=($(grep -o 'name="options\[\]" value="[0-9]*"' "$TMP/body" | sed 's/.*value="//;s/"//'))
check "3 réponses proposées" 3 "${#OPTS[@]}"
post "$P1" "$PO" "$PO" --data-urlencode "action=answer"; check "réponse vide refusée" 422 "$CODE"
post "$P1" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[0]}" --data-urlencode "options[]=${OPTS[1]}"
check "deux réponses refusées (choix unique)" 422 "$CODE"
post "$P1" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=999999"; check "réponse inconnue refusée" 422 "$CODE"
post "$P1" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[0]}"; check "réponse" 303 "$CODE"
req "$P1" "$PO"; body_has "résultats visibles après réponse" "<progress"; body_has "réponse modifiable" "Modifier ma réponse"
post "$P2" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[0]}"
post "$P1" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[2]}"; check "réponse modifiée" 303 "$CODE"
check "une réponse par participant" "1,1" "$(sql "$D/master.sqlite" "SELECT COUNT(*) FROM poll_answers WHERE option_id IN (${OPTS[0]}, ${OPTS[2]}) GROUP BY option_id ORDER BY option_id")"
req "$P1" "$B/master/"; body_has "badge « répondu »" "Vous avez répondu"
post "$A" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[0]}"; check "admin sans code ne répond pas" 403 "$CODE"
post "$A" "$POA" "$POA" --data-urlencode "action=add_option" --data-urlencode "label=Dimanche"; check "réponses verrouillées après la 1re réponse" 422 "$CODE"
post "$A" "$POA" "$POA" --data-urlencode "action=save_activity" --data-urlencode "title=Date de la prochaine réunion des parents" --data-urlencode "description=" --data-urlencode "opens_at=" --data-urlencode "closes_at=" --data-urlencode "results=voted" --data-urlencode "multiple=1"
check "passage au choix multiple refusé après réponses" 422 "$CODE"
req "$A" "$POA/export.csv"; check "export du sondage" 200 "$CODE"; body_has "export : réponse" '"Mardi à 18 h 30";1;"50 %"'
post "$A" "$POA" "$POA" --data-urlencode "action=close_now"; check "clôture du sondage" 303 "$CODE"
post "$P3" "$PO" "$PO" --data-urlencode "action=answer" --data-urlencode "options[]=${OPTS[0]}"; check "réponse après clôture" 403 "$CODE"
req "$P3" "$PO"; body_has "résultats visibles à la clôture" "<progress"; body_has "bandeau sondage terminé" "Le sondage est terminé"

echo "── Inscriptions bénévoles ──"
post "$A" "$SGA" "$SGA" --data-urlencode "action=publish"; check "publication des inscriptions" 303 "$CODE"
req "$P1" "$SG"; check "page des inscriptions" 200 "$CODE"
SLOTS=($(grep -o 'name="slot_id" value="[0-9]*"' "$TMP/body" | sed 's/.*value="//;s/"//'))
check "5 créneaux du modèle" 5 "${#SLOTS[@]}"
BUVETTE=${SLOTS[1]}
post "$P1" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name="; check "prénom obligatoire" 422 "$CODE"
post "$P1" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name=Camille"; check "inscription" 303 "$CODE"
post "$P1" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name=Camille"; check "double inscription" 409 "$CODE"
req "$P1" "$SG"; body_has "inscription affichée" "Vous êtes inscrit(e)"; body_has "prénom prérempli" 'value="Camille"'
post "$P2" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name=Dominique"
req "$P2" "$SG"; body_lacks "prénoms masqués par défaut" "Camille"; body_has "places prises" "2 / 3"
req "$A" "$SG"; body_has "l'admin voit les prénoms" "Camille, Dominique"
req "$A" "$SGA"; body_has "gestion : inscrits" "Dominique"
post "$A" "$SGA" "$SGA" --data-urlencode "action=add_signup" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name=Inscrit par téléphone"; check "inscription par l'admin" 303 "$CODE"
post "$P3" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "name=Alex"; check "créneau complet" 409 "$CODE"; body_has "message complet" "complet"
post "$A" "$SGA" "$SGA" --data-urlencode "action=update_slot" --data-urlencode "slot_id=$BUVETTE" --data-urlencode "label=Buvette" --data-urlencode "starts_at=" --data-urlencode "capacity=2" --data-urlencode "position=2"
check "capacité inférieure aux inscrits refusée" 422 "$CODE"
post "$A" "$SGA" "$SGA" --data-urlencode "action=save_activity" --data-urlencode "title=Fête de l'école" --data-urlencode "description=" --data-urlencode "opens_at=" --data-urlencode "closes_at=" --data-urlencode "show_names=1"
req "$P2" "$SG"; body_has "prénoms visibles si l'admin l'a choisi" "Camille"
check "prénom sans lien avec le participant" "0" "$(sql "$D/master.sqlite" "SELECT COUNT(*) FROM signups g JOIN participants p ON p.token_hash = g.owner_hash")"
check "pas de colonne participant dans signups" "0" "$(sql "$D/master.sqlite" "SELECT COUNT(*) FROM pragma_table_info('signups') WHERE name = 'participant_id'")"
req "$A" "$SGA/export.csv"; body_has "export : inscrit" "Buvette;;Camille"; body_has "export : place libre" "(place libre)"
post "$P1" "$SG" "$SG" --data-urlencode "action=leave" --data-urlencode "slot_id=$BUVETTE"; check "désinscription" 303 "$CODE"
req "$P1" "$SG"; body_lacks "désinscription effective" "Vous êtes inscrit(e)"
post "$P2" "$SG" "$SG" --data-urlencode "action=leave" --data-urlencode "slot_id=999999"; check "créneau inconnu" 404 "$CODE"
SID=$(sql "$D/master.sqlite" "SELECT id FROM signups WHERE name = 'Dominique'")
post "$A" "$SGA" "$SGA" --data-urlencode "action=remove_signup" --data-urlencode "signup_id=$SID"; check "retrait par l'admin" 303 "$CODE"
post "$A" "$SGA" "$SGA" --data-urlencode "action=close_now"
post "$P3" "$SG" "$SG" --data-urlencode "action=join" --data-urlencode "slot_id=${SLOTS[0]}" --data-urlencode "name=Alex"; check "inscription après clôture" 403 "$CODE"

echo "── Annonces et agenda ──"
SOON=$(php -r 'echo date("Y-m-d\\TH:i", time() + 3 * 86400);')
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=save_post" --data-urlencode "post_id=0" --data-urlencode "title=Conseil d'école <b>" --data-urlencode "body=Ordre du jour à venir." --data-urlencode "event_at=$SOON" --data-urlencode "location=Préau"
check "annonce datée" 303 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=save_post" --data-urlencode "post_id=0" --data-urlencode "title=" --data-urlencode "body=" --data-urlencode "event_at=" --data-urlencode "location="
check "annonce sans titre refusée" 422 "$CODE"
req "$P1" "$B/master/"; body_has "agenda : événement" "Conseil d&#039;école &lt;b&gt;"; body_has "agenda : lieu" "Préau"; body_lacks "annonce échappée" "<b></a>"
POST=$(sql "$D/master.sqlite" "SELECT id FROM posts WHERE location = 'Préau'")
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=save_post" --data-urlencode "post_id=$POST" --data-urlencode "title=Conseil d'école (modifié)" --data-urlencode "body=" --data-urlencode "event_at=" --data-urlencode "location=" --data-urlencode "pinned=1"
check "annonce modifiée" 303 "$CODE"
req "$P1" "$B/master/"; body_has "annonce modifiée visible" "Conseil d&#039;école (modifié)"; body_lacks "sans date : hors agenda" "Préau"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=delete_post" --data-urlencode "post_id=$POST"; check "annonce supprimée" 303 "$CODE"
req "$P1" "$B/master/"; body_lacks "annonce supprimée invisible" "Conseil d&#039;école"

echo "── Isolation entre instances, modèles ──"
req "$S" "$B/_super";                   check "console super-admin (login)" 200 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "password=$SUPER_PW"; check "connexion super-admin" 303 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=ecole-etienne-marcel" --data-urlencode "name=École Étienne Marcel" --data-urlencode "template=maternelle"
check "création d'instance" 200 "$CODE"; body_has "mot de passe affiché une fois" "Mot de passe provisoire"
check "modèle maternelle appliqué" "Accueil et adaptation" "$(sql "$D/ecole-etienne-marcel.sqlite" "SELECT name FROM themes ORDER BY position LIMIT 1")"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=college-test" --data-urlencode "name=Collège" --data-urlencode "template=college"
check "modèle collège appliqué" "Forum des métiers" "$(sql "$D/college-test.sqlite" "SELECT title FROM activities WHERE kind = 'signup'")"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=vierge" --data-urlencode "name=Vierge" --data-urlencode "template=vide"
check "modèle vide : aucune activité" "0" "$(sql "$D/vierge.sqlite" "SELECT COUNT(*) FROM activities")"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=autre" --data-urlencode "name=x" --data-urlencode "template=inconnu"
check "modèle inconnu" 422 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=ecole-etienne-marcel" --data-urlencode "name=Doublon"
check "instance en double" 422 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=Mauvais Slug" --data-urlencode "name=x"
check "slug invalide à la création" 422 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=admin" --data-urlencode "name=réservé"
check "slug réservé" 422 "$CODE"
req "$S" "$B/_super"; body_has "console : liste des instances" "/college-test/"
req "$P1" "$B/ecole-etienne-marcel/";   check "cookie master inutilisable ailleurs" 303 "$CODE"
post "$X" "$B/ecole-etienne-marcel/acces" "$B/ecole-etienne-marcel/acces" --data-urlencode "code=$SHARED"
check "code master refusé sur une autre instance" 401 "$CODE"
req "$X" "$B/_super";                   check "console super-admin sans session" 200 "$CODE"; body_has "login requis" "Mot de passe"

echo "── Migration d'une base de la première version ──"
OLD_PW="ancien-mot-de-passe"
php -r '
$db = new PDO("sqlite:" . $argv[1]);
$db->exec(file_get_contents($argv[2]));
$db->exec("PRAGMA user_version = 1");
$t = time();
$db->prepare("INSERT INTO settings VALUES (\"name\", \"Ancienne école\"), (\"admin_hash\", ?), (\"must_change_pw\", \"0\"), (\"created_at\", \"1700000000\"), (\"intro\", \"Bonjour\"), (\"max_ideas\", \"7\"), (\"opens_at\", \"\"), (\"closes_at\", ?), (\"closed_at\", \"\")")
   ->execute([password_hash($argv[3], PASSWORD_DEFAULT), (string) ($t + 5 * 86400)]);
$db->exec("INSERT INTO themes (name, color, position) VALUES (\"Cantine\", 1, 1), (\"Sécurité\", 2, 2)");
$db->exec("INSERT INTO codes (code, label, created_at) VALUES (\"ABCD-EFGH\", \"Tous\", $t)");
$db->exec("INSERT INTO participants (token_hash, code_id, created_at, last_seen_at) VALUES (\"x\", 1, $t, $t), (\"y\", 1, $t, $t)");
$db->exec("INSERT INTO ideas (theme_id, title, description, participant_id, created_at) VALUES (1, \"Repas bio le vendredi\", \"\", 1, $t)");
$db->exec("INSERT INTO votes VALUES (1, 2, $t)");
$db->exec("INSERT INTO notes (idea_id, participant_id, body, created_at) VALUES (1, 2, \"Loi EGalim\", $t)");
' "$D/ancienne.sqlite" "$ROOT/migrations/001_init.sql" "$OLD_PW"
OA="$TMP/old-admin.jar"; OP="$TMP/old-p.jar"
post "$OA" "$B/ancienne/admin" "$B/ancienne/admin" --data-urlencode "action=login" --data-urlencode "password=$OLD_PW"
check "base v1 : connexion admin" 303 "$CODE"
check "base v1 : schéma migré" "2" "$(sql "$D/ancienne.sqlite" "PRAGMA user_version")"
check "base v1 : réglages de vote déplacés" "" "$(sql "$D/ancienne.sqlite" "SELECT key FROM settings WHERE key IN ('opens_at', 'closes_at', 'closed_at', 'max_ideas')")"
req "$OA" "$B/ancienne/admin"; body_has "base v1 : consultation reprise" "Propositions des parents"
req "$OA" "$B/ancienne/admin/activite/1"; body_has "base v1 : plafond repris" 'name="max_ideas" pattern="[0-9]+" value="7"'; body_has "base v1 : échéance reprise" "échéance"
post "$OP" "$B/ancienne/acces" "$B/ancienne/acces" --data-urlencode "code=ABCD-EFGH"; check "base v1 : ancien code valide" 303 "$CODE"
req "$OP" "$B/ancienne/"; body_has "base v1 : message d'accueil conservé" "Bonjour"; body_has "base v1 : activité au tableau de bord" "Propositions des parents"
req "$OP" "$B/ancienne/activite/1"; body_has "base v1 : idée reprise" "Repas bio le vendredi"; body_has "base v1 : vote repris" '<span class="vote-count">1</span>'
body_has "base v1 : note reprise" "Loi EGalim"; body_has "base v1 : thème repris" "Cantine"; body_has "base v1 : compte à rebours" "J-"
post "$OP" "$B/ancienne/activite/1" "$B/ancienne/activite/1" --data-urlencode "action=add_idea" --data-urlencode "theme_id=2" --data-urlencode "title=Nouvelle idée après migration" --data-urlencode "description="
check "base v1 : nouvelle idée" 303 "$CODE"

echo "── Révocation, clôture, suppression ──"
req "$A" "$B/master/admin"; CID=$(grep -o 'name="code_id" value="[0-9]*"' "$TMP/body" | head -2 | tail -1 | sed 's/.*value="//;s/"//')
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=revoke_code" --data-urlencode "code_id=$CID"
check "révocation" 303 "$CODE"
req "$P1" "$B/master/";                 check "participant révoqué perd l'accès" 303 "$CODE"
req "$P3" "$B/master/";                 check "autre code toujours valide" 200 "$CODE"
post "$A" "$CA" "$CA" --data-urlencode "action=close_now"; check "clôture" 303 "$CODE"
post "$P3" "$C" "$C" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "vote après clôture" 403 "$CODE"
req "$P3" "$C"; body_has "bandeau clôture" "La période de vote est terminée"
req "$P3" "$B/master/"; body_has "activités terminées regroupées" "Activités terminées"
post "$P3" "$C" "$C" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=Note après clôture"
check "note possible après clôture" 303 "$CODE"
post "$A" "$CA" "$CA" --data-urlencode "action=reopen"; check "réouverture" 303 "$CODE"
req "$P3" "$C"; body_lacks "réouverture effective" "La période de vote est terminée"
post "$A" "$C" "$C" --data-urlencode "action=delete_idea" --data-urlencode "idea_id=$IDEA"
check "modération : admin supprime" 303 "$CODE"
post "$A" "$SGA" "$SGA" --data-urlencode "action=unpublish"; check "retour en brouillon" 303 "$CODE"
req "$P3" "$SG"; check "brouillon de nouveau invisible" 404 "$CODE"
post "$A" "$SGA" "$SGA" --data-urlencode "action=delete_activity"; check "suppression d'activité" 303 "$CODE"
check "inscriptions supprimées avec l'activité" "0" "$(sql "$D/master.sqlite" "SELECT COUNT(*) FROM signups")"
post "$A" "$CA" "$CA" --data-urlencode "action=delete_activity"
check "idées, votes et notes supprimés avec la consultation" "0,0,0" "$(sql "$D/master.sqlite" "SELECT COUNT(*) FROM ideas WHERE activity_id = $CONS UNION ALL SELECT COUNT(*) FROM themes WHERE activity_id = $CONS UNION ALL SELECT COUNT(*) FROM votes")"
req "$A" "$CA"; check "activité supprimée introuvable" 404 "$CODE"

echo "── Limitation des essais ──"
for i in $(seq 1 8); do post "$X" "$B/master/acces" "$B/master/acces" --data-urlencode "code=AAAA-AAA$i" >/dev/null; done
post "$X" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"
check "blocage après 8 échecs" 429 "$CODE"

echo
echo "Résultat : $PASS réussis, $FAIL échec(s)"
[ "$FAIL" -eq 0 ]
