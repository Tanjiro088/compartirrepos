import axios from 'axios'
import { useGlobalStore } from '../stores/store.js'

const apiClient = axios.create()

apiClient.interceptors.request.use((config) => {
  const store = useGlobalStore()
  config.baseURL = store.baseUrl
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const msg = error?.response?.data?.Mensaje
      || error?.response?.data?.message
      || error?.message
      || 'Error de conexión.'
    console.error('[API]', msg, error?.response?.status || '')
    return Promise.reject(error)
  }
)

export default apiClient
