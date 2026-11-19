import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Puerto 5173: es el CORS_ORIGIN que la API permite (api-contract.md §4)
export default defineConfig({
  plugins: [react()],
  server: { port: 5173, strictPort: true, host: true },
  preview: { port: 5173, strictPort: true, host: true },
});
