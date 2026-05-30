'use strict'

const { createAuthHook } = require('../middleware/auth')
const { getStartOfTodayZurich } = require('../scheduler')

module.exports = async function apiV1(fastify) {
  const db = fastify.db

  // All routes in this plugin require authentication.
  // The hook accepts both a session cookie (web UI) and a Bearer token (API clients).
  fastify.addHook('preHandler', createAuthHook(db))

  const apiLimit = {
    config: {
      rateLimit: {
        max: 300,
        timeWindow: '1 minute',
        errorResponseBuilder: () => ({ error: 'Rate limit exceeded. Please slow down.' })
      }
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  function getTaskWithTags(taskId, userId) {
    const task = db.prepare(`
      SELECT id, name, description, deadline, completed_at, created_at
      FROM tasks WHERE id = ? AND user_id = ?
    `).get(taskId, userId)

    if (!task) return null

    const tags = db.prepare('SELECT tag FROM task_tags WHERE task_id = ? ORDER BY tag')
      .all(taskId)
      .map(r => r.tag)

    return serializeTask(task, tags)
  }

  function listTasksForUser(userId) {
    const startOfTodayMs = getStartOfTodayZurich()

    // Return active tasks and tasks completed today (Zurich time); nothing older.
    // Ordering: active first (by creation date), then completed tasks.
    const rows = db.prepare(`
      SELECT id, name, description, deadline, completed_at, created_at
      FROM tasks
      WHERE user_id = ?
        AND (completed_at IS NULL OR completed_at >= ?)
      ORDER BY
        CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END,
        created_at ASC
    `).all(userId, startOfTodayMs)

    if (rows.length === 0) return []

    // Batch-fetch all tags in a single query to avoid N+1
    const ids = rows.map(r => r.id)
    const placeholders = ids.map(() => '?').join(', ')
    const tagRows = db.prepare(
      `SELECT task_id, tag FROM task_tags WHERE task_id IN (${placeholders}) ORDER BY tag`
    ).all(...ids)

    const tagMap = {}
    for (const { task_id, tag } of tagRows) {
      if (!tagMap[task_id]) tagMap[task_id] = []
      tagMap[task_id].push(tag)
    }

    return rows.map(r => serializeTask(r, tagMap[r.id] ?? []))
  }

  function insertTags(taskId, tags) {
    if (!tags || tags.length === 0) return
    const stmt = db.prepare('INSERT OR IGNORE INTO task_tags (task_id, tag) VALUES (?, ?)')
    for (const raw of tags) {
      const tag = String(raw).trim().slice(0, 64)
      if (tag.length > 0) stmt.run(taskId, tag)
    }
  }

  function parseTags(raw) {
    if (raw == null) return []
    if (Array.isArray(raw)) return raw.map(String).filter(t => t.trim().length > 0)
    if (typeof raw === 'string') return raw.split(',').map(t => t.trim()).filter(t => t.length > 0)
    return []
  }

  // Convert a raw DB row to the public API shape.
  // completed_at is kept as a Unix ms integer (null when active).
  function serializeTask(row, tags) {
    return {
      id:           Number(row.id),
      name:         row.name,
      description:  row.description ?? null,
      tags:         tags,
      deadline:     row.deadline ?? null,
      completed_at: row.completed_at != null ? Number(row.completed_at) : null,
      created_at:   Number(row.created_at)
    }
  }

  // ── GET /api/v1/tasks ─────────────────────────────────────────────────────
  // Returns all active tasks plus tasks completed today (Zurich time).
  fastify.get('/tasks', apiLimit, async (request, reply) => {
    return reply.send({ tasks: listTasksForUser(request.user.id) })
  })

  // ── POST /api/v1/tasks ────────────────────────────────────────────────────
  // Body: { name: string (required), description?: string, tags?: string[], deadline?: string }
  // deadline format: "YYYY-MM-DD" or "YYYY-MM-DDTHH:MM"
  fastify.post('/tasks', apiLimit, async (request, reply) => {
    const { name, description, tags: rawTags, deadline } = request.body ?? {}

    if (typeof name !== 'string' || name.trim().length === 0) {
      return reply.code(400).send({ error: 'Task name is required and must not be empty' })
    }

    const tags = parseTags(rawTags)

    const result = db.prepare(`
      INSERT INTO tasks (user_id, name, description, deadline)
      VALUES (?, ?, ?, ?)
    `).run(
      request.user.id,
      name.trim(),
      description ? String(description).trim() || null : null,
      deadline    ? String(deadline).trim()    || null : null
    )

    const taskId = Number(result.lastInsertRowid)
    insertTags(taskId, tags)

    return reply.code(201).send(getTaskWithTags(taskId, request.user.id))
  })

  // ── PATCH /api/v1/tasks/:id ───────────────────────────────────────────────
  // Partial update: send only the fields you want to change.
  // Sending `tags: []` clears all tags. Omitting `tags` leaves them unchanged.
  fastify.patch('/tasks/:id', apiLimit, async (request, reply) => {
    const taskId = parseInt(request.params.id, 10)
    if (!Number.isFinite(taskId)) return reply.code(400).send({ error: 'Invalid task id' })

    const exists = db.prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?')
      .get(taskId, request.user.id)
    if (!exists) return reply.code(404).send({ error: 'Task not found' })

    const { name, description, tags: rawTags, deadline } = request.body ?? {}

    // Build a dynamic SET clause from whichever fields were provided
    const sets = []
    const vals = []

    if (name !== undefined) {
      if (typeof name !== 'string' || name.trim().length === 0) {
        return reply.code(400).send({ error: 'Task name must not be empty' })
      }
      sets.push('name = ?')
      vals.push(name.trim())
    }
    if (description !== undefined) {
      sets.push('description = ?')
      vals.push(description ? String(description).trim() || null : null)
    }
    if (deadline !== undefined) {
      sets.push('deadline = ?')
      vals.push(deadline ? String(deadline).trim() || null : null)
    }

    if (sets.length > 0) {
      db.prepare(`UPDATE tasks SET ${sets.join(', ')} WHERE id = ? AND user_id = ?`)
        .run(...vals, taskId, request.user.id)
    }

    if (rawTags !== undefined) {
      db.prepare('DELETE FROM task_tags WHERE task_id = ?').run(taskId)
      insertTags(taskId, parseTags(rawTags))
    }

    return reply.send(getTaskWithTags(taskId, request.user.id))
  })

  // ── PATCH /api/v1/tasks/:id/done ──────────────────────────────────────────
  // Toggles the completed state.
  // Inactive → sets completed_at to now.
  // Completed → clears completed_at, making the task active again.
  fastify.patch('/tasks/:id/done', apiLimit, async (request, reply) => {
    const taskId = parseInt(request.params.id, 10)
    if (!Number.isFinite(taskId)) return reply.code(400).send({ error: 'Invalid task id' })

    const task = db.prepare('SELECT id, completed_at FROM tasks WHERE id = ? AND user_id = ?')
      .get(taskId, request.user.id)
    if (!task) return reply.code(404).send({ error: 'Task not found' })

    const newCompletedAt = task.completed_at == null ? Date.now() : null
    db.prepare('UPDATE tasks SET completed_at = ? WHERE id = ?').run(newCompletedAt, taskId)

    return reply.send(getTaskWithTags(taskId, request.user.id))
  })

  // ── DELETE /api/v1/tasks/:id ──────────────────────────────────────────────
  // Permanently and immediately removes the task (and its tags via CASCADE).
  fastify.delete('/tasks/:id', apiLimit, async (request, reply) => {
    const taskId = parseInt(request.params.id, 10)
    if (!Number.isFinite(taskId)) return reply.code(400).send({ error: 'Invalid task id' })

    const result = db.prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?')
      .run(taskId, request.user.id)

    if (result.changes === 0) return reply.code(404).send({ error: 'Task not found' })

    return reply.code(204).send()
  })
}
