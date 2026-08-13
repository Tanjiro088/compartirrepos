import apiClient from './apiClient.js'

export const obtenerProveedores = async (params = {}) => {
  const { data } = await apiClient.get('proveedores', { params })
  return data
}

export const crearProveedor = async (proveedorData) => {
  const { data } = await apiClient.post('proveedores', proveedorData)
  return data
}

export const actualizarProveedor = async (id, proveedorData) => {
  const { data } = await apiClient.put(`proveedores/${id}`, proveedorData)
  return data
}

export const actualizarCalificacionProveedor = async (id, calificacion) => {
  const { data } = await apiClient.patch(`proveedores/${id}/calificar`, { calificacion })
  return data
}

export const cambiarEstadoProveedor = async (id, activo) => {
  const { data } = await apiClient.patch(`proveedores/${id}/estado`, { activo })
  return data
}
