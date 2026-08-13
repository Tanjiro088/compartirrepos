import apiClient from './apiClient.js'

export const obtenerHistorialDevoluciones = async (params = {}) => {
  const { data } = await apiClient.get('mermas', { params: { ...params, tipo_merma: 'devolucion_proveedor' } })
  return data
}

export const obtenerComprasParaDevolucion = async () => {
  const { data } = await apiClient.get('compras/disponibles-devolucion')
  return data
}

export const registrarDevolucionProveedor = async (devolucionData) => {
  const payload = { ...devolucionData, tipo_merma: 'devolucion_proveedor' }
  const { data } = await apiClient.post('mermas', payload)
  return data
}
