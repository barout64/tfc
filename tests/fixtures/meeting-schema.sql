-- Meeting Management System - database schema (SQLite)

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS departments (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'member'
                  CHECK (role IN ('admin', 'organizer', 'secretary', 'member')),
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    job_title     TEXT,
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS rooms (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT NOT NULL UNIQUE,
    capacity  INTEGER NOT NULL DEFAULT 10,
    location  TEXT,
    equipment TEXT
);

CREATE TABLE IF NOT EXISTS meetings (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    title        TEXT NOT NULL,
    description  TEXT,
    meeting_type TEXT NOT NULL DEFAULT 'Team Meeting',
    start_time   TEXT NOT NULL,              -- 'YYYY-MM-DD HH:MM'
    end_time     TEXT NOT NULL,
    room_id      INTEGER REFERENCES rooms(id) ON DELETE SET NULL,
    online_link  TEXT,
    organizer_id INTEGER NOT NULL REFERENCES users(id),
    status       TEXT NOT NULL DEFAULT 'scheduled'
                 CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled')),
    quorum       INTEGER NOT NULL DEFAULT 50, -- % of participants that must be present
    series_id    TEXT,                        -- shared by meetings created as a repeating series
    created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS participants (
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rsvp       TEXT NOT NULL DEFAULT 'pending'
               CHECK (rsvp IN ('pending', 'accepted', 'declined', 'tentative')),
    attendance TEXT NOT NULL DEFAULT 'unknown'
               CHECK (attendance IN ('unknown', 'present', 'absent', 'excused')),
    PRIMARY KEY (meeting_id, user_id)
);

CREATE TABLE IF NOT EXISTS agenda_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id   INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    title        TEXT NOT NULL,
    description  TEXT,
    presenter_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    duration     INTEGER,                    -- minutes
    position     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS minutes (
    meeting_id  INTEGER PRIMARY KEY REFERENCES meetings(id) ON DELETE CASCADE,
    content     TEXT NOT NULL DEFAULT '',
    status      TEXT NOT NULL DEFAULT 'draft'
                CHECK (status IN ('draft', 'submitted', 'approved')),
    updated_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at  TEXT,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TEXT
);

CREATE TABLE IF NOT EXISTS decisions (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id     INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    agenda_item_id INTEGER REFERENCES agenda_items(id) ON DELETE SET NULL,
    text           TEXT NOT NULL,
    proposer_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    seconder_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    votes_for      INTEGER,
    votes_against  INTEGER,
    votes_abstain  INTEGER,
    outcome        TEXT,                     -- carried / defeated / deferred / noted
    created_at     TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Groups and committees (e.g. Board of Directors, Management Team)
CREATE TABLE IF NOT EXISTS user_groups (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    description TEXT,
    color       TEXT NOT NULL DEFAULT '#3b5bdb',
    created_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS user_group_members (
    group_id INTEGER NOT NULL REFERENCES user_groups(id) ON DELETE CASCADE,
    user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (group_id, user_id)
);

-- Which departments / groups a meeting was addressed to (for display)
CREATE TABLE IF NOT EXISTS meeting_audience (
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    kind       TEXT NOT NULL CHECK (kind IN ('department', 'group')),
    ref_id     INTEGER NOT NULL,
    PRIMARY KEY (meeting_id, kind, ref_id)
);

CREATE TABLE IF NOT EXISTS action_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id   INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    title        TEXT NOT NULL,
    description  TEXT,
    assignee_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    due_date     TEXT,                       -- 'YYYY-MM-DD'
    status       TEXT NOT NULL DEFAULT 'open'
                 CHECK (status IN ('open', 'in_progress', 'done')),
    created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    completed_at TEXT
);

CREATE TABLE IF NOT EXISTS attachments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id  INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    filename    TEXT NOT NULL,
    stored_name TEXT NOT NULL,
    size        INTEGER,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    uploaded_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message    TEXT NOT NULL,
    link       TEXT,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_meetings_start   ON meetings(start_time);
CREATE INDEX IF NOT EXISTS idx_meetings_series  ON meetings(series_id, start_time);
CREATE INDEX IF NOT EXISTS idx_meetings_room    ON meetings(room_id, start_time);
CREATE INDEX IF NOT EXISTS idx_participants_usr ON participants(user_id);
CREATE INDEX IF NOT EXISTS idx_actions_assignee ON action_items(assignee_id, status);
CREATE INDEX IF NOT EXISTS idx_notif_user       ON notifications(user_id, is_read);

ALTER TABLE meetings ADD COLUMN started_at TEXT;
ALTER TABLE meetings ADD COLUMN ended_at TEXT;
ALTER TABLE meetings ADD COLUMN current_agenda_id INTEGER;
ALTER TABLE agenda_items ADD COLUMN started_at TEXT;
ALTER TABLE agenda_items ADD COLUMN ended_at TEXT;
ALTER TABLE attachments ADD COLUMN agenda_item_id INTEGER;
