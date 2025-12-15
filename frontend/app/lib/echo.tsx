import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echo: Echo<'reverb'> | null = null

if (typeof window !== 'undefined') {
  (window as any).Pusher  = Pusher

  echo = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY ?? 'local-dev-key-12345',
    wsHost: import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1',
    wsPort: import.meta.env.VITE_REVERB_PORT
      ? Number(import.meta.env.VITE_REVERB_PORT)
      : 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT
      ? Number(import.meta.env.VITE_REVERB_PORT)
      : 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
  })
}

export default echo
