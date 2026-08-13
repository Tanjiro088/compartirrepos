import apiClient from './apiClient.js'

export const obtenerHistorialCuentas = async (params = {}) => {
  const { data } = await apiClient.get('cuentas', { params })
  return data
}

export const registrarAbonoCuenta = async (idCuenta, datosPago) => {
  const { data } = await apiClient.post(`cuentas/${idCuenta}/abonar`, datosPago)
  return data
}

export const ordenarCuentasPorPrioridad = (cuentas) => {
  return [...cuentas].sort((a, b) => {
    const prioridad = { vencido: 1, pendiente: 2, parcial: 2, pagado: 3 }
    const pa = prioridad[a.estado] || 9
    const pb = prioridad[b.estado] || 9
    if (pa !== pb) return pa - pb
    return new Date(a.fecha_vencimiento) - new Date(b.fecha_vencimiento)
  })
}

export const getEstadoBadgeClass = (estado) => {
  const clases = { pagado: 'bg-success', vencido: 'bg-danger', pendiente: 'bg-secondary', parcial: 'bg-warning' }
  return clases[estado] || 'bg-light text-dark'
}

export const validarDistribucionAbono = (efectivo, tarjeta, saldoPendiente) => {
  const totalAbono = parseFloat(efectivo || 0) + parseFloat(tarjeta || 0)
  return totalAbono > 0 && totalAbono <= saldoPendiente
}
