'use strict'

const { hashToken } = require('../utils')

/**
 * Creates a Fastify preHandler hook that accepts EITHER:
 *   - An Authorization: Bearer <token> header  (Apple Shortcuts, API clients)
 *   - A valid session cookie                   (web UI)
 *
 * On success, `request.user = { id, username }` is set.
 * On failure, replies 401 immediately.
 *
 * Sharing one auth layer for both web and API keeps all task logic in a single
 * set of endpoints, so there is no drift between what the browser can do and
 * what Shortcuts can do.
 */
function createAuthHook(db) {
  const byTokenHash = db.prepare('SELECT id, username FROM users WHERE api_token_hash = ?')
  const byId        = db.prepare('SELECT id, username FROM users WHERE id = ?')

  return async function authenticate(request, reply) {
    // ── 1. Bearer token ──────────────────────────────────────────────────────
    const authHeader = request.headers.authorization
    if (authHeader?.startsWith('Bearer ')) {
      const rawToken = authHeader.slice(7).trim()
      if (rawToken.length > 0) {
        const user = byTokenHash.get(hashToken(rawToken))
        if (user) {
          request.user = user
          return
        }
      }
      // A Bearer header was present but invalid — reject, don't fall through to session.
      // This prevents accidental session-based bypass when a token is provided.
      return reply.code(401).send({ error: 'Invalid or revoked API token' })
    }

    // ── 2. Session cookie ────────────────────────────────────────────────────
    if (request.session?.userId) {
      const user = byId.get(request.session.userId)
      if (user) {
        request.user = user
        return
      }
      // Session points to a deleted account — destroy it to avoid lingering state
      await request.session.destroy()
    }

    reply.code(401).send({ error: 'Authentication required' })
  }
}

/**
 * Simpler hook for settings routes that only accept session auth (not tokens).
 * Used so that API token management is only reachable from the browser UI.
 */
function createSessionHook(db) {
  const byId = db.prepare('SELECT id, username FROM users WHERE id = ?')

  return async function requireSession(request, reply) {
    if (!request.session?.userId) {
      return reply.code(401).send({ error: 'Login required' })
    }
    const user = byId.get(request.session.userId)
    if (!user) {
      await request.session.destroy()
      return reply.code(401).send({ error: 'Session invalid' })
    }
    request.user = user
  }
}

module.exports = { createAuthHook, createSessionHook }
