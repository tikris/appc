#!/usr/bin/env bash
# Test de bout en bout : démarre le serveur PHP intégré sur une base temporaire et rejoue les parcours
# clés (accès par code, votes, notes, administration, isolation entre instances, console super-admin).
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

A="$TMP/admin.jar"; P1="$TMP/p1.jar"; P2="$TMP/p2.jar"; P3="$TMP/p3.jar"; P4="$TMP/p4.jar"; X="$TMP/x.jar"; S="$TMP/super.jar"

echo "── Routage et accès ──"
req "$X" "$B/";                         check "accueil" 200 "$CODE"; body_has "accueil" "Parents à Paris Centre"
req "$X" "$B/master";                   check "slash final" 301 "$CODE"; check "slash final (cible)" "/master/" "$(location)"
req "$X" "$B/master/";                  check "portail sans code" 303 "$CODE"; check "portail sans code (cible)" "/master/acces" "$(location)"
req "$X" "$B/inconnu/";                 check "instance inconnue" 404 "$CODE"
req "$X" "$B/Bad_Slug/";                check "slug invalide" 404 "$CODE"
req "$X" "$B/../../etc/passwd" --path-as-is; check "traversée de chemin" 404 "$CODE"
req "$X" "$B/master/acces";             check "page d'accès" 200 "$CODE"; body_has "en-tête CSP" "Accès au portail"
grep -qi "content-security-policy: default-src 'none'" "$TMP/hdr" && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "  ÉCHEC  en-tête CSP absent"; }

echo "── CSRF ──"
req "$X" "$B/master/acces" --data-urlencode "code=ABCD-EFGH"; check "POST sans jeton CSRF" 403 "$CODE"

echo "── Administration : connexion et codes ──"
req "$A" "$B/master/admin";             check "login admin (formulaire)" 200 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=login" --data-urlencode "password=mauvais"
check "mauvais mot de passe admin" 401 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=login" --data-urlencode "password=$ADMIN_PW"
check "bon mot de passe admin" 303 "$CODE"
req "$A" "$B/master/admin";             check "panneau admin" 200 "$CODE"; body_has "panneau admin" "Codes d'accès"; body_has "mot de passe provisoire" "provisoire"

post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=Parents CE2" --data-urlencode "max_uses=" --data-urlencode "expires_at="
check "création code partagé" 303 "$CODE"
req "$A" "$B/master/admin"; SHARED=$(newest_code)
[ -n "$SHARED" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "  ÉCHEC  code partagé introuvable"; }
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=Individuel" --data-urlencode "max_uses=1" --data-urlencode "expires_at="
req "$A" "$B/master/admin"; SINGLE=$(newest_code)
check "codes distincts" "1" "$([ "$SHARED" != "$SINGLE" ] && echo 1 || echo 0)"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=create_code" --data-urlencode "label=x" --data-urlencode "max_uses=abc" --data-urlencode "expires_at="
check "max_uses invalide" 422 "$CODE"

echo "── Participants ──"
post "$P1" "$B/master/acces" "$B/master/acces" --data-urlencode "code=ZZZZ-ZZZZ"
check "code inconnu" 401 "$CODE"; body_has "message code" "Code invalide"
post "$P1" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$(echo "$SHARED" | tr 'A-Z' 'a-z' | tr -d '-')"
check "code valide (saisie minuscule sans tiret)" 303 "$CODE"
req "$P1" "$B/master/";                 check "portail avec code" 200 "$CODE"; body_has "formulaire d'idée" "Proposer une idée"

post "$P2" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SHARED"; check "2e participant (code partagé)" 303 "$CODE"
post "$P3" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"; check "code individuel, 1re utilisation" 303 "$CODE"
post "$P4" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"; check "code individuel, 2e utilisation" 401 "$CODE"

echo "── Idées, votes, notes ──"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=4" --data-urlencode "title=Liste des adultes autorisés" --data-urlencode "description=Une liste à la sortie de l'école."
check "ajout d'idée" 303 "$CODE"; IDEA=$(location | sed -n 's/.*#idea-//p')
[ -n "$IDEA" ] && PASS=$((PASS+1)) || { FAIL=$((FAIL+1)); echo "  ÉCHEC  identifiant d'idée absent"; IDEA=1; }
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=99" --data-urlencode "title=t" --data-urlencode "description=d"
check "thème invalide" 422 "$CODE"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=ab" --data-urlencode "description=x"
check "titre trop court (< 3)" 422 "$CODE"; body_has "message titre" "entre 3 et 120"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=$(printf 'a%.0s' $(seq 1 121))" --data-urlencode "description=x"
check "titre trop long (> 120)" 422 "$CODE"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Titre valide" --data-urlencode "description=$(printf 'a%.0s' $(seq 1 401))"
check "description trop longue (> 400)" 422 "$CODE"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Sans description" --data-urlencode "description="
check "description facultative" 303 "$CODE"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=<script>alert(1)</script>" --data-urlencode "description=x"
req "$P1" "$B/master/"; body_lacks "échappement XSS" "<script>alert(1)"; body_has "échappement XSS" "&lt;script&gt;alert(1)"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title==HYPERLINK(1)" --data-urlencode "description=injection tableur"

post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "vote pour sa propre idée refusé" 403 "$CODE"
req "$P1" "$B/master/"; body_has "pas de bouton de vote sur sa propre idée" 'ma<br>prop.'
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "vote" 303 "$CODE"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"; check "double vote (idempotent)" 303 "$CODE"
post "$P3" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
req "$P2" "$B/master/"; body_has "2 votes (1 par participant)" '<span class="vote-count">2</span>'
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=Voir le code de la sécurité intérieure."
check "note de contexte" 303 "$CODE"
req "$P1" "$B/master/"; body_has "note affichée" "Voir le code de la sécurité intérieure."
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=$(printf 'x%.0s' $(seq 1 601))"
check "note trop longue" 422 "$CODE"

echo "── Droits ──"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=edit_idea" --data-urlencode "idea_id=$IDEA" --data-urlencode "theme_id=1" --data-urlencode "title=piraté" --data-urlencode "description=x"
check "modifier l'idée d'autrui" 403 "$CODE"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=delete_idea" --data-urlencode "idea_id=$IDEA"
check "supprimer l'idée d'autrui" 403 "$CODE"
post "$P1" "$B/master/" "$B/master/" --data-urlencode "action=edit_idea" --data-urlencode "idea_id=$IDEA" --data-urlencode "theme_id=4" --data-urlencode "title=Liste des adultes autorisés (v2)" --data-urlencode "description=Une liste à la sortie."
check "modifier sa propre idée" 303 "$CODE"
req "$A" "$B/master/";                  check "admin sans code : consultation" 200 "$CODE"; body_has "admin : bouton supprimer" "Supprimer"
post "$A" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "admin sans code ne vote pas" 403 "$CODE"

echo "── Export ──"
req "$A" "$B/master/admin/export.csv";  check "export CSV" 200 "$CODE"; body_has "export : idée" "Liste des adultes autorisés (v2)"; body_has "export : anti-injection de formule" "'=HYPERLINK(1)"
req "$P1" "$B/master/admin/export.csv"; check "export interdit aux participants" 403 "$CODE"

echo "── Calendrier d'ouverture ──"
SET=(--data-urlencode action=save_settings --data-urlencode "name=Portail maître" --data-urlencode max_ideas=5)
FUTURE=$(php -r 'echo date("Y-m-d\\TH:i", time() + 10 * 86400);')
FAR=$(php -r 'echo date("Y-m-d\\TH:i", time() + 40 * 86400);')
post "$A" "$B/master/admin" "$B/master/admin" "${SET[@]}" --data-urlencode "opens_at=$FUTURE" --data-urlencode "closes_at=$FAR"
check "début dans le futur enregistré" 303 "$CODE"
req "$P2" "$B/master/"; body_has "bandeau avant ouverture" "n'a pas encore commencé"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=add_idea" --data-urlencode "theme_id=1" --data-urlencode "title=Avant ouverture" --data-urlencode "description="
check "dépôt refusé avant l'ouverture" 403 "$CODE"; body_has "message avant ouverture" "pas encore commencé"
post "$P2" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "vote refusé avant l'ouverture" 403 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" "${SET[@]}" --data-urlencode "opens_at=$FAR" --data-urlencode "closes_at=$FUTURE"
check "fin antérieure au début refusée" 422 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" "${SET[@]}" --data-urlencode "opens_at=" --data-urlencode "closes_at=$FUTURE"
check "fenêtre ouverte avec échéance" 303 "$CODE"
req "$P2" "$B/master/"; body_has "compte à rebours" "J-"; body_has "formulaire visible" "Proposer une idée"
post "$A" "$B/master/admin" "$B/master/admin" "${SET[@]}" --data-urlencode "opens_at=" --data-urlencode "closes_at="
check "retour à la fenêtre ouverte sans échéance" 303 "$CODE"

echo "── Isolation entre instances ──"
req "$S" "$B/_super";                   check "console super-admin (login)" 200 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "password=$SUPER_PW"; check "connexion super-admin" 303 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=ecole-etienne-marcel" --data-urlencode "name=École Étienne Marcel"
check "création d'instance" 200 "$CODE"; body_has "mot de passe affiché une fois" "Mot de passe provisoire"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=ecole-etienne-marcel" --data-urlencode "name=Doublon"
check "instance en double" 422 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=Mauvais Slug" --data-urlencode "name=x"
check "slug invalide à la création" 422 "$CODE"
post "$S" "$B/_super" "$B/_super" --data-urlencode "action=create" --data-urlencode "slug=admin" --data-urlencode "name=réservé"
check "slug réservé" 422 "$CODE"
req "$P1" "$B/ecole-etienne-marcel/";   check "cookie master inutilisable ailleurs" 303 "$CODE"
post "$X" "$B/ecole-etienne-marcel/acces" "$B/ecole-etienne-marcel/acces" --data-urlencode "code=$SHARED"
check "code master refusé sur une autre instance" 401 "$CODE"
req "$X" "$B/_super";                   check "console super-admin sans session" 200 "$CODE"; body_has "login requis" "Mot de passe"

echo "── Révocation et clôture ──"
req "$A" "$B/master/admin"; CID=$(grep -o 'name="code_id" value="[0-9]*"' "$TMP/body" | head -2 | tail -1 | sed 's/.*value="//;s/"//')
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=revoke_code" --data-urlencode "code_id=$CID"
check "révocation" 303 "$CODE"
req "$P1" "$B/master/";                 check "participant révoqué perd l'accès" 303 "$CODE"
req "$P3" "$B/master/";                 check "autre code toujours valide" 200 "$CODE"
post "$A" "$B/master/admin" "$B/master/admin" --data-urlencode "action=close_now"; check "clôture" 303 "$CODE"
post "$P3" "$B/master/" "$B/master/" --data-urlencode "action=vote" --data-urlencode "idea_id=$IDEA"
check "vote après clôture" 403 "$CODE"
req "$P3" "$B/master/"; body_has "bandeau clôture" "La période de vote est terminée"
post "$P3" "$B/master/" "$B/master/" --data-urlencode "action=save_note" --data-urlencode "idea_id=$IDEA" --data-urlencode "body=Note après clôture"
check "note possible après clôture" 303 "$CODE"
post "$A" "$B/master/" "$B/master/" --data-urlencode "action=delete_idea" --data-urlencode "idea_id=$IDEA"
check "modération : admin supprime" 303 "$CODE"

echo "── Limitation des essais ──"
for i in $(seq 1 8); do post "$X" "$B/master/acces" "$B/master/acces" --data-urlencode "code=AAAA-AAA$i" >/dev/null; done
post "$X" "$B/master/acces" "$B/master/acces" --data-urlencode "code=$SINGLE"
check "blocage après 8 échecs" 429 "$CODE"

echo
echo "Résultat : $PASS réussis, $FAIL échec(s)"
[ "$FAIL" -eq 0 ]
