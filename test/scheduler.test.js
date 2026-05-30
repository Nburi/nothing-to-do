'use strict'

const { test, describe } = require('node:test')
const assert = require('node:assert/strict')

const { DatabaseSync } = require('node:sqlite')
const { purgeCompletedTasks, getStartOfTodayZurich } = require('../src/scheduler')

function makeMemDb() {
  const db = new DatabaseSync(':memory:')
  db.exec('PRAGMA foreign_keys = ON')
  db.exec(`
    CREATE TABLE users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      api_token_hash TEXT,
      created_at INTEGER NOT NULL DEFAULT (unixepoch() * 1000)
    );
    CREATE TABLE tasks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      name TEXT NOT NULL,
      description TEXT,
      deadline TEXT,
      completed_at INTEGER,
      created_at INTEGER NOT NULL DEFAULT (unixepoch() * 1000),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    CREATE TABLE task_tags (
      task_id INTEGER NOT NULL,
      tag TEXT NOT NULL,
      PRIMARY KEY (task_id, tag),
      FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    );
  `)
  db.prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)').run('testuser', 'hash')
  return db
}

function insertTask(db, completedAt = null) {
  const result = db.prepare(
    'INSERT INTO tasks (user_id, name, completed_at) VALUES (1, ?, ?)'
  ).run('task', completedAt)
  return Number(result.lastInsertRowid)
}

describe('Midnight purge (scheduler)', () => {
  test('getStartOfTodayZurich returns a UTC timestamp < now', () => {
    const start = getStartOfTodayZurich()
    const now   = Date.now()
    assert.ok(start <= now, 'start of today must be in the past')
    assert.ok(start > now - 48 * 3600 * 1000, 'start of today must be within last 48 hours')
  })

  test('purge does not delete active tasks', () => {
    const db = makeMemDb()
    const id = insertTask(db, null) // active
    const deleted = purgeCompletedTasks(db)
    assert.equal(deleted, 0)
    assert.ok(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id))
  })

  test('purge does not delete tasks completed today', () => {
    const db = makeMemDb()
    const startOfToday = getStartOfTodayZurich()
    // Completed 1 hour after today's midnight
    const id = insertTask(db, startOfToday + 3_600_000)
    const deleted = purgeCompletedTasks(db)
    assert.equal(deleted, 0)
    assert.ok(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id))
  })

  test('purge deletes tasks completed yesterday', () => {
    const db = makeMemDb()
    const startOfToday = getStartOfTodayZurich()
    // Completed 1 hour before today's midnight (i.e., yesterday)
    const id = insertTask(db, startOfToday - 3_600_000)
    const deleted = purgeCompletedTasks(db)
    assert.equal(deleted, 1)
    assert.equal(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id), undefined)
  })

  test('purge deletes tasks completed several days ago', () => {
    const db = makeMemDb()
    const startOfToday = getStartOfTodayZurich()
    const id1 = insertTask(db, startOfToday - 2 * 86_400_000) // 2 days ago
    const id2 = insertTask(db, startOfToday - 5 * 86_400_000) // 5 days ago
    const id3 = insertTask(db, null)                            // active — must NOT be deleted
    const deleted = purgeCompletedTasks(db)
    assert.equal(deleted, 2)
    assert.equal(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id1), undefined)
    assert.equal(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id2), undefined)
    assert.ok(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id3))
  })

  test('purge is idempotent (running twice has the same effect)', () => {
    const db = makeMemDb()
    const startOfToday = getStartOfTodayZurich()
    insertTask(db, startOfToday - 3_600_000)
    const firstRun  = purgeCompletedTasks(db)
    const secondRun = purgeCompletedTasks(db)
    assert.equal(firstRun,  1)
    assert.equal(secondRun, 0) // nothing left to delete
  })

  test('purge handles catch-up across multiple days offline', () => {
    const db = makeMemDb()
    const startOfToday = getStartOfTodayZurich()
    // Simulate tasks from 1, 3, and 7 days ago
    const ids = [1, 3, 7].map(d => insertTask(db, startOfToday - d * 86_400_000))
    const active = insertTask(db, null)

    const deleted = purgeCompletedTasks(db)
    assert.equal(deleted, 3)
    for (const id of ids) {
      assert.equal(db.prepare('SELECT id FROM tasks WHERE id = ?').get(id), undefined)
    }
    assert.ok(db.prepare('SELECT id FROM tasks WHERE id = ?').get(active))
  })
})
