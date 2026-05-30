'use strict'

const schedule = require('node-schedule')

/**
 * Returns the UTC timestamp (ms) of the most recent midnight in Europe/Zurich.
 *
 * Approach: ask Intl how many hours/minutes/seconds have elapsed since midnight
 * in the Zurich timezone, then subtract that duration from "now". This correctly
 * handles both CET (UTC+1) and CEST (UTC+2) without any hardcoded offsets.
 */
function getStartOfTodayZurich() {
  const now = new Date()
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Europe/Zurich',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  }).formatToParts(now)

  const hour   = parseInt(parts.find(p => p.type === 'hour').value,   10) % 24
  const minute = parseInt(parts.find(p => p.type === 'minute').value, 10)
  const second = parseInt(parts.find(p => p.type === 'second').value, 10)

  return now.getTime() - (hour * 3600 + minute * 60 + second) * 1000
}

/**
 * Hard-deletes all tasks that were completed before the start of today
 * (Europe/Zurich time).  Safe to call multiple times — idempotent.
 *
 * @param {DatabaseSync} db
 * @returns {number} number of tasks deleted
 */
function purgeCompletedTasks(db) {
  const startOfTodayMs = getStartOfTodayZurich()
  const result = db
    .prepare('DELETE FROM tasks WHERE completed_at IS NOT NULL AND completed_at < ?')
    .run(startOfTodayMs)
  return result.changes
}

/**
 * Wires up the nightly purge job and runs a catch-up pass immediately.
 *
 * The catch-up pass handles the case where the server was offline at midnight:
 * on next startup it will still delete tasks completed on previous days.
 *
 * @param {DatabaseSync} db
 */
function scheduleNightlyPurge(db) {
  // Catch-up: delete anything that should have been purged while we were down
  const startupCount = purgeCompletedTasks(db)
  if (startupCount > 0) {
    console.log(`[scheduler] startup catch-up: purged ${startupCount} task(s) completed before today`)
  }

  // Schedule the nightly run at 00:00 Europe/Zurich
  schedule.scheduleJob({ rule: '0 0 * * *', tz: 'Europe/Zurich' }, () => {
    const count = purgeCompletedTasks(db)
    console.log(`[scheduler] nightly purge: removed ${count} completed task(s)`)
  })
}

module.exports = { scheduleNightlyPurge, purgeCompletedTasks, getStartOfTodayZurich }
