'use strict'

const { test, describe, before, after } = require('node:test')
const assert = require('node:assert/strict')

const { buildApp } = require('../src/app')

describe('Authentication', () => {
  let app

  before(async () => {
    app = await buildApp({ dbPath: ':memory:', schedule: false, fastifyOpts: { logger: false } })
    await app.ready()
  })

  after(() => app.close())

  // ── Registration ───────────────────────────────────────────────────────────

  test('registers a new user and returns 201', async () => {
    const res = await app.inject({
      method: 'POST',
      url: '/auth/register',
      payload: { username: 'alice', password: 'password123' },
    })
    assert.equal(res.statusCode, 201)
    const body = JSON.parse(res.body)
    assert.equal(body.username, 'alice')
    assert.ok(typeof body.id === 'number')
  })

  test('rejects duplicate username with 409', async () => {
    await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'bob', password: 'password123' },
    })
    const res = await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'bob', password: 'password123' },
    })
    assert.equal(res.statusCode, 409)
  })

  test('rejects username shorter than 2 chars', async () => {
    const res = await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'x', password: 'password123' },
    })
    assert.equal(res.statusCode, 400)
  })

  test('rejects password shorter than 8 chars', async () => {
    const res = await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'charlie', password: 'short' },
    })
    assert.equal(res.statusCode, 400)
  })

  // ── Login ──────────────────────────────────────────────────────────────────

  test('logs in with correct credentials and sets a session cookie', async () => {
    await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'diana', password: 'password123' },
    })
    const res = await app.inject({
      method: 'POST', url: '/auth/login',
      payload: { username: 'diana', password: 'password123' },
    })
    assert.equal(res.statusCode, 200)
    assert.ok(res.cookies.find(c => c.name === 'ntd_sid'), 'session cookie should be set')
  })

  test('rejects wrong password with 401', async () => {
    await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'eve', password: 'password123' },
    })
    const res = await app.inject({
      method: 'POST', url: '/auth/login',
      payload: { username: 'eve', password: 'wrongpassword' },
    })
    assert.equal(res.statusCode, 401)
  })

  test('rejects non-existent user with 401', async () => {
    const res = await app.inject({
      method: 'POST', url: '/auth/login',
      payload: { username: 'nobody', password: 'password123' },
    })
    assert.equal(res.statusCode, 401)
  })

  // ── /auth/me ───────────────────────────────────────────────────────────────

  test('/auth/me returns 401 without a session', async () => {
    const res = await app.inject({ method: 'GET', url: '/auth/me' })
    assert.equal(res.statusCode, 401)
  })

  test('/auth/me returns the current user with a valid session', async () => {
    await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'frank', password: 'password123' },
    })
    const loginRes = await app.inject({
      method: 'POST', url: '/auth/login',
      payload: { username: 'frank', password: 'password123' },
    })
    const cookie = loginRes.cookies.find(c => c.name === 'ntd_sid')
    assert.ok(cookie)

    const meRes = await app.inject({
      method: 'GET', url: '/auth/me',
      headers: { cookie: `ntd_sid=${cookie.value}` },
    })
    assert.equal(meRes.statusCode, 200)
    assert.equal(JSON.parse(meRes.body).username, 'frank')
  })

  // ── Logout ─────────────────────────────────────────────────────────────────

  test('logout destroys the session', async () => {
    await app.inject({
      method: 'POST', url: '/auth/register',
      payload: { username: 'grace', password: 'password123' },
    })
    const loginRes = await app.inject({
      method: 'POST', url: '/auth/login',
      payload: { username: 'grace', password: 'password123' },
    })
    const cookie = loginRes.cookies.find(c => c.name === 'ntd_sid')

    await app.inject({
      method: 'POST', url: '/auth/logout',
      headers: { cookie: `ntd_sid=${cookie.value}` },
    })

    const meRes = await app.inject({
      method: 'GET', url: '/auth/me',
      headers: { cookie: `ntd_sid=${cookie.value}` },
    })
    assert.equal(meRes.statusCode, 401)
  })
})
