'use strict'

const crypto = require('crypto')

/**
 * Returns a SHA-256 hex digest of the given token.
 * Used to store API tokens at rest — the raw token is never persisted.
 */
function hashToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex')
}

/**
 * Generates a new random API token with a recognizable "ntd_" prefix.
 * The prefix helps identify leaked tokens in logs or pastes.
 */
function generateToken() {
  return 'ntd_' + crypto.randomBytes(32).toString('hex')
}

module.exports = { hashToken, generateToken }
