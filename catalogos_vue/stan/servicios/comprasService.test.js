import { describe, it, expect } from 'vitest'
import { calcularTotalesCompra, determinarEstadoRecepcion } from './comprasService'

describe('comprasService - Lógica de Compras', () => {

    it('debería calcular el total correctamente (Subtotal + IVA 16%)', () => {
        const detalles = [
            { cantidad: 2, precio: 50 }, // 100
            { cantidad: 1, precio: 50 }  // 50
        ] // Subtotal 150. IVA 16% = 24. Total = 174.

        const resultado = calcularTotalesCompra(detalles)
        expect(resultado.subtotal).toBe(150)
        expect(resultado.impuesto).toBe(24)
        expect(resultado.total).toBe(174)
    })

    it('debería determinar que la recepción es parcial si faltan productos', () => {
        const detalles = [
            { pedida: 10, recibida: 5 }
        ]
        expect(determinarEstadoRecepcion(detalles)).toBe('parcial')
    })

    it('debería determinar recepción completa si todos coinciden', () => {
        const detalles = [
            { pedida: 10, recibida: 10 },
            { pedida: 5, recibida: 5 }
        ]
        expect(determinarEstadoRecepcion(detalles)).toBe('recibido')
    })
})
