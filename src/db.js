'use strict'

const { DatabaseSync } = require('node:sqlite')
const { EventEmitter } = require('events')
const path = require('path')
const fs = require('fs')

const DEFAULT_DB_PATH = path.join(__dirname, '..', 'data', 'tasks.db')

function initDb(dbPath) {
  const resolvedPath = dbPath ?? process.env.DB_PATH ?? DEFAULT_DB_PATH

  if (resolvedPath !== ':memory:') {
    fs.mkdirSync(path.dirname(resolvedPath), { recursive: true })
  }

  const db = new DatabaseSync(resolvedPath)

  // WAL mode for better concurrent read performance; NORMAL sync is safe with WAL
  db.exec('PRAGMA journal_mode = WAL')
  db.exec('PRAGMA synchronous = NORMAL')
  // Enforce referential integrity — SQLite disables FK checks by default
  db.exec('PRAGMA foreign_keys = ON')

  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      username        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
      password_hash   TEXT    NOT NULL,
      api_token_hash  TEXT,
      created_at      INTEGER NOT NULL DEFAULT (unixepoch() * 1000)
    );

    CREATE TABLE IF NOT EXISTS tasks (
      id           INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id      INTEGER NOT NULL,
      name         TEXT    NOT NULL,
      description  TEXT,
      deadline     TEXT,
      completed_at INTEGER,
      created_at   INTEGER NOT NULL DEFAULT (unixepoch() * 1000),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS task_tags (
      task_id  INTEGER NOT NULL,
      tag      TEXT    NOT NULL COLLATE NOCASE,
      PRIMARY KEY (task_id, tag),
      FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS sessions (
      sid      TEXT    PRIMARY KEY,
      data     TEXT    NOT NULL,
      expires  INTEGER NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_tasks_user_completed
      ON tasks(user_id, completed_at);
    CREATE INDEX IF NOT EXISTS idx_task_tags_task_id
      ON task_tags(task_id);
    CREATE INDEX IF NOT EXISTS idx_sessions_expires
      ON sessions(expires);
  `)

  return db
}

/**
 * express-session-compatible store backed by SQLite.
 * @fastify/session accepts any store that implements get/set/destroy
 * with Node-style (err, result) callbacks and extends EventEmitter.
 */
class SqliteSessionStore extends EventEmitter {
  constructor(db) {
    super()
    this._db = db

    // Remove stale sessions immediately on startup
    this._db.prepare('DELETE FROM sessions WHERE expires <= ?').run(Date.now())

    // Periodic cleanup so the sessions table doesn't grow unbounded
    const cleanup = setInterval(() => {
      this._db.prepare('DELETE FROM sessions WHERE expires <= ?').run(Date.now())
    }, 60 * 60 * 1000) // every hour

    cleanup.unref() // Allow the process to exit even if this timer is pending
  }

  get(sid, callback) {
    try {
      const row = this._db
        .prepare('SELECT data FROM sessions WHERE sid = ? AND expires > ?')
        .get(sid, Date.now())
      callback(null, row ? JSON.parse(row.data) : null)
    } catch (err) {
      callback(err)
    }
  }

  set(sid, session, callback) {
    try {
      const expires = session.cookie?.expires
        ? new Date(session.cookie.expires).getTime()
        : Date.now() + 7 * 24 * 60 * 60 * 1000 // 7 days
      this._db
        .prepare('INSERT OR REPLACE INTO sessions (sid, data, expires) VALUES (?, ?, ?)')
        .run(sid, JSON.stringify(session), expires)
      callback(null)
    } catch (err) {
      callback(err)
    }
  }

  destroy(sid, callback) {
    try {
      this._db.prepare('DELETE FROM sessions WHERE sid = ?').run(sid)
      callback(null)
    } catch (err) {
      callback(err)
    }
  }
}

module.exports = { initDb, SqliteSessionStore }
