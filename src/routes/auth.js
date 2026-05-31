'use strict'

const { hash, verify } = require('@node-rs/argon2')

module.exports = async function authRoutes(fastify) {
  const db = fastify.db

  const findUser   = db.prepare('SELECT id, username, password_hash FROM users WHERE username = ?')
  const insertUser = db.prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')

  // ── POST /auth/register ───────────────────────────────────────────────────
  fastify.post('/register', {
    config: {
      rateLimit: {
        max: 10,
        timeWindow: '15 minutes',
        errorResponseBuilder: () => ({ error: 'Too many registration attempts. Please try again in 15 minutes.' })
      }
    }
  }, async (request, reply) => {
    const { username, password } = request.body ?? {}

    if (typeof username !== 'string' || username.trim().length < 2 || username.trim().length > 64) {
      return reply.code(400).send({ error: 'Username must be between 2 and 64 characters' })
    }
    if (typeof password !== 'string' || password.length < 8) {
      return reply.code(400).send({ error: 'Password must be at least 8 characters' })
    }

    const clean = username.trim()
    if (findUser.get(clean)) {
      return reply.code(409).send({ error: 'Username already taken' })
    }

    const passwordHash = await hash(password)
    const result = insertUser.run(clean, passwordHash)

    // Regenerate session ID to prevent session fixation: the pre-login session
    // ID is destroyed and a fresh one is issued before we store the user identity.
    const newUserId = Number(result.lastInsertRowid)
    await request.session.regenerate()
    request.session.userId = newUserId
    return reply.code(201).send({ id: newUserId, username: clean })
  })

  // ── POST /auth/login ──────────────────────────────────────────────────────
  fastify.post('/login', {
    config: {
      rateLimit: {
        max: 5,
        timeWindow: '15 minutes',
        errorResponseBuilder: () => ({ error: 'Too many login attempts. Please try again in 15 minutes.' })
      }
    }
  }, async (request, reply) => {
    const { username, password } = request.body ?? {}

    if (!username || !password) {
      return reply.code(400).send({ error: 'Username and password are required' })
    }

    const user = findUser.get(String(username).trim())
    if (!user) {
      // Hash a dummy value to keep response time consistent and prevent
      // timing-based username enumeration
      await hash('_timing_pad_')
      return reply.code(401).send({ error: 'Invalid username or password' })
    }

    const ok = await verify(user.password_hash, String(password))
    if (!ok) {
      return reply.code(401).send({ error: 'Invalid username or password' })
    }

    // Regenerate session ID to prevent session fixation.
    await request.session.regenerate()
    request.session.userId = user.id
    return reply.send({ id: user.id, username: user.username })
  })

  // ── POST /auth/logout ─────────────────────────────────────────────────────
  fastify.post('/logout', async (request, reply) => {
    if (request.session) {
      await request.session.destroy()
    }
    return reply.send({ ok: true })
  })

  // ── GET /auth/me ──────────────────────────────────────────────────────────
  // Returns the current user if a valid session exists; 401 otherwise.
  // The SPA calls this on load to decide which view to show.
  fastify.get('/me', async (request, reply) => {
    if (!request.session?.userId) {
      return reply.code(401).send({ error: 'Not logged in' })
    }
    const user = db.prepare('SELECT id, username FROM users WHERE id = ?').get(request.session.userId)
    if (!user) {
      await request.session.destroy()
      return reply.code(401).send({ error: 'Session invalid' })
    }
    return reply.send({ id: user.id, username: user.username })
  })
}
