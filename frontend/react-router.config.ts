import type { Config } from "@react-router/dev/config";

export default {
  // Config options...
  // Server-side render by default, to enable SPA mode set this to `false`
  ssr: false,
    // Optional: Pre-render specific routes for faster initial loads
  prerender: ["/"], // or true for all static routes
  
} satisfies Config;
