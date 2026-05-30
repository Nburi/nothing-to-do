'use strict'

const { test, describe, before, after, beforeEach } = require('node:test')
const assert = require('node:assert/strict')

const { buildApp } = require('../src/app')

// ── Helpers ───────────────────────────────────────────────────────────────────

async function register(app, username, password = 'password123') {
  const res = await app.inject({
    method: 'POST', url: '/auth/register',
    payload: { username, password },
  })
  assert.equal(res.statusCode, 201, `register ${username} failed: ${res.body}`)
  const cookie = res.cookies.find(c => c.name === 'ntd_sid')
  return { user: JSON.parse(res.body), cookieHeader: `ntd_sid=${cookie.value}` }
}

async function makeTask(app, cookieHeader, fields = {}) {
  const res = await app.inject({
    method: 'POST', url: '/api/v1/tasks',
    headers: { cookie: cookieHeader },
    payload: { name: 'Test task', ...fields },
  })
  assert.equal(res.statusCode, 201, `createTask failed: ${res.body}`)
  return JSON.parse(res.body)
}

// ── Task CRUD ─────────────────────────────────────────────────────────────────

describe('Task CRUD', () => {
  let app, session

  before(async () => {
    app = await buildApp({ dbPath: ':memory:', schedule: false, fastifyOpts: { logger: false } })
    await app.ready()
    session = await register(app, 'taskuser')
  })

  after(() => app.close())

  test('creates a task and returns 201 with all fields', async () => {
    const res = await app.inject({
      method: 'POST', url: '/api/v1/tasks',
      headers: { cookie: session.cookieHeader },
      payload: { name: 'Buy milk', description: 'Full fat', tags: ['shopping'], deadline: '2099-12-31' },
    })
    assert.equal(res.statusCode, 201)
    const task = JSON.parse(res.body)
    assert.equal(task.name, 'Buy milk')
    assert.equal(task.description, 'Full fat')
    assert.deepEqual(task.tags, ['shopping'])
    assert.equal(task.deadline, '2099-12-31')
    assert.equal(task.completed_at, null)
    assert.ok(typeof task.id === 'number')
  })

  test('rejects task with empty name', async () => {
    const res = await app.inject({
      method: 'POST', url: '/api/v1/tasks',
      headers: { cookie: session.cookieHeader },
      payload: { name: '   ' },
    })
    assert.equal(res.statusCode, 400)
    assert.match(JSON.parse(res.body).error, /required|empty/i)
  })

  test('rejects task with no name field', async () => {
    const res = await app.inject({
      method: 'POST', url: '/api/v1/tasks',
      headers: { cookie: session.cookieHeader },
      payload: {},
    })
    assert.equal(res.statusCode, 400)
  })

  test('lists tasks', async () => {
    const t = await makeTask(app, session.cookieHeader, { name: 'Listed task' })
    const res = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(res.statusCode, 200)
    const { tasks } = JSON.parse(res.body)
    assert.ok(Array.isArray(tasks))
    assert.ok(tasks.some(tk => tk.id === t.id))
  })

  test('updates task name and tags', async () => {
    const t = await makeTask(app, session.cookieHeader, { name: 'Original', tags: ['old'] })
    const res = await app.inject({
      method: 'PATCH', url: `/api/v1/tasks/${t.id}`,
      headers: { cookie: session.cookieHeader },
      payload: { name: 'Updated', tags: ['new1', 'new2'] },
    })
    assert.equal(res.statusCode, 200)
    const updated = JSON.parse(res.body)
    assert.equal(updated.name, 'Updated')
    assert.deepEqual(updated.tags.sort(), ['new1', 'new2'])
  })

  test('marks task as done (toggle)', async () => {
    const t = await makeTask(app, session.cookieHeader)
    assert.equal(t.completed_at, null)

    const doneRes = await app.inject({
      method: 'PATCH', url: `/api/v1/tasks/${t.id}/done`,
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(doneRes.statusCode, 200)
    const done = JSON.parse(doneRes.body)
    assert.ok(done.completed_at !== null)

    // Toggle back
    const activeRes = await app.inject({
      method: 'PATCH', url: `/api/v1/tasks/${t.id}/done`,
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(activeRes.statusCode, 200)
    assert.equal(JSON.parse(activeRes.body).completed_at, null)
  })

  test('deletes task immediately and returns 204', async () => {
    const t = await makeTask(app, session.cookieHeader)

    const delRes = await app.inject({
      method: 'DELETE', url: `/api/v1/tasks/${t.id}`,
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(delRes.statusCode, 204)

    // Task should no longer appear in list
    const listRes = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { cookie: session.cookieHeader },
    })
    const { tasks } = JSON.parse(listRes.body)
    assert.ok(!tasks.some(tk => tk.id === t.id))
  })

  test('returns 404 for non-existent task', async () => {
    const res = await app.inject({
      method: 'DELETE', url: '/api/v1/tasks/9999999',
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(res.statusCode, 404)
  })
})

// ── Multi-user isolation ──────────────────────────────────────────────────────

describe('Multi-user isolation', () => {
  let app, alice, bob

  before(async () => {
    app = await buildApp({ dbPath: ':memory:', schedule: false, fastifyOpts: { logger: false } })
    await app.ready()
    alice = await register(app, 'alice_iso')
    bob   = await register(app, 'bob_iso')
  })

  after(() => app.close())

  test('alice cannot read bob\'s tasks', async () => {
    const bobTask = await makeTask(app, bob.cookieHeader, { name: 'Bob private' })

    const aliceList = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { cookie: alice.cookieHeader },
    })
    const { tasks } = JSON.parse(aliceList.body)
    assert.ok(!tasks.some(t => t.id === bobTask.id), 'Alice should not see Bob\'s task')
  })

  test('alice cannot delete bob\'s task', async () => {
    const bobTask = await makeTask(app, bob.cookieHeader, { name: 'Bob protected' })

    const res = await app.inject({
      method: 'DELETE', url: `/api/v1/tasks/${bobTask.id}`,
      headers: { cookie: alice.cookieHeader },
    })
    assert.equal(res.statusCode, 404, 'Cross-user delete should return 404')

    // Task still exists for bob
    const bobList = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { cookie: bob.cookieHeader },
    })
    assert.ok(JSON.parse(bobList.body).tasks.some(t => t.id === bobTask.id))
  })

  test('alice cannot mark bob\'s task done', async () => {
    const bobTask = await makeTask(app, bob.cookieHeader, { name: 'Bob untouchable' })
    const res = await app.inject({
      method: 'PATCH', url: `/api/v1/tasks/${bobTask.id}/done`,
      headers: { cookie: alice.cookieHeader },
    })
    assert.equal(res.statusCode, 404)
  })

  test('alice cannot update bob\'s task', async () => {
    const bobTask = await makeTask(app, bob.cookieHeader, { name: 'Bob immutable' })
    const res = await app.inject({
      method: 'PATCH', url: `/api/v1/tasks/${bobTask.id}`,
      headers: { cookie: alice.cookieHeader },
      payload: { name: 'Hacked' },
    })
    assert.equal(res.statusCode, 404)
  })
})

// ── API token auth ────────────────────────────────────────────────────────────

describe('API token authentication', () => {
  let app, session, token

  before(async () => {
    app = await buildApp({ dbPath: ':memory:', schedule: false, fastifyOpts: { logger: false } })
    await app.ready()
    session = await register(app, 'tokenuser')

    // Generate a token
    const res = await app.inject({
      method: 'POST', url: '/settings/token',
      headers: { cookie: session.cookieHeader },
    })
    assert.equal(res.statusCode, 200)
    token = JSON.parse(res.body).token
    assert.ok(token.startsWith('ntd_'))
  })

  after(() => app.close())

  test('Bearer token allows creating a task', async () => {
    const res = await app.inject({
      method: 'POST', url: '/api/v1/tasks',
      headers: { authorization: `Bearer ${token}` },
      payload: { name: 'Via token' },
    })
    assert.equal(res.statusCode, 201)
    assert.equal(JSON.parse(res.body).name, 'Via token')
  })

  test('Bearer token allows listing tasks', async () => {
    const res = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { authorization: `Bearer ${token}` },
    })
    assert.equal(res.statusCode, 200)
    assert.ok(Array.isArray(JSON.parse(res.body).tasks))
  })

  test('invalid Bearer token is rejected with 401', async () => {
    const res = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { authorization: 'Bearer ntd_notavalidtoken' },
    })
    assert.equal(res.statusCode, 401)
  })

  test('missing auth is rejected with 401', async () => {
    const res = await app.inject({ method: 'GET', url: '/api/v1/tasks' })
    assert.equal(res.statusCode, 401)
  })

  test('revoking the token prevents further access', async () => {
    await app.inject({
      method: 'DELETE', url: '/settings/token',
      headers: { cookie: session.cookieHeader },
    })
    const res = await app.inject({
      method: 'GET', url: '/api/v1/tasks',
      headers: { authorization: `Bearer ${token}` },
    })
    assert.equal(res.statusCode, 401)
  })
})
