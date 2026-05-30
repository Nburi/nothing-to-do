'use strict'

const { createSessionHook } = require('../middleware/auth')
const { hashToken, generateToken } = require('../utils')

module.exports = async function settingsRoutes(fastify) {
  const db = fastify.db
  const requireSession = createSessionHook(db)

  // ── GET /settings/token ───────────────────────────────────────────────────
  // Returns whether the user currently has an active API token.
  // The raw token is never stored, so this only tells the client yes/no.
  fastify.get('/token', { preHandler: requireSession }, async (request, reply) => {
    const row = db.prepare('SELECT api_token_hash FROM users WHERE id = ?').get(request.user.id)
    return reply.send({ hasToken: row?.api_token_hash != null })
  })

  // ── POST /settings/token ──────────────────────────────────────────────────
  // Generates (or regenerates) the API token.
  // The raw token is returned exactly once — it cannot be recovered afterward.
  fastify.post('/token', { preHandler: requireSession }, async (request, reply) => {
    const rawToken = generateToken()
    db.prepare('UPDATE users SET api_token_hash = ? WHERE id = ?')
      .run(hashToken(rawToken), request.user.id)

    return reply.send({
      token: rawToken,
      note: 'Save this token now — it will not be shown again.'
    })
  })

  // ── DELETE /settings/token ────────────────────────────────────────────────
  // Revokes the API token, preventing any further API access until regenerated.
  fastify.delete('/token', { preHandler: requireSession }, async (request, reply) => {
    db.prepare('UPDATE users SET api_token_hash = NULL WHERE id = ?').run(request.user.id)
    return reply.send({ ok: true })
  })
}
