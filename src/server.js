'use strict'

require('dotenv').config()

const { buildApp } = require('./app')

const PORT = parseInt(process.env.PORT ?? '3000', 10)
const HOST = process.env.HOST ?? '127.0.0.1'

buildApp({
  fastifyOpts: {
    logger: {
      level: process.env.LOG_LEVEL ?? 'info'
    }
  }
})
  .then(app => app.listen({ port: PORT, host: HOST }))
  .then(() => {
    console.log(`nothing-to-do is running at http://${HOST}:${PORT}`)
  })
  .catch(err => {
    console.error('Startup failed:', err)
    process.exit(1)
  })
