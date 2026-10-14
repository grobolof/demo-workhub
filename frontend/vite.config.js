import { createReadStream, cpSync, existsSync, statSync } from 'node:fs'
import { extname, resolve } from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const apiTarget = process.env.VITE_API_TARGET || 'http://application'
const swaggerTypes = {
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.map': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.html': 'text/html; charset=utf-8',
}

function swaggerAssets() {
  const dist = resolve('node_modules/swagger-ui/dist')
  let outDir = 'dist'

  return {
    name: 'swagger-assets',
    configResolved(config) {
      outDir = config.build.outDir
    },
    configureServer(server) {
      server.middlewares.use('/vendor/swagger-ui', (request, response, next) => {
        const url = decodeURIComponent((request.url || '/').split('?')[0])
        const file = resolve(dist, `.${url}`)
        if (!file.startsWith(`${dist}/`) || !existsSync(file) || !statSync(file).isFile()) {
          next()
          return
        }
        response.setHeader('Content-Type', swaggerTypes[extname(file)] || 'application/octet-stream')
        createReadStream(file).pipe(response)
      })
    },
    closeBundle() {
      cpSync(dist, resolve(outDir, 'vendor/swagger-ui'), { recursive: true })
    },
  }
}

export default defineConfig({
  plugins: [vue(), swaggerAssets()],
  server: {
    host: '0.0.0.0',
    allowedHosts: ['workhub.docker.local', 'workhub.ru.tuna.am', '.ru.tuna.am'],
    port: 5173,
    strictPort: true,
    watch: {
      usePolling: true,
    },
    proxy: {
      '/api': {
        target: apiTarget,
        changeOrigin: true,
        configure(proxy) {
          proxy.on('proxyRes', (proxyRes) => {
            const cookies = proxyRes.headers['set-cookie']
            if (!cookies) return
            proxyRes.headers['set-cookie'] = cookies.map((cookie) =>
              cookie.replace(/; ?Domain=[^;]*/gi, '').replace(/; ?Secure/gi, ''),
            )
          })
        },
      },
      '/upload': {
        target: apiTarget,
        changeOrigin: true,
      },
    },
  },
})
