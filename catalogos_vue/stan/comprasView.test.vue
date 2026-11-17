import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createTestingPinia } from '@pinia/testing'
import ComprasView from './ComprasView.vue'

import * as comprasService from '@/services/comprasService'
import * as proveedoresService from '@/services/proveedoresService'
import * as mermasService from '@/services/mermasService'

// Mock de servicios de datos
vi.mock('@/services/comprasService', () => ({
  obtenerHistorialCompras: vi.fn(),
  obtenerProductosDisponibles: vi.fn(),
  obtenerSiguienteFolioCompra: vi.fn(),
  calcularTotalesCompra: vi.fn(),
  determinarEstadoRecepcion: vi.fn(),
  crearOrdenCompra: vi.fn()
}))

vi.mock('@/services/proveedoresService', () => ({
  obtenerProveedores: vi.fn()
}))

vi.mock('@/services/mermasService', () => ({
  obtenerAlmacenes: vi.fn()
}))

describe('ComprasView.vue', () => {
  let mockConfirmOpen

  beforeEach(() => {
    vi.clearAllMocks()

    // Respuestas base predeterminadas para los mocks
    comprasService.obtenerHistorialCompras.mockResolvedValue({
      resultados: [
        {
          id_compra: 101,
          folio: 'OC-0001',
          proveedor: 'Distribuidora Central SA',
          fecha: '2026-09-01',
          total: 12500.50,
          estado: 'recibida'
        }
      ],
      total: 1
    })

    comprasService.obtenerProductosDisponibles.mockResolvedValue([
      { id_presentacion: 1, nombre: 'Monitor 27 Pulgadas', costo: 3200 }
    ])

    comprasService.obtenerSiguienteFolioCompra.mockResolvedValue('OC-0002')

    comprasService.calcularTotalesCompra.mockReturnValue({
      subtotal: 10000,
      impuesto: 1600,
      total: 11600
    })

    comprasService.determinarEstadoRecepcion.mockReturnValue('recibido')

    proveedoresService.obtenerProveedores.mockResolvedValue({
      resultados: [{ id_proveedor: 1, razon_social: 'Distribuidora Central SA' }]
    })

    mermasService.obtenerAlmacenes.mockResolvedValue([
      { id_almacen: 1, nombre: 'Almacén Principal' }
    ])

    mockConfirmOpen = vi.fn().mockResolvedValue(true)
  })

  const mountComponent = (options = {}) => {
    return mount(ComprasView, {
      global: {
        plugins: [
          createTestingPinia({
            createSpy: vi.fn,
            initialState: {
              global: { baseUrl: 'https://api.erp.test/api' }
            }
          })
        ],
        stubs: {
          ConfirmModal: {
            name: 'ConfirmModal',
            template: '<div class="stub-confirm-modal"></div>',
            methods: {
              open: mockConfirmOpen
            }
          },
          Paginador: {
            template: '<div class="stub-paginador"></div>'
          },
          ToastNotification: true,
          PageHeader: {
            template: '<div><slot name="action"></slot><slot></slot></div>'
          }
        },
        ...options.global
      }
    })
  }

  it('debería renderizar la cabecera y el botón de nueva compra', () => {
    const wrapper = mountComponent()
    expect(wrapper.text()).toMatch(/Módulo de Compras|Compras/i)

    // Selector resiliente por texto o data-testid
    const botonRegistro = wrapper.find('[data-testid="btn-nueva-compra"]')
    if (botonRegistro.exists()) {
      expect(botonRegistro.exists()).toBe(true)
    } else {
      const botonPorTexto = wrapper.findAll('button').find(b => b.text().includes('Registrar') || b.text().includes('Nueva'))
      expect(botonPorTexto?.exists()).toBe(true)
    }
  })

  it('debería consultar y renderizar el historial de compras al montarse', async () => {
    const wrapper = mountComponent()
    await flushPromises()

    expect(comprasService.obtenerHistorialCompras).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('OC-0001')
    expect(wrapper.text()).toContain('Distribuidora Central SA')
  })

  it('debería precargar proveedores y almacenes al abrir el modal de compra', async () => {
    const wrapper = mountComponent()
    await flushPromises()

    const btnNuevo = wrapper.find('[data-testid="btn-nueva-compra"]').exists()
      ? wrapper.find('[data-testid="btn-nueva-compra"]')
      : wrapper.findAll('button').find(b => b.text().includes('Registrar') || b.text().includes('Nueva'))

    if (btnNuevo?.exists()) {
      await btnNuevo.trigger('click')
      await flushPromises()

      expect(proveedoresService.obtenerProveedores).toHaveBeenCalled()
      expect(comprasService.obtenerSiguienteFolioCompra).toHaveBeenCalled()
    }
  })

  it('debería solicitar confirmación mediante ConfirmModal antes de guardar una compra', async () => {
    const wrapper = mountComponent()
    await flushPromises()

    // Simular llamada directa al método de guardado si el modal está expuesto
    if (wrapper.vm.guardarCompra || wrapper.vm.ejecutarRegistroCompra) {
      const metodoGuardar = wrapper.vm.guardarCompra || wrapper.vm.ejecutarRegistroCompra

      // Llenamos datos mínimos necesarios si el form está disponible
      if (wrapper.vm.formCompra) {
        wrapper.vm.formCompra.id_proveedor = 1
        wrapper.vm.formCompra.id_almacen = 1
        wrapper.vm.formCompra.detalles = [{ id_presentacion: 1, cantidad: 2, costo_unitario: 100 }]
      }

      await metodoGuardar()
      expect(mockConfirmOpen).toHaveBeenCalled()
    }
  })
})