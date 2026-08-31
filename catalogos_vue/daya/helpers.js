// Utilidades generales reutilizables en toda la app.

// Formatea un número como moneda MXN.
export const money = (n) =>
  '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Devuelve la clase de badge según el estado del registro.
export const badge = (estado) =>
  ({
    generada: 'b-blue', pagada: 'b-green', anulada: 'b-gray',
    solicitado: 'b-amber', aprobado: 'b-blue', pagado: 'b-green', liquidado: 'b-green', rechazado: 'b-red',
    activo: 'b-blue', vencido: 'b-red', castigado: 'b-gray',
    activa: 'b-green', en_proceso: 'b-amber', reparada: 'b-blue', reemplazada: 'b-blue', vencida: 'b-red', cancelada: 'b-gray',
    disponible: 'b-green', vendida: 'b-blue', garantia: 'b-amber', reparacion: 'b-amber',
  }[estado] || 'b-gray');