import axios from 'axios'
import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'

function initializeApp() {
  const tokenMeta = document.head.querySelector('meta[name="csrf-token"]')
  if (tokenMeta) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.content
  }
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
  axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || '/'

  axios.interceptors.response.use(
    response => response,
    error => {
      if (error.response && error.response.status === 401) {
        delete axios.defaults.headers.common['X-CSRF-TOKEN']
        window.location.href = '/login'
      }
      return Promise.reject(error)
    }
  )
}

function setupRouter() {
  return createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
    scrollBehavior(to, from, savedPosition) {
      if (savedPosition) {
        return savedPosition
      }
      if (to.hash) {
        return { el: to.hash, behavior: 'smooth' }
      }
      return { left: 0, top: 0 }
    }
  })
}

function mountApp(router, pinia) {
  const app = createApp(App)
  app.use(pinia)
  app.use(router)
  router.isReady().then(() => {
    app.mount('#app')
  })
}

initializeApp()
const pinia = createPinia()
const router = setupRouter()
mountApp(router, pinia)