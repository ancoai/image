CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT,
    is_admin INTEGER NOT NULL DEFAULT 0,
    oauth_provider TEXT,
    oauth_subject TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT
);

CREATE TABLE storage_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    config_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE oauth_providers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    config_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    storage_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    width INTEGER,
    height INTEGER,
    created_at TEXT NOT NULL,
    public_url TEXT NOT NULL,
    FOREIGN KEY(storage_id) REFERENCES storage_configs(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE puzzles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    image_id INTEGER NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    visibility TEXT NOT NULL,
    grid_cols INTEGER NOT NULL,
    grid_rows INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    FOREIGN KEY(image_id) REFERENCES images(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    html_snippet TEXT NOT NULL,
    success_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    last_verified_at TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE verification_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id INTEGER NOT NULL,
    success INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(token_id) REFERENCES verification_tokens(id)
);

CREATE INDEX idx_puzzles_visibility ON puzzles (visibility);
CREATE INDEX idx_puzzles_slug ON puzzles (slug);
