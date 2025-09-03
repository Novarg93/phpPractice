import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

Pusher.logToConsole = false // временно, чтобы видеть детальные логи Pusher
window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
  forceTLS: true,
})

window.Echo.connector.pusher.connection.bind('connected', () => {
  console.log('[Echo] connected ✅')
})

window.Echo.channel('contact-messages')
  .subscribed(() => console.log('[Echo] subscribed to contact-messages ✅'))
  window.Echo.channel('contact-messages')
  .listen('.created', (e) => {
    console.debug('[Echo] created', e)
    window.dispatchEvent(new CustomEvent('realtime-contact'))
  })
  .listen('.status-changed', (e) => {
    console.debug('[Echo] status-changed', e)
    window.dispatchEvent(new CustomEvent('realtime-contact'))
  })
  .listen('.deleted', (e) => {
    console.debug('[Echo] deleted', e)
    window.dispatchEvent(new CustomEvent('realtime-contact'))
  })