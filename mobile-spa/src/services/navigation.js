import { reactive } from 'vue'

const SHOW_DELAY = 120
const SLOW_DELAY = 3500

export const navigation = reactive({ pending: false, visible: false, slow: false })

let showTimer = null
let slowTimer = null

export function startNavigation() {
  clearTimeout(showTimer)
  clearTimeout(slowTimer)
  navigation.pending = true
  showTimer = setTimeout(() => { navigation.visible = true }, SHOW_DELAY)
  slowTimer = setTimeout(() => { navigation.slow = true }, SLOW_DELAY)
}

export function finishNavigation() {
  clearTimeout(showTimer)
  clearTimeout(slowTimer)
  navigation.pending = false
  navigation.visible = false
  navigation.slow = false
}
