import apiClient from './apiClient.js'

export const obtenerHistorialMermas = async (params = {}) => {
  const { data } = await apiClient.get('mermas', { params })
  return data
}

export const obtenerSiguienteFolioMerma = async (tipo = 'merma') => {
  const { data } = await apiClient.get('mermas/next-folio', { params: { tipo } })
  return data.folio
}

export const obtenerMermasPendientes = async () => {
  const { data } = await apiClient.get('mermas/pendientes')
  return data
}

export const obtenerProductosMermables = async (idAlmacen = null) => {
  const params = idAlmacen ? { id_almacen: idAlmacen } : {}
  const { data } = await apiClient.get('productos-modulo', { params })
  return Array.isArray(data) ? data : (data.data || [])
}

export const obtenerAlmacenes = async () => {
  const { data } = await apiClient.get('almacenes')
  const lista = Array.isArray(data) ? data : (data.data || data.resultados || [])
  return lista
    .map((a) => {
      const activo = a.activo
      const estaActivo =
        activo === undefined ||
        activo === null ||
        activo === true ||
        activo === 1 ||
        activo === '1'
      return {
        id_almacen: Number(a.id_almacen ?? a.id ?? 0),
        nombre: String(a.nombre ?? a.nombre_almacen ?? a.almacen ?? '').trim(),
        activo: estaActivo,
      }
    })
    .filter((a) => a.id_almacen > 0 && a.nombre && a.activo)
}

export const registrarMerma = async (mermaData) => {
  const { data } = await apiClient.post('mermas', mermaData)
  return data
}

export const actualizarEstadoMerma = async (idMerma, nuevoEstado, motivo = null) => {
  const payload = { estado: nuevoEstado }
  if (motivo) payload.motivo = motivo
  const { data } = await apiClient.patch(`mermas/${idMerma}/estado`, payload)
  return data
}

export const obtenerComprasParaDevolucion = async () => {
  const { data } = await apiClient.get('compras/disponibles-devolucion')
  return data
}

export const obtenerMerma = async (id) => {
  const { data } = await apiClient.get(`mermas/${id}`)
  return data
}

export const actualizarMerma = async (id, mermaData) => {
  const { data } = await apiClient.put(`mermas/${id}`, mermaData)
  return data
}

export const obtenerReportePerdidas = async (params = {}) => {
  const { data } = await apiClient.get('mermas/reporte-perdidas', { params })
  return data
}

export const exportarMermas = async (params = {}) => {
  const response = await apiClient.get('mermas/exportar', {
    params,
    responseType: 'blob',
  })
  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `mermas_${new Date().toISOString().slice(0, 10)}.xlsx`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export const getEstadoMermaBadgeClass = (estado) => {
  const clases = { aprobada: 'bg-success', rechazada: 'bg-danger', registrada: 'bg-secondary' }
  return clases[estado] || 'bg-light text-dark'
}

export const calcularTotalMerma = (detalles) => {
  return detalles.reduce((sum, item) => sum + item.cantidad * item.precio_costo, 0)
}
