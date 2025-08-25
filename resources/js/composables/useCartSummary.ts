import { ref } from 'vue'
import axios from 'axios'


const summary = ref({ total_qty: 0, total_sum_cents: 0 })

async function loadSummary() {
  const { data } = await axios.get('/cart/summary')
  summary.value = data
}

export function useCartSummary() {
  return { summary, loadSummary }
}