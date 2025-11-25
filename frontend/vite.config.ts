import { reactRouter } from "@react-router/dev/vite";
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vite";
import tsconfigPaths from "vite-tsconfig-paths";

export default defineConfig({
  plugins: [tailwindcss(), reactRouter(), tsconfigPaths()],
  server: {
    port: 5173, // React dev server
    proxy: {
      "/storage": "http://127.0.0.1:8000", // for song file access
      "/songs": "http://127.0.0.1:8000", // Laravel backend
      "/playlists": "http://127.0.0.1:8000",
    },
  },
  build: {
    outDir: "../public", // where built files go (Laravel serves this)
  },
});
