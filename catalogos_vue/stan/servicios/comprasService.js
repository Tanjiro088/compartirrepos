import apiClient from './apiClient.js'

export const obtenerHistorialCompras = async (params = {}) => {
  const { data } = await apiClient.get('compras', { params })
  return data
}

export const obtenerSiguienteFolioCompra = async () => {
  const { data } = await apiClient.get('compras/next-folio')
  return data.folio
}

export const crearOrdenCompra = async (compraData) => {
  const { data } = await apiClient.post('compras', compraData)
  return data
}

export const actualizarOrdenCompra = async (idCompra, compraData) => {
  const { data } = await apiClient.put(`compras/${idCompra}`, compraData)
  return data
}

export const registrarRecepcionMercancia = async (idCompra, itemsAudita) => {
  const { data } = await apiClient.patch(`compras/${idCompra}/recepcion`, {
    id_usuario: 3,
    fecha_recepcion: new Date().toISOString().slice(0, 10),
    detalles_recibidos: itemsAudita.map((item) => ({
      id_presentacion: item.id_presentacion || 1,
      cantidad_recibida: item.recibida,
      observacion: item.observacion || '',
    })),
  })
  return data
}

export const obtenerProductosDisponibles = async () => {
  const { data } = await apiClient.get('productos-modulo')
  return Array.isArray(data) ? data : (data.data || [])
}

export const calcularTotalesCompra = (detalles) => {
  const subtotal = detalles.reduce((sum, item) => sum + item.cantidad * item.precio, 0)
  const impuesto = subtotal * 0.16
  const total = subtotal + impuesto
  return { subtotal, impuesto, total }
}

export const determinarEstadoRecepcion = (detalles) => {
  const hayFaltantes = detalles.some((item) => item.recibida < item.pedida)
  return hayFaltantes ? 'parcial' : 'recibido'
}

export const validarDistribucionPagos = (efectivo, tarjeta, credito, totalCompra) => {
  const suma = parseFloat(efectivo || 0) + parseFloat(tarjeta || 0) + parseFloat(credito || 0)
  return Math.abs(suma - totalCompra) < 0.01
}
