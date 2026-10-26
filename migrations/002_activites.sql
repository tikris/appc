-- Plusieurs activités par établissement (consultations, sondages, inscriptions bénévoles),
-- et annonces / agenda affichés sur le tableau de bord du portail.
--
-- Reprise des données : une instance créée avant cette migration contient une seule consultation
-- (thèmes et idées sans activité, dates dans settings). Elle devient l'activité n° 1, publiée.

CREATE TABLE activities (
    id           INTEGER PRIMARY KEY,
    kind         TEXT    NOT NULL CHECK (kind IN ('consultation', 'poll', 'signup')),
    title        TEXT    NOT NULL,
    description  TEXT    NOT NULL DEFAULT '',
    opens_at     INTEGER,
    closes_at    INTEGER,
    closed_at    INTEGER,
    published_at INTEGER,                     -- NULL = brouillon, invisible des participants
    -- Consultation : nombre maximal de propositions par participant
    max_ideas    INTEGER NOT NULL DEFAULT 5 CHECK (max_ideas BETWEEN 1 AND 50),
    -- Sondage : plusieurs réponses possibles ; résultats visibles « live » (toujours),
    -- « voted » (après avoir répondu) ou « closed » (après la clôture)
    multiple     INTEGER NOT NULL DEFAULT 0,
    results      TEXT    NOT NULL DEFAULT 'voted' CHECK (results IN ('live', 'voted', 'closed')),
    -- Inscriptions : prénoms visibles des autres participants (sinon de l'administrateur seul)
    show_names   INTEGER NOT NULL DEFAULT 0,
    created_at   INTEGER NOT NULL
);

INSERT INTO activities (id, kind, title, opens_at, closes_at, closed_at, published_at, max_ideas, created_at)
SELECT 1, 'consultation', 'Propositions des parents',
       (SELECT CAST(value AS INTEGER) FROM settings WHERE key = 'opens_at' AND value <> ''),
       (SELECT CAST(value AS INTEGER) FROM settings WHERE key = 'closes_at' AND value <> ''),
       (SELECT CAST(value AS INTEGER) FROM settings WHERE key = 'closed_at' AND value <> ''),
       COALESCE((SELECT CAST(value AS INTEGER) FROM settings WHERE key = 'created_at'), CAST(strftime('%s', 'now') AS INTEGER)),
       COALESCE((SELECT MIN(MAX(CAST(value AS INTEGER), 1), 50) FROM settings WHERE key = 'max_ideas'), 5),
       COALESCE((SELECT CAST(value AS INTEGER) FROM settings WHERE key = 'created_at'), CAST(strftime('%s', 'now') AS INTEGER))
WHERE EXISTS (SELECT 1 FROM settings WHERE key = 'name');

DELETE FROM settings WHERE key IN ('opens_at', 'closes_at', 'closed_at', 'max_ideas');

-- SQLite n'accepte pas NOT NULL sur une colonne ajoutée avec REFERENCES : l'application garantit la valeur.
ALTER TABLE themes ADD COLUMN activity_id INTEGER REFERENCES activities(id) ON DELETE CASCADE;
ALTER TABLE ideas  ADD COLUMN activity_id INTEGER REFERENCES activities(id) ON DELETE CASCADE;
UPDATE themes SET activity_id = 1 WHERE EXISTS (SELECT 1 FROM activities WHERE id = 1);
UPDATE ideas  SET activity_id = 1 WHERE EXISTS (SELECT 1 FROM activities WHERE id = 1);
CREATE INDEX themes_activity ON themes(activity_id);
CREATE INDEX ideas_activity ON ideas(activity_id);

-- Annonces du tableau de bord ; avec une date d'événement, elles figurent aussi à l'agenda.
CREATE TABLE posts (
    id         INTEGER PRIMARY KEY,
    title      TEXT    NOT NULL,
    body       TEXT    NOT NULL DEFAULT '',
    event_at   INTEGER,
    location   TEXT    NOT NULL DEFAULT '',
    pinned     INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER
);

-- Sondages : une ligne par réponse cochée ; modifiable tant que le sondage est ouvert.
CREATE TABLE poll_options (
    id          INTEGER PRIMARY KEY,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    label       TEXT    NOT NULL,
    position    INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX poll_options_activity ON poll_options(activity_id);

CREATE TABLE poll_answers (
    option_id      INTEGER NOT NULL REFERENCES poll_options(id) ON DELETE CASCADE,
    participant_id INTEGER NOT NULL REFERENCES participants(id),
    created_at     INTEGER NOT NULL,
    PRIMARY KEY (option_id, participant_id)
);

-- Inscriptions bénévoles : créneaux à capacité limitée.
CREATE TABLE slots (
    id          INTEGER PRIMARY KEY,
    activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    label       TEXT    NOT NULL,
    starts_at   INTEGER,
    capacity    INTEGER NOT NULL CHECK (capacity BETWEEN 1 AND 500),
    position    INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX slots_activity ON slots(activity_id);

-- Le prénom (ou pseudo) est la seule donnée personnelle du portail. Il n'est volontairement PAS relié
-- à participants : owner_hash dérive du jeton gardé par le navigateur, sans lien direct en base avec les
-- propositions ou les votes d'un participant. created_at est arrondi au jour par l'application pour limiter
-- les recoupements par horodatage (voir docs/SECURITE-RGPD.md).
CREATE TABLE signups (
    id         INTEGER PRIMARY KEY,
    slot_id    INTEGER NOT NULL REFERENCES slots(id) ON DELETE CASCADE,
    owner_hash TEXT    NOT NULL,
    name       TEXT    NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE (slot_id, owner_hash)
);
