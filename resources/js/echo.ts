import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from 'axios'

Pusher.logToConsole = true // временно: видеть логи подключения/ошибок
window.Pusher = Pusher as any

// Явный authorizer, чтобы приватный канал гарантированно получил сессию и CSRF
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
  forceTLS: true,
  authorizer: (channel: any, options: any) => {
    return {
      authorize: (socketId: string, callback: any) => {
        axios.post('/broadcasting/auth', {
          socket_id: socketId,
          channel_name: channel.name,
        })
        .then(response => callback(false, response.data))
        .catch(error => callback(true, error))
      },
    }
  },
})

// Доп. логи, чтобы сразу видеть проблемы авторизации/подписки
const p = (window as any).Echo.connector.pusher
p.connection.bind('connected', () => console.log('[Echo] connected ✅'))
p.connection.bind('error', (err: any) => console.error('[Echo] conn error ❌', err))
p.bind('pusher:subscription_error', (status: any) => console.error('[Echo] sub error ❌', status))
p.bind('pusher:subscription_succeeded', (ch: any) => console.log('[Echo] sub ok ✅', ch))