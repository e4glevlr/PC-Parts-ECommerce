import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      // Proxy frontend /api/* to the FastAPI backend (port 8000) to bypass CORS in development
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        // No rewrite needed; '/api/v1' -> 'http://127.0.0.1:8000/api/v1'
      },
      // Proxy backend static resources (served by FastAPI)
      '/images': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
