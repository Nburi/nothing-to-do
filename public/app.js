'use strict'

// ── Tag color palette ─────────────────────────────────────────────────────────
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
  editingId: null,   // task id currently in the edit modal
  activeTab: 'inbox', // mobile active tab
}

// ── View helpers ──────────────────────────────────────────────────────────────
function show(id)      { document.getElementById(id)?.classList.remove('hidden') }
function hide(id)      { document.getElementById(id)?.classList.add('hidden') }
function el(id)        { return document.getElementById(id) }
function setText(id, v) { const e = el(id); if (e) e.textContent = v }

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

// ── Sort tasks for display ────────────────────────────────────────────────────
function sortTasksForColumn(tasks) {
  const fourDaysMs = Date.now() + 4 * 86400000

  function priority(t) {
    if (t.is_important) return 0
    const dl = t.deadline ? new Date(t.deadline).getTime() : null
    if (dl !== null && dl <= fourDaysMs) return 1
    return 2
  }

  return [...tasks].sort((a, b) => {
    const pa = priority(a), pb = priority(b)
    if (pa !== pb) return pa - pb
    return a.created_at - b.created_at
  })
}

// ── Task card rendering ───────────────────────────────────────────────────────
function renderTaskCard(task, context = 'desktop') {
  const div = document.createElement('div')
  div.className = 'task-card'
    + (task.completed_at  ? ' is-done'      : '')
    + (task.is_important  ? ' is-important' : '')
  div.dataset.id = task.id

  // Swipe hint overlay (mobile)
  if (context === 'mobile') {
    const hint = document.createElement('div')
    hint.className = 'swipe-hint'
    div.appendChild(hint)
  }

  // Desktop: draggable
  if (context === 'desktop') {
    div.draggable = true
    div.addEventListener('dragstart', onDragStart)
    div.addEventListener('dragend',   onDragEnd)
  }

  // Check button → mark done (stops propagation so body click doesn't fire)
  const check = document.createElement('button')
  check.className = 'btn-check'
  check.type = 'button'
  check.setAttribute('aria-label', task.completed_at ? 'Mark active' : 'Mark done')
  check.innerHTML = task.completed_at ? '✓' : ''
  check.addEventListener('click', (e) => { e.stopPropagation(); toggleDone(task.id) })
  div.appendChild(check)

  // Body — click → toggle important
  const body = document.createElement('div')
  body.className = 'task-body'
  body.addEventListener('click', () => toggleImportant(task.id))

  const name = document.createElement('div')
  name.className = 'task-name'
  name.textContent = task.name
  body.appendChild(name)

  if (task.description) {
    const desc = document.createElement('div')
    desc.className = 'task-description'
    desc.textContent = task.description
    body.appendChild(desc)
  }

  // Tags + deadline row
  const hasTags = task.tags && task.tags.length > 0
  const dlInfo  = deadlineInfo(task.deadline)
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

  // 3-dots action button
  const actions = document.createElement('div')
  actions.className = 'task-actions'

  const dots = document.createElement('button')
  dots.className = 'btn-dots'
  dots.type = 'button'
  dots.setAttribute('aria-label', 'Task actions')
  dots.textContent = '···'
  dots.addEventListener('click', (e) => {
    e.stopPropagation()
    openTaskMenu(task, dots)
  })
  actions.appendChild(dots)
  div.appendChild(actions)

  // Mobile swipe
  if (context === 'mobile') {
    attachSwipeHandlers(div, task)
  }

  return div
}

// ── Task context menu ─────────────────────────────────────────────────────────
let _menuTaskId = null

function openTaskMenu(task, anchor) {
  closeTaskMenu()
  _menuTaskId = task.id

  const menu = el('task-menu')
  menu.classList.remove('hidden')

  // Wire up buttons for this task
  el('task-menu-edit').onclick = () => { closeTaskMenu(); openEditModal(task.id) }
  el('task-menu-delete').onclick = () => { closeTaskMenu(); deleteTask(task.id) }

  // Position below the anchor button
  const rect = anchor.getBoundingClientRect()
  const menuW = 140
  let left = rect.right - menuW
  let top  = rect.bottom + 4

  // Keep within viewport
  if (left < 8) left = 8
  if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8

  menu.style.top  = top + 'px'
  menu.style.left = left + 'px'
}

function closeTaskMenu() {
  el('task-menu')?.classList.add('hidden')
  _menuTaskId = null
}

// ── Desktop drag-and-drop ─────────────────────────────────────────────────────
let _dragTaskId = null

function onDragStart(e) {
  _dragTaskId = Number(e.currentTarget.dataset.id)
  e.currentTarget.classList.add('is-dragging')
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(_dragTaskId))
}

function onDragEnd(e) {
  e.currentTarget.classList.remove('is-dragging')
  _dragTaskId = null
}

function initDragAndDrop() {
  document.querySelectorAll('.column').forEach(col => {
    col.addEventListener('dragover', (e) => {
      e.preventDefault()
      e.dataTransfer.dropEffect = 'move'
      col.classList.add('drag-over')
    })
    col.addEventListener('dragleave', (e) => {
      if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over')
    })
    col.addEventListener('drop', async (e) => {
      e.preventDefault()
      col.classList.remove('drag-over')
      const taskId = Number(e.dataTransfer.getData('text/plain'))
      const newList = col.dataset.list
      if (taskId && newList) await moveToList(taskId, newList)
    })
  })
}

// ── Mobile swipe gestures ─────────────────────────────────────────────────────
function attachSwipeHandlers(cardEl, task) {
  let startX = 0, startY = 0

  cardEl.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX
    startY = e.touches[0].clientY
  }, { passive: true })

  cardEl.addEventListener('touchmove', (e) => {
    const dx = e.touches[0].clientX - startX
    const dy = e.touches[0].clientY - startY
    if (Math.abs(dy) > Math.abs(dx)) return
    if (Math.abs(dx) < 20) { cardEl.classList.remove('swipe-right', 'swipe-left'); return }

    const hint = cardEl.querySelector('.swipe-hint')
    if (dx > 0) {
      cardEl.classList.add('swipe-right')
      cardEl.classList.remove('swipe-left')
      if (hint) hint.textContent = task.list === 'inbox' ? '→ To Dos' : '→ Today'
    } else {
      cardEl.classList.add('swipe-left')
      cardEl.classList.remove('swipe-right')
      if (hint) hint.textContent = task.list === 'inbox' ? '← Tasks' : '← Deadline'
    }
  }, { passive: true })

  cardEl.addEventListener('touchend', async (e) => {
    cardEl.classList.remove('swipe-right', 'swipe-left')
    const dx = e.changedTouches[0].clientX - startX
    const dy = e.changedTouches[0].clientY - startY
    if (Math.abs(dy) > Math.abs(dx) || Math.abs(dx) < 60) return

    if (task.list === 'inbox') {
      if (dx > 0) await moveToList(task.id, 'todos')
      else        await moveToList(task.id, 'tasks')
    } else {
      if (dx > 0) await toggleToday(task.id)
      else        openDeadlinePicker(task)
    }
  }, { passive: true })
}

// ── Mobile nav tabs ───────────────────────────────────────────────────────────
function initMobileNav() {
  document.querySelectorAll('.mobile-nav-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const panel = tab.dataset.panel
      state.activeTab = panel

      document.querySelectorAll('.mobile-nav-tab').forEach(t => t.classList.remove('active'))
      tab.classList.add('active')

      document.querySelectorAll('.mobile-panel').forEach(p => p.classList.remove('active'))
      el(`panel-${panel}`)?.classList.add('active')
    })
  })
}

// ── Mobile quick-add ──────────────────────────────────────────────────────────
function initMobileQuickAdd() {
  document.querySelectorAll('.mobile-quick-add').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault()
      const input = form.querySelector('.mob-qa-name')
      const name  = input.value.trim()
      const list  = form.dataset.list
      if (!name) return
      const btn = form.querySelector('.btn-add')
      btn.disabled = true
      try {
        await createTask({ name, list })
        input.value = ''
      } catch (err) {
        alert(err.message ?? 'Failed to create task')
      } finally {
        btn.disabled = false
      }
    })
  })
}

// ── Mobile deadline picker ────────────────────────────────────────────────────
let _deadlineTaskId = null

function openDeadlinePicker(task) {
  _deadlineTaskId = task.id
  const input = el('deadline-picker-input')
  input.value = task.deadline ? task.deadline.slice(0, 10) : ''
  show('deadline-picker-overlay')
  show('deadline-picker')
  input.focus()
}

function closeDeadlinePicker() {
  hide('deadline-picker')
  hide('deadline-picker-overlay')
  _deadlineTaskId = null
}

function initDeadlinePicker() {
  el('deadline-picker-set').addEventListener('click', async () => {
    if (!_deadlineTaskId) return
    const val = el('deadline-picker-input').value
    await updateTask(_deadlineTaskId, { deadline: val || null })
    closeDeadlinePicker()
  })

  el('deadline-picker-clear').addEventListener('click', async () => {
    if (!_deadlineTaskId) return
    await updateTask(_deadlineTaskId, { deadline: null })
    closeDeadlinePicker()
  })

  el('deadline-picker-overlay').addEventListener('click', closeDeadlinePicker)
}

// ── Column & panel rendering ──────────────────────────────────────────────────
function renderColumn(listName, allActive, allDone) {
  const active = allActive.filter(t => t.list === listName)
  const done   = allDone.filter(t => t.list === listName)

  if (listName === 'todos' || listName === 'tasks') {
    const todaySection = el(`today-${listName}`)
    const todayListEl  = el(`today-list-${listName}`)
    const todayItems   = active.filter(t => t.is_today)
    todayListEl.innerHTML = ''
    sortTasksForColumn(todayItems).forEach(t => todayListEl.appendChild(renderTaskCard(t, 'desktop')))
    todaySection.classList.toggle('hidden', todayItems.length === 0)
  }

  const listEl  = el(`list-${listName}`)
  listEl.innerHTML = ''

  const mainItems = listName === 'inbox'
    ? active
    : active.filter(t => !t.is_today)

  const sorted = sortTasksForColumn(mainItems)

  if (sorted.length === 0 && done.length === 0) {
    const empty = document.createElement('div')
    empty.className = 'empty-state'
    empty.innerHTML = `<div class="empty-state-title">Nothing here.</div><div class="empty-state-sub">Add a task above.</div>`
    listEl.appendChild(empty)
  } else {
    sorted.forEach(t => listEl.appendChild(renderTaskCard(t, 'desktop')))
  }

  if (done.length > 0) {
    const doneLabel = document.createElement('div')
    doneLabel.className = 'section-label'
    doneLabel.textContent = 'Done today'
    listEl.appendChild(doneLabel)
    done.forEach(t => listEl.appendChild(renderTaskCard(t, 'desktop')))
  }
}

function renderMobilePanel(panelName, allActive, allDone) {
  const listEl = el(`mob-list-${panelName}`)
  if (!listEl) return
  listEl.innerHTML = ''

  let active, done
  if (panelName === 'all') {
    active = allActive
    done   = allDone
  } else {
    active = allActive.filter(t => t.list === panelName)
    done   = allDone.filter(t => t.list === panelName)
  }

  if (active.length === 0 && done.length === 0) {
    const empty = document.createElement('div')
    empty.className = 'empty-state'
    empty.innerHTML = `<div class="empty-state-title">Nothing here.</div><div class="empty-state-sub">Add a task above.</div>`
    listEl.appendChild(empty)
    return
  }

  sortTasksForColumn(active).forEach(t => listEl.appendChild(renderTaskCard(t, 'mobile')))

  if (done.length > 0) {
    const doneLabel = document.createElement('div')
    doneLabel.className = 'section-label'
    doneLabel.textContent = 'Done today'
    listEl.appendChild(doneLabel)
    done.forEach(t => listEl.appendChild(renderTaskCard(t, 'mobile')))
  }
}

// ── Main task list renderer ───────────────────────────────────────────────────
function renderTaskList() {
  const active = state.tasks.filter(t => t.completed_at == null)
  const done   = state.tasks.filter(t => t.completed_at != null)

  renderColumn('inbox', active, done)
  renderColumn('todos', active, done)
  renderColumn('tasks', active, done)

  renderMobilePanel('inbox', active, done)
  renderMobilePanel('todos', active, done)
  renderMobilePanel('tasks', active, done)
  renderMobilePanel('all',   active, done)
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
  renderTaskList()
  return task
}

async function toggleDone(taskId) {
  const task = await api.patch(`/api/v1/tasks/${taskId}/done`)
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = task
  renderTaskList()
}

async function toggleImportant(taskId) {
  const task = state.tasks.find(t => t.id === taskId)
  if (!task) return
  const updated = await api.patch(`/api/v1/tasks/${taskId}`, { is_important: !task.is_important })
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = updated
  renderTaskList()
}

async function toggleToday(taskId) {
  const task = state.tasks.find(t => t.id === taskId)
  if (!task) return
  const updated = await api.patch(`/api/v1/tasks/${taskId}`, { is_today: !task.is_today })
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = updated
  renderTaskList()
}

async function moveToList(taskId, newList) {
  const updated = await api.patch(`/api/v1/tasks/${taskId}`, { list: newList })
  const idx = state.tasks.findIndex(t => t.id === taskId)
  if (idx !== -1) state.tasks[idx] = updated
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

// ── Desktop quick-add form ────────────────────────────────────────────────────
function initDesktopQuickAdd() {
  const form = el('quick-add')
  if (!form) return

  form.addEventListener('submit', async (e) => {
    e.preventDefault()
    const nameInput = el('qa-name')
    const name = nameInput.value.trim()
    if (!name) { nameInput.focus(); return }

    const submitBtn = form.querySelector('.btn-add')
    submitBtn.disabled = true
    try {
      await createTask({ name, list: 'inbox' })
      nameInput.value = ''
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
  el('edit-name').value     = task.name
  el('edit-desc').value     = task.description ?? ''
  el('edit-tags').value     = (task.tags ?? []).join(', ')
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
  state.activeTab = 'inbox'
  showView('view-login')
  el('login-username').value = ''
  el('login-password').value = ''
})

// ── Close menu on outside click ───────────────────────────────────────────────
document.addEventListener('click', (e) => {
  if (!el('task-menu')?.classList.contains('hidden')) {
    if (!el('task-menu').contains(e.target)) closeTaskMenu()
  }
})

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeTaskMenu()
    closeDeadlinePicker()
  }
})

// ── Lifecycle ─────────────────────────────────────────────────────────────────
let _uiInitialized = false

async function onLoggedIn(user) {
  state.user = user
  setText('header-username', user.username)
  showView('view-tasks')
  await loadTasks()
  if (!_uiInitialized) {
    _uiInitialized = true
    initDesktopQuickAdd()
    initMobileQuickAdd()
    initMobileNav()
    initEditModal()
    initSettings()
    initDragAndDrop()
    initDeadlinePicker()
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
