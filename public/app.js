'use strict'

// ── Tag color palette ─────────────────────────────────────────────────────────
// Each entry is [background, text]. Color is picked deterministically from tag name.
const TAG_PALETTE = [
  ['#dbeafe', '#1e40af'],
  ['#dcfce7', '#166534'],
  ['#fef3c7', '#92400e'],
  ['#fce7f3', '#9d174d'],
  ['#e0e7ff', '#3730a3'],
  ['#f3e8ff', '#7e22ce'],
  ['#ffedd5', '#9a3412'],
  ['#cffafe', '#155e75'],
  ['#d1fae5', '#065f46'],
  ['#fdf2f8', '#86198f'],
]

function tagColors(tag) {
  let h = 0
  for (let i = 0; i < tag.length; i++) h = (h * 31 + tag.charCodeAt(i)) >>> 0
  return TAG_PALETTE[h % TAG_PALETTE.length]
}

// ── Lightweight API client ────────────────────────────────────────────────────
const api = {
  async request(method, path, body) {
    const opts = { method, headers: {} }
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json'
      opts.body = JSON.stringify(body)
    }
    const res = await fetch(path, opts)
    if (res.status === 204) return null
    const data = await res.json()
    if (!res.ok) throw Object.assign(new Error(data.error ?? 'Request failed'), { status: res.status, data })
    return data
  },
  get:    (p)    => api.request('GET',    p),
  post:   (p, b) => api.request('POST',   p, b),
  patch:  (p, b) => api.request('PATCH',  p, b),
  delete: (p)    => api.request('DELETE', p),
}

// ── Application state ─────────────────────────────────────────────────────────
const state = {
  user:      null,   // { id, username }
  tasks:     [],     // full task list from server
  activeTag: null,   // string tag currently filtering, or null
  editingId: null,   // task id currently in the edit modal
}

// ── View helpers ──────────────────────────────────────────────────────────────
function show(id)     { document.getElementById(id)?.classList.remove('hidden') }
function hide(id)     { document.getElementById(id)?.classList.add('hidden') }
function el(id)       { return document.getElementById(id) }
function setText(id, v){ const e = el(id); if (e) e.textContent = v }

function showError(id, msg) {
  const e = el(id)
  if (!e) return
  e.textContent = msg
  e.classList.remove('hidden')
}
function clearError(id) { el(id)?.classList.add('hidden') }

function showView(viewId) {
  for (const id of ['view-login', 'view-register', 'view-tasks', 'view-settings']) {
    document.getElementById(id)?.classList.toggle('hidden', id !== viewId)
  }
}

// ── Deadline formatting ───────────────────────────────────────────────────────
function deadlineInfo(deadline) {
  if (!deadline) return null
  const d = new Date(deadline)
  if (isNaN(d)) return null

  const now       = new Date()
  const todayMs   = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const dMs       = new Date(d.getFullYear(),   d.getMonth(),   d.getDate()).getTime()
  const isDateOnly = !/[T ]/.test(deadline)

  if (dMs < todayMs) {
    return { text: 'Overdue', cls: 'deadline-overdue' }
  }
  if (dMs === todayMs) {
    const t = isDateOnly ? '' : ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    return { text: 'Today' + t, cls: 'deadline-today' }
  }
  const tomorrowMs = todayMs + 86400000
  if (dMs === tomorrowMs) {
    return { text: 'Tomorrow', cls: 'deadline-tomorrow' }
  }
  const days = Math.round((dMs - todayMs) / 86400000)
  if (days <= 7) {
    return { text: d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }), cls: 'deadline-soon' }
  }
  const yr = d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
  return { text: d.toLocaleDateString([], { month: 'short', day: 'numeric', year: yr }), cls: 'deadline-future' }
}

// ── Task card rendering ───────────────────────────────────────────────────────
function renderTaskCard(task) {
  const div = document.createElement('div')
  div.className = 'task-card' + (task.completed_at ? ' is-done' : '')
  div.dataset.id = task.id

  // Check button
  const check = document.createElement('button')
  check.className = 'btn-check'
  check.type = 'button'
  check.setAttribute('aria-label', task.completed_at ? 'Mark active' : 'Mark done')
  check.innerHTML = task.completed_at ? '✓' : ''
  check.addEventListener('click', () => toggleDone(task.id))
  div.appendChild(check)

  // Body
  const body = document.createElement('div')
  body.className = 'task-body'

  const name = document.createElement('div')
  name.className = 'task-name'
  name.textContent = task.name
  name.title = 'Click to edit'
  name.addEventListener('click', () => openEditModal(task.id))
  body.appendChild(name)

  if (task.description) {
    const desc = document.createElement('div')
    desc.className = 'task-description'
    desc.textContent = task.description
    body.appendChild(desc)
  }

  // Tags + deadline row
  const hasTags     = task.tags && task.tags.length > 0
  const dlInfo      = deadlineInfo(task.deadline)
  if (hasTags || dlInfo) {
    const meta = document.createElement('div')
    meta.className = 'task-meta'

    for (const tag of (task.tags ?? [])) {
      const [bg, color] = tagColors(tag)
      const pill = document.createElement('span')
      pill.className = 'tag-pill'
      pill.style.background = bg
      pill.style.color = color
      pill.textContent = tag
      pill.addEventListener('click', (e) => { e.stopPropagation(); filterByTag(tag) })
      meta.appendChild(pill)
    }

    if (dlInfo) {
      const badge = document.createElement('span')
      badge.className = `deadline-badge ${dlInfo.cls}`
      badge.textContent = dlInfo.text
      meta.appendChild(badge)
    }
    body.appendChild(meta)
  }
  div.appendChild(body)

  // Action buttons (edit via name click; delete here)
  const actions = document.createElement('div')
  actions.className = 'task-actions'

  const del = document.createElement('button')
  del.className = 'btn-task-action btn-task-delete'
  del.type = 'button'
  del.setAttribute('aria-label', 'Delete task')
  del.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>`
  del.addEventListener('click', () => deleteTask(task.id))
  actions.appendChild(del)
  div.appendChild(actions)

  return div
}

// ── Tag filter bar ────────────────────────────────────────────────────────────
function renderTagFilters(tasks) {
  const allTags = [...new Set(tasks.flatMap(t => t.tags ?? []))].sort()
  const container = el('tag-filters')

  if (allTags.length === 0) {
    container.classList.add('hidden')
    return
  }

  container.innerHTML = ''
  container.classList.remove('hidden')

  // "All" pill
  const allPill = document.createElement('span')
  allPill.className = 'tag-filter-pill' + (state.activeTag === null ? ' active' : '')
  allPill.textContent = 'All'
  allPill.addEventListener('click', () => filterByTag(null))
  container.appendChild(allPill)

  for (const tag of allTags) {
    const [bg, color] = tagColors(tag)
    const pill = document.createElement('span')
    pill.className = 'tag-filter-pill' + (state.activeTag === tag ? ' active' : '')
    pill.style.background = bg
    pill.style.color = color
    pill.textContent = tag
    pill.addEventListener('click', () => filterByTag(tag))
    container.appendChild(pill)
  }
}

// ── Main task list renderer ───────────────────────────────────────────────────
function renderTaskList() {
  const filtered = state.activeTag
    ? state.tasks.filter(t => t.tags?.includes(state.activeTag))
    : state.tasks

  const active    = filtered.filter(t => t.completed_at == null)
  const done      = filtered.filter(t => t.completed_at != null)

  const taskListEl = el('task-list')
  const doneListEl = el('done-list')
  taskListEl.innerHTML = ''
  doneListEl.innerHTML = ''

  renderTagFilters(state.tasks)

  if (active.length === 0 && done.length === 0) {
    const emptyEl = document.createElement('div')
    emptyEl.className = 'empty-state'
    emptyEl.innerHTML = `
      <div class="empty-state-title">Nothing to do.</div>
      <div class="empty-state-sub">Add a task above to get started.</div>
    `
    taskListEl.appendChild(emptyEl)
  } else {
    for (const task of active) taskListEl.appendChild(renderTaskCard(task))
  }

  if (done.length > 0) {
    el('done-section').classList.remove('hidden')
    for (const task of done) doneListEl.appendChild(renderTaskCard(task))
  } else {
    el('done-section').classList.add('hidden')
  }
}

// ── Data actions ──────────────────────────────────────────────────────────────
async function loadTasks() {
  try {
    const data = await api.get('/api/v1/tasks')
    state.tasks = data.tasks
    renderTaskList()
  } catch (err) {
    console.error('Failed to load tasks:', err)
  }
}

async function createTask(fields) {
  const task = await api.post('/api/v1/tasks', fields)
  state.tasks.unshift(task)
  // Re-sort: active first by created_at, then done
  state.tasks.sort((a, b) => {
    const aDone = a.completed_at != null
    const bDone = b.completed_at != null
    if (aDone !== bDone) return aDone ? 1 : -1
    return a.created_at - b.created_at
  })
  renderTaskList()
  return task
}

async function toggleDone(taskId) {
  const task = await api.patch(`/api/v1/tasks/${taskId}/done`)
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = task
  renderTaskList()
}

async function deleteTask(taskId) {
  await api.delete(`/api/v1/tasks/${taskId}`)
  state.tasks = state.tasks.filter(t => t.id !== taskId)
  renderTaskList()
}

async function updateTask(taskId, fields) {
  const task = await api.patch(`/api/v1/tasks/${taskId}`, fields)
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = task
  renderTaskList()
  return task
}

function filterByTag(tag) {
  state.activeTag = tag === state.activeTag ? null : tag
  renderTaskList()
}

// ── Quick-add form ────────────────────────────────────────────────────────────
function initQuickAdd() {
  const nameInput = el('qa-name')
  const details   = el('qa-details')
  const form      = el('quick-add')

  // Expand to show detail fields when the user focuses the name input
  nameInput.addEventListener('focus', () => details.classList.remove('hidden'))

  // Collapse if user clicks outside the form and the name is empty
  document.addEventListener('click', (e) => {
    if (!form.contains(e.target) && !nameInput.value.trim()) {
      details.classList.add('hidden')
    }
  })

  form.addEventListener('submit', async (e) => {
    e.preventDefault()
    const name = nameInput.value.trim()
    if (!name) { nameInput.focus(); return }

    const desc     = el('qa-desc').value.trim()
    const tagsRaw  = el('qa-tags').value.trim()
    const deadline = el('qa-deadline').value

    const submitBtn = form.querySelector('.btn-add')
    submitBtn.disabled = true
    try {
      await createTask({
        name,
        description: desc || undefined,
        tags:        tagsRaw ? tagsRaw.split(',').map(t => t.trim()).filter(Boolean) : undefined,
        deadline:    deadline || undefined,
      })
      // Reset form
      nameInput.value = ''
      el('qa-desc').value = ''
      el('qa-tags').value = ''
      el('qa-deadline').value = ''
      details.classList.add('hidden')
    } catch (err) {
      alert(err.message ?? 'Failed to create task')
    } finally {
      submitBtn.disabled = false
    }
  })
}

// ── Edit modal ────────────────────────────────────────────────────────────────
function openEditModal(taskId) {
  const task = state.tasks.find(t => t.id === taskId)
  if (!task) return

  state.editingId = taskId
  el('edit-name').value    = task.name
  el('edit-desc').value    = task.description ?? ''
  el('edit-tags').value    = (task.tags ?? []).join(', ')
  el('edit-deadline').value = task.deadline
    ? (task.deadline.length === 10 ? task.deadline + 'T00:00' : task.deadline.slice(0, 16))
    : ''

  clearError('edit-error')
  show('edit-modal')
  el('edit-name').focus()
}

function closeEditModal() {
  hide('edit-modal')
  state.editingId = null
}

function initEditModal() {
  el('edit-cancel').addEventListener('click', closeEditModal)

  el('edit-modal').addEventListener('click', (e) => {
    if (e.target === el('edit-modal')) closeEditModal()
  })

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !el('edit-modal').classList.contains('hidden')) {
      closeEditModal()
    }
  })

  el('edit-save').addEventListener('click', async () => {
    const taskId = state.editingId
    if (!taskId) return

    const name = el('edit-name').value.trim()
    if (!name) { showError('edit-error', 'Task name is required'); return }

    const tagsRaw  = el('edit-tags').value.trim()
    const deadline = el('edit-deadline').value

    el('edit-save').disabled = true
    clearError('edit-error')
    try {
      await updateTask(taskId, {
        name,
        description: el('edit-desc').value.trim() || null,
        tags:        tagsRaw ? tagsRaw.split(',').map(t => t.trim()).filter(Boolean) : [],
        deadline:    deadline || null,
      })
      closeEditModal()
    } catch (err) {
      showError('edit-error', err.message ?? 'Failed to save task')
    } finally {
      el('edit-save').disabled = false
    }
  })
}

// ── Settings view ─────────────────────────────────────────────────────────────
async function initSettings() {
  el('btn-settings').addEventListener('click', showSettings)
  el('btn-back').addEventListener('click', () => showView('view-tasks'))
}

async function showSettings() {
  showView('view-settings')
  await refreshTokenStatus()
}

async function refreshTokenStatus() {
  try {
    const data = await api.get('/settings/token')
    renderTokenStatus(data.hasToken, null)
  } catch (err) {
    el('token-display-area').textContent = 'Could not load token status.'
  }
}

function renderTokenStatus(hasToken, newToken) {
  const area = el('token-display-area')
  area.innerHTML = ''

  if (newToken) {
    const warning = document.createElement('div')
    warning.className = 'token-warning'
    warning.textContent = '⚠ Copy this token now — it will not be shown again.'
    area.appendChild(warning)

    const display = document.createElement('div')
    display.className = 'token-display'
    display.textContent = newToken
    area.appendChild(display)

    const copyBtn = document.createElement('button')
    copyBtn.className = 'btn-secondary'
    copyBtn.style.marginBottom = '12px'
    copyBtn.textContent = 'Copy token'
    copyBtn.addEventListener('click', async () => {
      await navigator.clipboard.writeText(newToken).catch(() => {})
      copyBtn.textContent = 'Copied!'
      setTimeout(() => { copyBtn.textContent = 'Copy token' }, 2000)
    })
    area.appendChild(copyBtn)
  } else if (hasToken) {
    const status = document.createElement('p')
    status.style.marginBottom = '14px'
    status.textContent = 'An API token is active.'
    area.appendChild(status)
  } else {
    const status = document.createElement('p')
    status.style.marginBottom = '14px'
    status.textContent = 'No token set. Generate one to use the REST API.'
    area.appendChild(status)
  }

  el('btn-gen-token').textContent = hasToken ? 'Regenerate token' : 'Generate token'
  el('btn-revoke-token')?.classList.toggle('hidden', !hasToken)
}

el('btn-gen-token').addEventListener('click', async () => {
  const isRegen = el('btn-gen-token').textContent.startsWith('Regen')
  if (isRegen) {
    // Show inline confirmation instead of a blocking dialog
    el('btn-gen-token').textContent = 'Confirm — invalidates current token'
    el('btn-gen-token').dataset.confirming = '1'
    setTimeout(() => {
      if (el('btn-gen-token').dataset.confirming) {
        el('btn-gen-token').textContent = 'Regenerate token'
        delete el('btn-gen-token').dataset.confirming
      }
    }, 4000)
    return
  }
  if (el('btn-gen-token').dataset.confirming) {
    delete el('btn-gen-token').dataset.confirming
  }

  try {
    const data = await api.post('/settings/token')
    renderTokenStatus(true, data.token)
  } catch (err) {
    el('btn-gen-token').textContent = isRegen ? 'Regenerate token' : 'Generate token'
  }
})

el('btn-revoke-token')?.addEventListener('click', async () => {
  const btn = el('btn-revoke-token')
  if (!btn.dataset.confirming) {
    btn.textContent = 'Confirm revoke'
    btn.dataset.confirming = '1'
    setTimeout(() => {
      if (btn.dataset.confirming) {
        btn.textContent = 'Revoke token'
        delete btn.dataset.confirming
      }
    }, 4000)
    return
  }
  delete btn.dataset.confirming
  try {
    await api.delete('/settings/token')
    renderTokenStatus(false, null)
  } catch (err) {
    btn.textContent = 'Revoke token'
  }
})

// ── Auth forms ────────────────────────────────────────────────────────────────
el('goto-register').addEventListener('click', (e) => { e.preventDefault(); showView('view-register') })
el('goto-login').addEventListener('click',    (e) => { e.preventDefault(); showView('view-login') })

el('form-login').addEventListener('submit', async (e) => {
  e.preventDefault()
  clearError('login-error')
  const btn = el('login-submit')
  btn.disabled = true
  try {
    const data = await api.post('/auth/login', {
      username: el('login-username').value,
      password: el('login-password').value,
    })
    await onLoggedIn(data)
  } catch (err) {
    showError('login-error', err.message ?? 'Login failed')
  } finally {
    btn.disabled = false
  }
})

el('form-register').addEventListener('submit', async (e) => {
  e.preventDefault()
  clearError('register-error')

  const pw  = el('reg-password').value
  const pw2 = el('reg-confirm').value
  if (pw !== pw2) { showError('register-error', 'Passwords do not match'); return }

  const btn = el('register-submit')
  btn.disabled = true
  try {
    const data = await api.post('/auth/register', {
      username: el('reg-username').value,
      password: pw,
    })
    await onLoggedIn(data)
  } catch (err) {
    showError('register-error', err.message ?? 'Registration failed')
  } finally {
    btn.disabled = false
  }
})

el('btn-logout').addEventListener('click', async () => {
  await api.post('/auth/logout').catch(() => {})
  state.user  = null
  state.tasks = []
  state.activeTag = null
  showView('view-login')
  el('login-username').value = ''
  el('login-password').value = ''
})

// ── Lifecycle ─────────────────────────────────────────────────────────────────
let _uiInitialized = false

async function onLoggedIn(user) {
  state.user = user
  setText('header-username', user.username)
  showView('view-tasks')
  await loadTasks()
  // Guard against duplicate listener registration on logout + re-login
  if (!_uiInitialized) {
    _uiInitialized = true
    initQuickAdd()
    initEditModal()
    initSettings()
  }
}

async function init() {
  try {
    const user = await api.get('/auth/me')
    await onLoggedIn(user)
  } catch (err) {
    if (err.status === 401) {
      showView('view-login')
    } else {
      showView('view-login')
      console.error('Unexpected error during init:', err)
    }
  }
}

init()
