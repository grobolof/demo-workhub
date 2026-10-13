import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const apiTarget = process.env.VITE_API_TARGET || 'http://application'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
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
