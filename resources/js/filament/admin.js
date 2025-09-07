import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

Pusher.logToConsole = true // временно, чтобы видеть детальные логи Pusher
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

window.Echo.private('orders')
  .subscribed(() => console.log('[Echo] subscribed to private:orders ✅'))
  .listen('.workflow.updated', () => {
    window.dispatchEvent(new CustomEvent('realtime-orders'))
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

  window.Echo.connector.pusher.connection.bind('state_change', (s) => {
  console.log('[Pusher] state', s.previous, '→', s.current)
})
window.Echo.connector.pusher.connection.bind('error', (err) => {
  console.error('[Pusher] error ❌', err)
})