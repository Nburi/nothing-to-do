'use strict'

const path = require('path')
const Fastify = require('fastify')

const { initDb, SqliteSessionStore } = require('./db')
const { scheduleNightlyPurge } = require('./scheduler')

/**
 * Builds and returns a fully configured Fastify application.
 *
 * @param {object} opts
 * @param {string}        [opts.dbPath]         Override the SQLite file path (use ':memory:' for tests)
 * @param {boolean}       [opts.schedule=true]  Whether to start the nightly purge scheduler
 * @param {object}        [opts.fastifyOpts]    Options forwarded to the Fastify constructor
 * @returns {Promise<FastifyInstance>}
 */
async function buildApp(opts = {}) {
  const {
    dbPath,
    schedule: enableSchedule = true,
    fastifyOpts = {}
  } = opts

  const fastify = Fastify(fastifyOpts)
  const db = initDb(dbPath)

  fastify.decorate('db', db)

  // ── Plugins ─────────────────────────────────────────────────────────────
  await fastify.register(require('@fastify/cookie'))

  await fastify.register(require('@fastify/session'), {
    secret: process.env.SESSION_SECRET || (function () {
      if (process.env.NODE_ENV === 'production') {
        throw new Error('SESSION_SECRET environment variable must be set in production')
      }
      return 'nothing-to-do-dev-secret-change-in-production!x#9'
    })(),
    cookieName: 'ntd_sid',
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      // Secure flag is set only in production (behind HTTPS).
      // In development this must be false so the cookie works over plain HTTP.
      secure: process.env.NODE_ENV === 'production',
      maxAge: 7 * 24 * 60 * 60 * 1000 // 7 days in ms
    },
    store: new SqliteSessionStore(db)
  })

  // Serve the public/ directory at /
  // index.html is served for GET / automatically (index option is on by default)
  await fastify.register(require('@fastify/static'), {
    root: path.join(__dirname, '..', 'public'),
    prefix: '/'
  })

  // Parse application/x-www-form-urlencoded bodies (login/register forms)
  await fastify.register(require('@fastify/formbody'))

  // Rate-limiting plugin; limits are configured per-route via config.rateLimit
  await fastify.register(require('@fastify/rate-limit'), { global: false })

  // ── Routes ───────────────────────────────────────────────────────────────
  await fastify.register(require('./routes/auth'),     { prefix: '/auth' })
  await fastify.register(require('./routes/settings'), { prefix: '/settings' })
  await fastify.register(require('./api/v1'),          { prefix: '/api/v1' })

  // ── Scheduler ────────────────────────────────────────────────────────────
  if (enableSchedule) {
    scheduleNightlyPurge(db)
  }

  return fastify
}

module.exports = { buildApp }
