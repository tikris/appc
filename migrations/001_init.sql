-- Schéma d'une instance (un fichier SQLite par établissement).
-- Les migrations sont appliquées automatiquement à l'ouverture de la base
-- (PRAGMA user_version). Ne jamais modifier une migration déjà déployée :
-- ajouter un nouveau fichier NNN_description.sql.

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE themes (
    id       INTEGER PRIMARY KEY,
    name     TEXT    NOT NULL,
    color    INTEGER NOT NULL DEFAULT 1 CHECK (color BETWEEN 1 AND 8),
    position INTEGER NOT NULL DEFAULT 0
);

-- Codes d'accès délivrés par l'administrateur de l'instance.
-- max_uses NULL = code partagé (usages illimités) ; 1 = code individuel.
CREATE TABLE codes (
    id         INTEGER PRIMARY KEY,
    code       TEXT    NOT NULL UNIQUE,
    label      TEXT    NOT NULL DEFAULT '',
    max_uses   INTEGER CHECK (max_uses IS NULL OR max_uses >= 1),
    expires_at INTEGER,
    revoked_at INTEGER,
    created_at INTEGER NOT NULL
);

-- Un participant = un navigateur ayant saisi un code valide. Aucune donnée personnelle.
CREATE TABLE participants (
    id           INTEGER PRIMARY KEY,
    token_hash   TEXT    NOT NULL UNIQUE,
    code_id      INTEGER NOT NULL REFERENCES codes(id),
    created_at   INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL
);
CREATE INDEX participants_code ON participants(code_id);

CREATE TABLE ideas (
    id             INTEGER PRIMARY KEY,
    theme_id       INTEGER NOT NULL REFERENCES themes(id),
    title          TEXT    NOT NULL,
    description    TEXT    NOT NULL,
    participant_id INTEGER NOT NULL REFERENCES participants(id),
    created_at     INTEGER NOT NULL,
    updated_at     INTEGER
);
CREATE INDEX ideas_theme ON ideas(theme_id);
CREATE INDEX ideas_author ON ideas(participant_id);

CREATE TABLE votes (
    idea_id        INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
    participant_id INTEGER NOT NULL REFERENCES participants(id),
    created_at     INTEGER NOT NULL,
    PRIMARY KEY (idea_id, participant_id)
);

-- Notes de contexte (cadre légal, réglementaire…) : une par participant et par idée.
CREATE TABLE notes (
    id             INTEGER PRIMARY KEY,
    idea_id        INTEGER NOT NULL REFERENCES ideas(id) ON DELETE CASCADE,
    participant_id INTEGER NOT NULL REFERENCES participants(id),
    body           TEXT    NOT NULL,
    created_at     INTEGER NOT NULL,
    updated_at     INTEGER,
    UNIQUE (idea_id, participant_id)
);

CREATE TABLE admin_sessions (
    token_hash TEXT PRIMARY KEY,
    created_at INTEGER NOT NULL
);
