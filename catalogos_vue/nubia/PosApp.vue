<template>
  <div class="pos-app-container d-flex flex-column vh-100 bg-app-theme no-print">
    
    <!-- ========================================================================= -->
<!-- HEADER REDISTRIBUIDO SEGÚN IMAGEN                                       -->
<!-- ========================================================================= -->
<header class="pos-header-modern">
  
  <!-- BLOQUE 1: LOGO + TERMINAL POS -->
  <div class="header-block-logo">
    <div class="brand-icon-modern theme-gradient text-white d-flex align-items-center justify-content-center shadow">
      <i class="bi bi-bag-check-fill fs-3"></i>
    </div>
    <div class="logo-text">
      <h4 class="mb-0 fw-bold text-slate-800">Terminal POS</h4>
      <small class="text-theme-blue fw-medium">Módulo de Ventas</small>
    </div>
  </div>

  <div class="header-divider-modern"></div>

  <!-- BLOQUE 2: SELECTOR DE CLIENTE CON BÚSQUEDA -->
<div class="header-block-client" ref="clientBlockRef">
  <div class="client-select-modern shadow-sm" :class="{ 'focused': showClientDropdown }">
    <span class="client-select-icon">
      <i class="bi bi-person-badge fs-6"></i>
    </span>
    <input 
      ref="clientInputRef"
      type="text" 
      class="client-search-input"
      :value="clientDisplayText"
      @focus="openClientDropdown"
      @input="onClientInput"
      @keydown.down.prevent="navigateClients(1)"
      @keydown.up.prevent="navigateClients(-1)"
      @keydown.enter.prevent="selectHighlightedClient"
      @keydown.escape.prevent="closeClientDropdown"
      placeholder="Buscar cliente..."
    >
    <span class="client-select-arrow" @click="toggleClientDropdown">
      <i class="bi" :class="showClientDropdown ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </span>
  </div>

  <!-- LISTA DESPLEGABLE DE CLIENTES -->
  <transition name="dropdown-client">
    <div v-if="showClientDropdown" class="client-dropdown-modern shadow-lg">
      <div class="client-dropdown-scroll">
        <!-- Siempre mostrar Público General al inicio -->
        <div 
          class="client-dropdown-item"
          :class="{ 'highlighted': clientHighlightIndex === -1 }"
          @click="selectClient(1, 'Público General')"
          @mouseenter="clientHighlightIndex = -1"
        >
          <i class="bi bi-people-fill client-item-icon"></i>
          <div class="client-item-info">
            <span class="client-item-name">Público General</span>
            <small class="client-item-detail">Cliente por defecto</small>
          </div>
          <i v-if="clienteSeleccionado === 1" class="bi bi-check-circle-fill client-item-check"></i>
        </div>

        <!-- Clientes filtrados -->
        <template v-if="filteredClients.length > 0">
          <div 
            v-for="(cliente, index) in filteredClients" 
            :key="cliente.id_cliente"
            class="client-dropdown-item"
            :class="{ 'highlighted': clientHighlightIndex === index }"
            @click="selectClient(cliente.id_cliente, cliente.nombre_comercial)"
            @mouseenter="clientHighlightIndex = index"
          >
            <i class="bi bi-person client-item-icon"></i>
            <div class="client-item-info">
              <span class="client-item-name">{{ cliente.nombre_comercial }}</span>
              <small class="client-item-detail" v-if="cliente.rfc || cliente.email">
                {{ cliente.rfc || cliente.email || '' }}
              </small>
            </div>
            <i v-if="clienteSeleccionado === cliente.id_cliente" class="bi bi-check-circle-fill client-item-check"></i>
          </div>
        </template>

        <!-- Sin resultados -->
        <div v-else-if="clientSearchTerm.length > 0" class="client-dropdown-empty">
          <i class="bi bi-search"></i>
          <span>Sin coincidencias para "{{ clientSearchTerm }}"</span>
        </div>
      </div>

      <!-- Pie del dropdown -->
      <div class="client-dropdown-footer">
        <span>{{ totalClientsShown }} cliente{{ totalClientsShown !== 1 ? 's' : '' }} disponible{{ totalClientsShown !== 1 ? 's' : '' }}</span>
      </div>
    </div>
  </transition>

  <!-- Capa invisible para cerrar al hacer clic fuera -->
  <div 
    v-if="showClientDropdown" 
    class="client-overlay" 
    @click="closeClientDropdown"
  ></div>
</div>

  <div class="header-divider-modern"></div>

  <!-- BLOQUE 3: PESTAÑAS DE VENTAS (CENTRO - FLEX GROW) -->
  <div class="header-block-tabs">
    <div class="sales-tabs-modern">
      <button 
        v-for="venta in ventasActivas" 
        :key="venta.id"
        class="sale-tab-btn-modern"
        :class="venta.id === ventaActualId ? 'active' : ''"
        @click="cambiarVentaActiva(venta.id)"
      >
        <i class="bi bi-receipt" :class="venta.id === ventaActualId ? '' : 'text-muted'"></i> 
        <span class="fw-bold">{{ venta.nombre }}</span>
        <span 
                v-if="venta.carrito.length > 0" 
                class="badge rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                style="width: 22px; height: 22px; font-size: 0.75rem; padding: 0;"
                :class="venta.id === ventaActualId ? 'bg-white text-theme-blue' : 'text-white'"
                :style="venta.id !== ventaActualId ? 'background-color: #DF301C;' : ''"
              >
                {{ venta.carrito.length }}
              </span>
        <i 
          v-if="venta.id !== 1 || ventasActivas.length > 1" 
          class="bi bi-x ms-1 tab-close-modern" 
          title="Cerrar Venta"
          @click.stop="cerrarVenta(venta.id)"
        ></i>
      </button>
      
      <button class="btn-new-sale-modern" @click="abrirNuevaVenta" title="Nueva Venta">
        <i class="bi bi-plus-lg fw-bold"></i>
      </button>
    </div>
  </div>

  <div class="header-divider-modern"></div>

  <!-- BLOQUE 4: CAJERO + CAJA -->
  <div class="header-block-user">
    <div class="user-info-modern">
      <span class="user-name-modern">
        <i class="bi bi-person-circle text-theme-green me-1"></i> {{ nombreCajero }}
      </span>
      <span class="user-box-modern">
        <i class="bi bi-box-seam me-1"></i> {{ nombreCaja }}
      </span>
    </div>
  </div>

  <div class="header-divider-modern"></div>

  <!-- BLOQUE 5: BOTONES DE ACCIÓN -->
  <div class="header-block-actions">
    <button class="btn-icon-modern" @click="toggleFullscreen" title="Pantalla Completa">
      <i class="bi" :class="isFullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"></i>
    </button>
    <button class="btn-action-modern" @click="cargarPendientes">
      <i class="bi bi-folder-check me-1"></i> Guardadas
    </button>
    <button class="btn-action-modern" @click="cargarHistorial">
      <i class="bi bi-clock-history me-1"></i> Historial
    </button>
  </div>

</header>

    <main class="flex-grow-1 overflow-hidden d-flex p-3 gap-3">
      
      <!-- LADO IZQUIERDO: PRODUCTOS -->
      <div class="col-lg-7 h-100 d-flex flex-column bg-transparent overflow-hidden">
        
        <div class="search-section mb-3 position-relative">
          <div class="search-bar-wrapper bg-white shadow-sm d-flex align-items-center p-2 rounded-pill border border-light">
            <div class="bg-light-blue rounded-circle d-flex align-items-center justify-content-center ms-2" style="width: 40px; height: 40px;">
                <i class="bi bi-upc-scan fs-5 text-theme-blue"></i>
            </div>
            <input 
              ref="inputBusqueda"
              type="text" 
              v-model="codigoBusqueda" 
              class="form-control border-0 fs-5 shadow-none custom-search-input py-2 px-3 bg-transparent text-slate-800 fw-medium" 
              placeholder="Buscar producto o escanear código..."
              @keydown.down.prevent="navegarLista(1)"
              @keydown.up.prevent="navegarLista(-1)"
              @keydown.enter.prevent="manejarEnterInput"
              @input="filtrarProductosDebounced"
              @blur="ocultarLista"
            >
            <button class="btn-search-theme ms-2 rounded-pill px-4" @click="manejarEnterInput">
              Buscar
            </button>
          </div>

          <div v-if="mostrarLista && productosFiltrados.length > 0" class="autocomplete-list shadow-lg border-0 bg-white rounded-4 mt-2 overflow-hidden">
            <div 
              v-for="(p, index) in productosFiltrados" 
              :key="p.id_presentacion"
              class="autocomplete-item d-flex justify-content-between align-items-center px-4 py-3 border-bottom border-light"
              :class="{ 'bg-light-blue border-start border-4 border-theme-blue': index === indiceSeleccionado }"
              @mousedown.prevent="seleccionarProducto(p)"
              @mouseenter="indiceSeleccionado = index"
            >
              <div class="d-flex align-items-center">
                <div class="icon-box bg-light-green text-theme-green rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-box-seam fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold text-slate-800 fs-6">{{ p.nombre }}</div>
                  <small class="text-muted">Cód: {{ p.codigo_barras || p.id_presentacion }}</small>
                </div>
              </div>
              <span class="badge bg-theme-green text-white fs-6 px-3 py-2 rounded-pill shadow-sm">
                ${{ Number(p.precio || p.precio_venta || 0).toFixed(2) }}
              </span>
            </div>
          </div>

          <transition name="fade">
            <div v-if="mensajeAlerta" class="alert bg-light-orange border-0 text-orange mt-3 shadow-sm d-flex align-items-center py-2 px-3 rounded-pill">
              <i class="bi bi-exclamation-triangle-fill me-3 fs-5"></i>
              <span class="fw-bold">{{ mensajeAlerta }}</span>
            </div>
          </transition>
        </div>

        <div class="flex-grow-1 overflow-auto custom-scrollbar pe-2 d-flex flex-column gap-4">
            <div class="quick-sales-section">
              <div class="d-flex align-items-center mb-3">
                <div class="bg-light-blue rounded-circle p-2 me-2 d-flex"><i class="bi bi-lightning-charge-fill text-theme-blue"></i></div>
                <h5 class="fw-bold mb-0 text-slate-800">Productos Rápidos</h5>
              </div>
              
              <div class="quick-items-grid">
                <button 
                  v-for="p in productosRapidos" 
                  :key="p.id_presentacion" 
                  class="quick-item-card shadow-sm" 
                  @click="seleccionarProducto(p)"
                >
                  <div class="card-icon theme-gradient-light text-theme-blue shadow-sm">
                    <i class="bi bi-tag-fill"></i>
                  </div>
                  <div class="card-info d-flex flex-column align-items-start ms-3 w-100">
                    <span class="product-name fw-bold text-slate-800 w-100 text-start">{{ p.nombre }}</span>
                    <span class="product-price fw-bold text-theme-green mt-1">${{ Number(p.precio || p.precio_venta || 0).toFixed(2) }}</span>
                  </div>
                </button>
              </div>
            </div>
            
            <div class="search-results-section mt-2">
              <div class="d-flex align-items-center mb-3">
                <div class="bg-light-green rounded-circle p-2 me-2 d-flex"><i class="bi bi-view-list text-theme-green"></i></div>
                <h5 class="fw-bold mb-0 text-slate-800">Resultados de Búsqueda</h5>
              </div>
              
              <div class="search-results-grid">
                <div v-if="!ventaActual.productosBuscados || ventaActual.productosBuscados.length === 0" class="text-muted text-center py-5 w-100 bg-white rounded-4 border border-dashed">
                  <div class="bg-light rounded-circle d-inline-flex p-4 mb-3"><i class="bi bi-search fs-1 text-slate-300"></i></div>
                  <h6 class="fw-bold text-slate-500">Sin búsquedas recientes</h6>
                  <small>Los productos escaneados aparecerán aquí.</small>
                </div>

                <div v-for="p in ventaActual.productosBuscados" :key="p.id_presentacion" class="search-result-card shadow-sm bg-white" @click="verDetalleProducto(p)" style="cursor: pointer;">
                  <img 
                    :src="p.imagen ? globalStore.imagenUrlPublica + p.imagen : 'https://via.placeholder.com/80x80/e0f2fe/0284c7?text=Sin+Img'" 
                    class="result-img shadow-sm" 
                    :alt="p.nombre"
                  >
                  <div class="result-info">
                    <span class="result-name fw-bold text-slate-800">{{ p.nombre }}</span>
                    <span class="result-id text-muted mb-2">ID: {{ p.id_presentacion }}</span>
                    <div class="result-meta d-flex gap-2">
                      <span class="badge bg-light-blue text-theme-blue border-0">Stock: {{Math.trunc(p.stock) }}</span>
                      <span class="badge bg-light-green text-theme-green border-0 fw-bold">${{ Number(p.precio || p.precio_venta || 0).toFixed(2) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>
      </div>

      <!-- LADO DERECHO: CARRITO FLOTANTE -->
      <div class="col-lg-5 h-100 d-flex flex-column z-index-2">
        <div class="cart-panel bg-white shadow-lg rounded-4 d-flex flex-column h-100 overflow-hidden border border-light">
            
            <div class="cart-header px-4 py-3 theme-gradient text-white d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 d-flex align-items-center">
                <i class="bi bi-cart3 fs-4 me-2"></i> Ticket Activo
              </h5>
              <span class="badge bg-white text-theme-blue rounded-pill px-3 py-2 shadow-sm fw-bold fs-7">{{ ventaActual.carrito.length }} artículos</span>
            </div>

            <div class="cart-items-container flex-grow-1 overflow-auto custom-scrollbar p-0 bg-white">
              <div v-if="ventaActual.carrito.length > 0" class="d-flex flex-column">
                <transition-group name="list" tag="div">
                  <div v-for="(item, index) in ventaActual.carrito" :key="item.id_presentacion" class="cart-item-row px-4 py-2 border-bottom border-light d-flex align-items-center justify-content-between gap-3">
  
                    <!-- Nombre y Precio en 1 sola línea -->
                    <div class="flex-grow-1 overflow-hidden d-flex align-items-center gap-2" style="min-width: 130px;">
                      <span class="fw-bold text-slate-800 text-truncate fs-6 mb-0">
                        {{ item.nombre }} 
                        <span v-if="item.esPeso" class="badge bg-light-orange text-orange border-0 ms-1" style="font-size: 0.65rem;">Báscula</span>
                      </span>
                      <small class="text-muted fw-medium text-nowrap">
                        ${{ Number(item.precio).toFixed(2) }} {{ item.esPeso ? 'g' : 'c/u' }}
                      </small>
                    </div>
                    
                    <!-- Controlador de cantidad -->
                    <div class="d-flex align-items-center flex-shrink-0">
                      <div class="qty-controller d-flex align-items-center border border-light rounded-pill bg-light-gray shadow-sm overflow-hidden" style="height: 30px;">
                        <button class="qty-btn text-theme-blue py-0" @click="cambiarCantidad(index, item.esPeso ? -50 : -1)">
                          <i class="bi bi-dash fw-bold"></i>
                        </button>
                        <span class="qty-value fw-bold px-2 text-slate-800 bg-white border-start border-end border-light d-flex align-items-center justify-content-center" style="min-width: 40px; font-size: 0.9rem; height: 100%;">
                          {{ item.esPeso ? item.cantidad.toFixed(0) + 'g' : item.cantidad }}
                        </span>
                        <button class="qty-btn text-theme-green py-0" @click="cambiarCantidad(index, item.esPeso ? 50 : 1)">
                          <i class="bi bi-plus fw-bold"></i>
                        </button>
                      </div>
                    </div>

                    <!-- Subtotal -->
                    <div class="flex-shrink-0 text-end" style="width: 75px;">
                      <span class="fw-bold text-theme-blue fs-6">
                        ${{ (Number(item.cantidad) * Number(item.precio)).toFixed(2) }}
                      </span>
                    </div>

                    <!-- Botón Eliminar -->
                    <div class="flex-shrink-0">
                      <button class="btn btn-sm bg-light-orange text-orange rounded-circle p-0 d-flex align-items-center justify-content-center btn-remove-hover shadow-sm" style="width: 28px; height: 28px;" @click="eliminarDelCarrito(index)" title="Quitar">
                        <i class="bi bi-trash-fill" style="font-size: 0.85rem;"></i>
                      </button>
                    </div>

                  </div>
                </transition-group>
              </div>

              <div v-else class="h-100 d-flex flex-column justify-content-center align-items-center text-muted bg-app-theme">
                <div class="empty-cart-icon mb-4 bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px;">
                  <i class="bi bi-cart-x fs-1 text-slate-300"></i>
                </div>
                <h5 class="fw-bold text-slate-600 mb-1">Carrito Vacío</h5>
                <p class="text-muted small">Escanea o busca un producto para comenzar</p>
              </div>
            </div>

            <!-- TOTALES Y BOTONES DE COBRO (Optimizados en altura) -->
            <div class="cart-totals px-4 py-3 mt-auto bg-light-blue border-top border-light">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-theme-blue fw-bold">Subtotal</span>
                <span class="fw-bold text-slate-800 fs-6">${{ subtotal.toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-theme-blue fw-bold">I.V.A (18%)</span>
                <span class="fw-bold text-slate-800 fs-6">${{ iva.toFixed(2) }}</span>
              </div>
              
              <!-- Total más compacto -->
              <div class="d-flex justify-content-between align-items-center border-top border-theme-blue pt-2 mb-3">
                <h5 class="fw-black text-slate-800 mb-0 text-uppercase tracking-wider">Total</h5>
                <h2 class="fw-black text-theme-green mb-0" style="font-size: 2rem;">${{ total.toFixed(2) }}</h2>
              </div>
              
              <!-- Botones -->
              <div class="d-flex justify-content-between gap-3 w-100">
                <button
                    class="btn-guardar-venta w-50 d-flex align-items-center justify-content-center gap-2 shadow-sm rounded-4"
                    :disabled="ventaActual.carrito.length === 0"
                    @click="guardarVentaPendiente"
                >
                    <i class="bi bi-bookmark-plus-fill fs-5"></i>
                    <span class="fs-6 fw-bold">Guardar</span>
                </button>

                <button
                    class="btn-proceder-cobro w-50 d-flex align-items-center justify-content-center gap-2 shadow-md rounded-4"
                    :class="{'active-checkout': ventaActual.carrito.length > 0}"
                    :disabled="ventaActual.carrito.length === 0"
                    @click="abrirModalCobro"
                >
                    <i class="bi bi-wallet2 fs-5"></i>
                    <span class="fs-6 fw-bold">Cobrar</span>
                </button>
              </div>
            </div>
        </div>
      </div>
    </main>

    <!-- MODALES INTACTOS (Solo se aplicaron clases de color base si aplica) -->
    <!-- MODAL COBRO -->
    <div class="modal fade custom-modal" id="modalCobro" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header theme-gradient text-white border-0 py-4 px-4">
            <h5 class="modal-title fw-bold fs-5"><i class="bi bi-credit-card-2-front me-2"></i> Pago: {{ ventaActual.nombre }}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="limpiarPagos"></button>
          </div>
          
          <div class="modal-body p-4 p-md-5 bg-app-theme">
            <div class="row g-4">
              <div class="col-md-6 border-end border-light pe-md-4">
                <div class="text-start mb-4">
                  <p class="text-uppercase text-theme-blue fw-bold mb-1 tracking-wider">Monto a Pagar</p>
                  <h1 class="display-5 fw-black text-slate-800 mb-0">${{ total.toFixed(2) }}</h1>
                </div>
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-3">
                  <label class="form-label fw-bold text-slate-600 mb-3">Registrar Método de Pago</label>
                  <select class="form-select form-select-lg bg-light-gray border-0 shadow-none mb-3 text-slate-800 fw-bold" v-model="nuevoPagoMetodo" @change="limpiarDatosPagoActual">
                    <option :value="1">Efectivo</option>
                    <option :value="2">Tarjeta</option>
                    <option :value="3">Transferencia</option>
                  </select>
                  
                  <div class="input-group mb-2 shadow-sm rounded-3 overflow-hidden">
                    <span class="input-group-text bg-light-blue border-0 fs-4 text-theme-blue"><i class="bi bi-currency-dollar"></i></span>
                    <input type="number" class="form-control form-control-lg bg-light-blue border-0 shadow-none text-end fw-black fs-4 text-slate-800" v-model="nuevoPagoMonto" placeholder="0.00" @keyup.enter="agregarPago">
                    <button class="btn btn-theme-green px-4" @click="agregarPago"><i class="bi bi-plus-lg fw-bold fs-5"></i></button>
                  </div>

                  <div v-if="nuevoPagoMetodo === 2" class="mt-3">
                    <div class="input-group mb-2">
                      <span class="input-group-text bg-light-gray border-0"><i class="bi bi-credit-card"></i></span>
                      <input type="text" class="form-control bg-light-gray border-0 shadow-none" v-model="datosPagoActual.referencia" placeholder="Terminación de tarjeta (Ej. ****5678)">
                    </div>
                    <div class="input-group mb-2">
                      <span class="input-group-text bg-light-gray border-0"><i class="bi bi-shield-lock"></i></span>
                      <input type="text" class="form-control bg-light-gray border-0 shadow-none" v-model="datosPagoActual.autorizacion" placeholder="Código autorización">
                    </div>
                  </div>

                  <div v-if="nuevoPagoMetodo === 3" class="mt-3">
                    <div class="card bg-light-blue border-0 rounded-3 p-3 small text-theme-blue">
                      <p class="mb-1"><strong>Banco:</strong> Banco Nacional</p>
                      <p class="mb-1"><strong>Cuenta:</strong> 0123 4567 8901 2345</p>
                      <p class="mb-0"><strong>Titular:</strong> Empresa S.A. de C.V.</p>
                    </div>
                    <div class="input-group mt-2">
                      <span class="input-group-text bg-light-gray border-0"><i class="bi bi-receipt"></i></span>
                      <input type="text" class="form-control bg-light-gray border-0 shadow-none" v-model="datosPagoActual.referencia" placeholder="Folio de transferencia">
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-6 ps-md-4 d-flex flex-column">
                <h6 class="fw-bold text-slate-600 mb-3">Pagos Registrados</h6>
                <div class="flex-grow-1 overflow-auto custom-scrollbar mb-3" style="max-height: 200px;">
                  <ul class="list-group list-group-flush border-0 bg-transparent gap-2">
                    <li v-for="(p, index) in pagosMixtos" :key="index" class="list-group-item d-flex justify-content-between align-items-center py-3 border-0 bg-white rounded-3 shadow-sm">
                      <div class="d-flex align-items-center">
                        <div class="icon-circle bg-light-blue text-theme-blue me-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px;">
                          <i :class="p.id_forma_pago == 1 ? 'bi-cash text-theme-green' : (p.id_forma_pago == 2 ? 'bi-credit-card' : 'bi-phone')" class="bi fs-5"></i>
                        </div>
                        <span class="fw-bold text-slate-800">{{ p.id_forma_pago == 1 ? 'Efectivo' : (p.id_forma_pago == 2 ? 'Tarjeta' : 'Transferencia') }}</span>
                      </div>
                      <div class="d-flex align-items-center gap-3">
                        <strong class="fs-5 text-slate-800">${{ Number(p.monto).toFixed(2) }}</strong>
                        <button class="btn btn-sm bg-light-orange text-orange rounded-circle p-1" style="width: 28px; height: 28px;" @click="quitarPago(index)"><i class="bi bi-x-lg"></i></button>
                      </div>
                    </li>
                    <li v-if="pagosMixtos.length === 0" class="list-group-item text-center text-muted py-4 border-0 bg-white rounded-3">Sin pagos registrados</li>
                  </ul>
                </div>

                <div class="mt-auto card border-0 shadow-sm rounded-4 bg-white p-4">
                  <div class="d-flex justify-content-between fs-6 mb-2">
                    <span class="text-slate-500 fw-bold">Total Pagado:</span>
                    <strong class="text-slate-800">${{ totalPagado.toFixed(2) }}</strong>
                  </div>
                  <div class="d-flex justify-content-between align-items-center border-top border-light pt-3 mt-2">
                    <span class="fw-black text-uppercase" :class="cambio < 0 ? 'text-orange' : 'text-theme-blue'">{{ cambio < 0 ? 'Faltante:' : 'Cambio:' }}</span>
                    <strong class="fs-2" :class="cambio < 0 ? 'text-orange' : 'text-theme-green'">${{ Math.abs(cambio).toFixed(2) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 bg-white p-4 d-flex gap-3 rounded-bottom-4">
            <button type="button" class="btn btn-light fw-bold flex-grow-1 py-3 text-slate-600 rounded-pill shadow-sm" data-bs-dismiss="modal" @click="limpiarPagos">Cancelar</button>
            <button type="button" class="btn btn-theme-green flex-grow-1 py-3 d-flex justify-content-center align-items-center gap-2 rounded-pill shadow" :disabled="cambio < 0" @click="procesarVenta">
              <i class="bi bi-check2-circle fs-4"></i> <span class="fs-5 fw-bold">Finalizar Venta</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DETALLE PRODUCTO -->
    <div class="modal fade custom-modal" id="modalDetalleProducto" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-white border-bottom border-light py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="modal-title fw-bold fs-5 text-slate-800 d-flex align-items-center">
              <i class="bi bi-info-circle-fill me-2 text-theme-blue"></i> Detalle del Producto
            </h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4 bg-app-theme" v-if="productoDetalle">
            <div class="d-flex gap-4 bg-white p-3 rounded-4 shadow-sm">
                <img 
                  :src="productoDetalle.imagen ? (productoDetalle.imagen.startsWith('http') ? productoDetalle.imagen : globalStore.imagenUrlPublica + productoDetalle.imagen) : 'https://via.placeholder.com/150/e0f2fe/0284c7?text=Sin+Img'" 
                  class="img-fluid rounded-3 shadow-sm border border-light" 
                  style="width: 140px; height: 140px; object-fit: cover;"
                  :alt="productoDetalle.nombre"
                >
                <div class="d-flex flex-column justify-content-center">
                    <h4 class="fw-black text-slate-800 mb-2">{{ productoDetalle.nombre }}</h4>
                    <p class="text-slate-500 mb-3 small lh-sm">{{ productoDetalle.descripcion || 'Sin descripción detallada.' }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-light-blue text-theme-blue border-0">ID: {{ productoDetalle.id_presentacion }}</span>
                        <span class="badge bg-light-blue text-theme-blue border-0"><i class="bi bi-upc"></i> {{ productoDetalle.codigo_barras }}</span>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3 g-3">
                <div class="col-md-6">
                    <div class="card bg-white border-0 shadow-sm p-3 rounded-4 h-100">
                        <small class="text-theme-blue fw-bold text-uppercase">Categoría</small>
                        <h6 class="fw-bold text-slate-800 mb-0 mt-1">{{ productoDetalle.categoria || 'N/A' }}</h6>
                        <hr class="my-2 border-light">
                        <small class="text-theme-blue fw-bold text-uppercase">Marca</small>
                        <h6 class="fw-bold text-slate-800 mb-0 mt-1">{{ productoDetalle.marca || 'N/A' }}</h6>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card theme-gradient text-white border-0 shadow-sm p-3 rounded-4 h-100 d-flex flex-column justify-content-center align-items-center text-center">
                        <small class="fw-medium text-white-50 text-uppercase">Stock Disponible</small>
                        <h2 class="fw-black mb-0 mt-1">{{ productoDetalle.stock }}</h2>
                        <div class="bg-white rounded-pill px-4 py-2 mt-3 shadow-sm">
                            <h4 class="fw-black text-theme-green mb-0">${{ Number(productoDetalle.precio).toFixed(2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
          </div>
          <div class="modal-footer border-0 bg-white p-4">
            <button type="button" class="btn btn-theme-green w-100 d-flex justify-content-center align-items-center gap-2 py-3 rounded-pill fw-bold shadow-sm" @click="seleccionarProducto(productoDetalle)">
              <i class="bi bi-cart-plus-fill fs-5"></i> Agregar al Carrito
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- OTROS MODALES (Historial / Pendientes) CON TOQUES DEL TEMA -->
    <div class="modal fade custom-modal" id="modalHistorial" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-white border-bottom border-light py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="modal-title fw-bold fs-5 text-slate-800 d-flex align-items-center">
              <i class="bi bi-clock-history me-2 text-theme-blue"></i> Historial de Ventas
            </h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0 bg-app-theme">
            <div class="table-responsive custom-scrollbar" style="max-height: 60vh;">
              <table class="table table-hover align-middle mb-0 custom-table bg-white">
                <thead class="sticky-top bg-light-blue shadow-sm">
                  <tr>
                    <th class="ps-4 py-3 text-theme-blue">Folio</th>
                    <th class="py-3 text-theme-blue">Fecha</th>
                    <th class="py-3 text-theme-blue">Total</th>
                    <th class="py-3 text-theme-blue">Estado</th>
                    <th class="text-center pe-4 py-3 text-theme-blue">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="venta in historial" :key="venta.id_venta">
                    <td class="ps-4 fw-bold text-slate-800">{{ venta.folio }}</td>
                    <td class="text-muted">{{ new Date(venta.fecha).toLocaleString() }}</td>
                    <td class="fw-black text-theme-green">${{ venta.total }}</td>
                    <td>
                      <span class="badge rounded-pill px-3 py-1 fw-bold" :class="venta.estado === 'completada' ? 'bg-light-blue text-theme-blue' : 'bg-light-orange text-orange'">
                        {{ venta.estado }}
                      </span>
                    </td>
                    <td class="text-center pe-4">
                      <div class="d-flex justify-content-center gap-2">
                        <button class="btn-action-light" @click="verTicket(venta.id_venta)" title="Ver Ticket">
                          <i class="bi bi-receipt text-theme-blue"></i>
                        </button>
                        <button v-if="venta.estado === 'completada'" class="btn-action-light text-orange" @click="cancelarVenta(venta)" title="Cancelar Venta">
                          <i class="bi bi-x-circle-fill"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="historial.length === 0">
                    <td colspan="5" class="text-center py-5 text-muted fs-5 bg-white">
                      <i class="bi bi-inbox fs-1 d-block mb-2 text-slate-300"></i>
                      No hay registros recientes.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL VENTAS PENDIENTES -->
    <div class="modal fade custom-modal" id="modalPendientes" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-white border-bottom border-light py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="modal-title fw-bold fs-5 text-slate-800 d-flex align-items-center">
              <i class="bi bi-folder-check me-2 text-theme-green"></i> Ventas Guardadas
            </h5>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0 bg-app-theme">
            <div class="list-group list-group-flush custom-scrollbar" style="max-height: 60vh; overflow-y: auto;">
              <div v-if="ventasPendientes.length === 0" class="text-center py-5 text-muted bg-white">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-slate-300"></i>
                No hay ventas guardadas activas.
              </div>
              
              <div v-for="vp in ventasPendientes" :key="vp.id_venta_pendiente" class="list-group-item bg-white px-4 py-3 border-bottom border-light d-flex justify-content-between align-items-center hover-bg-light">
                <div>
                  <div class="fw-bold text-theme-blue">Folio #{{ vp.id_venta_pendiente }}</div>
                  <small class="text-slate-500 fw-medium">
                    <i class="bi bi-clock me-1"></i> {{ new Date(vp.fecha_creacion).toLocaleString() }}
                  </small>
                </div>
                <button class="btn btn-sm btn-theme-green rounded-pill px-3 fw-bold shadow-sm" @click="recuperarPendiente(vp.id_venta_pendiente)">
                  <i class="bi bi-arrow-down-circle me-1"></i> Recuperar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- TICKET DE IMPRESIÓN (SIN CAMBIOS DE COLOR PARA IMPRESORAS TÉRMICAS) -->
  <div class="print-only">
    <!-- ... (Contenido del ticket intacto para compatibilidad) ... -->
    <div class="ticket-body" v-if="ticketData">
        <div class="text-center mb-2">
            <div class="fw-bold fs-5 text-uppercase">{{ ticketData.empresa }}</div>
            <div class="small">{{ ticketData.direccion }}</div>
            <div class="small">RUC/RFC: {{ ticketData.ruc }}</div>
        </div>
        <hr class="ticket-hr">
        <div class="small mb-2">
            <div>Folio: <strong>{{ ticketData.folio }}</strong></div>
            <div>Fecha: {{ new Date(ticketData.fecha).toLocaleString('es-MX') }}</div>
            <br>
            <div>Cajero: <strong>{{ ticketData.cajero }}</strong></div>
            <div>Caja: <strong>{{ ticketData.caja }}</strong></div>
            <div>Cliente: <strong>{{ ticketData.cliente }}</strong></div>
        </div>
        <hr class="ticket-hr">
        <table class="w-100 small mb-2">
            <thead>
                <tr class="text-start border-bottom border-dark">
                    <th class="pb-1 w-75">Cant/Desc</th>
                    <th class="pb-1 text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, index) in ticketData.productos" :key="index">
                    <td class="py-1">{{ p.cantidad }}x {{ p.nombre }}<br><small>${{ Number(p.precio).toFixed(2) }} c/u</small></td>
                    <td class="text-end align-top py-1 fw-bold">${{ Number(p.total).toFixed(2) }}</td>
                </tr>
            </tbody>
        </table>
        <hr class="ticket-hr">
        <div class="d-flex justify-content-between small"><span>Subtotal:</span><span>${{ Number(ticketData.totales.subtotal).toFixed(2) }}</span></div>
        <div class="d-flex justify-content-between small"><span>IVA (18%):</span><span>${{ Number(ticketData.totales.impuestos).toFixed(2) }}</span></div>
        <div class="d-flex justify-content-between fw-bold fs-6 mt-1 mb-1"><span>TOTAL:</span><span>${{ Number(ticketData.totales.total).toFixed(2) }}</span></div>
        <hr class="ticket-hr">
        <div v-for="(p, index) in ticketData.pagos" :key="'pag-'+index" class="d-flex justify-content-between small"><span>{{ p.forma }}: <small v-if="p.referencia">({{ p.referencia }})</small></span><span>${{ Number(p.monto).toFixed(2) }}</span></div>
        <div class="d-flex justify-content-between small fw-bold mt-1"><span>Cambio:</span><span>${{ Number(ticketData.cambio).toFixed(2) }}</span></div>
        <div class="text-center mt-4 mb-2 small fw-bold">*** GRACIAS POR SU COMPRA ***</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useGlobalStore } from '@/stores/store.js';

const globalStore = useGlobalStore();
const baseUrl = globalStore.baseUrl;

// =========================================================================
// DATOS DE SESIÓN DINÁMICOS Y CLIENTES
// =========================================================================
const idUsuarioActual = computed(() => globalStore.usuario?.id_usuario || 1);
const idSucursalActual = computed(() => globalStore.sucursal?.id_sucursal || 1);
const idCajaActual = computed(() => globalStore.caja?.id_caja || 1);
const idSesionCajaActual = computed(() => globalStore.sesion_caja?.id_sesion_caja || 1);

const nombreCajero = computed(() => globalStore.usuario?.nombre || 'Cajero Principal');
const nombreCaja = computed(() => globalStore.caja?.nombre || 'Caja 1');

// const clientes = ref([]);
// // const clienteSeleccionado = ref(1); 
// const clientBlockRef  = ref(null);
// const clientInp

const cargarClientes = async () => {
  try {
    const response = await axios.get(`${baseUrl}catalogos/clientes`);
    clientes.value = response.data.data || response.data || [];
  } catch (error) {
    console.error('Error al cargar clientes:', error);
  }
};

const desactivarFocoBootstrap = () => {
  const modalEl = document.getElementById('modalHistorial');
  if (!modalEl) return;
  const modalInstance = bootstrap.Modal.getInstance(modalEl);
  if (!modalInstance) return;
  const originalEnforceFocus = modalInstance._enforceFocus;
  modalInstance._enforceFocus = function() {};
  setTimeout(() => { modalInstance._enforceFocus = originalEnforceFocus; }, 2000);
};

// --- ESTADOS REACTIVOS ---
const codigoBusqueda = ref('');
const mensajeAlerta = ref('');
const historial = ref([]);
const inputBusqueda = ref(null);
const isFullscreen = ref(false);
const indiceSeleccionado = ref(-1);
const todosLosProductos = ref([]);
const productosRapidos = computed(() => todosLosProductos.value.slice(0, 8));
const mostrarLista = ref(false);
const productosFiltrados = ref([]);
const productoDetalle = ref(null);
let modalDetalleProducto = null;
let modalCobro = null;
let modalHistorial = null;
let modalPendientes = null;

const ventasActivas = ref([{ id: 1, nombre: 'Venta 1', carrito: [], productosBuscados: [] }]);
const ventaActualId = ref(1);
const contadorVentas = ref(1);
const ventaActual = computed(() => ventasActivas.value.find(v => v.id === ventaActualId.value) || { carrito: [], productosBuscados: [] });

const nuevoPagoMetodo = ref(1);
const nuevoPagoMonto = ref(''); 
const pagosMixtos = ref([]);
const datosPagoActual = ref({ referencia: '', autorizacion: '' });
const ticketData = ref(null);
const ventasPendientes = ref([]);

// --- CÁLCULOS ---
const subtotal = computed(() => ventaActual.value.carrito.reduce((acc, item) => acc + (Number(item.precio) * Number(item.cantidad)), 0));
const iva = computed(() => {
  return ventaActual.value.carrito.reduce((acc, item) => {
    const impuestoDelProducto = item.aplica_iva ? (Number(item.precio) * Number(item.cantidad) * 0.18) : 0;
    return acc + impuestoDelProducto;
  }, 0);
});
const total = computed(() => subtotal.value + iva.value);
const totalPagado = computed(() => pagosMixtos.value.reduce((acc, p) => acc + Number(p.monto), 0));
const cambio = computed(() => totalPagado.value - total.value);

const toggleFullscreen = () => {
  if (!document.fullscreenElement) { document.documentElement.requestFullscreen(); isFullscreen.value = true;
  } else { if (document.exitFullscreen) { document.exitFullscreen(); isFullscreen.value = false; } }
};

let timerDebounce = null;
const filtrarProductosDebounced = () => {
  clearTimeout(timerDebounce);
  if (codigoBusqueda.value.trim().length < 2) {
    mostrarLista.value = false;
    productosFiltrados.value = [];
    return;
  }
  timerDebounce = setTimeout(async () => {
    try {
      const response = await axios.get(`${baseUrl}catalogos/productos/buscar`, {
        params: { q: codigoBusqueda.value } 
      });
      productosFiltrados.value = response.data.data || [];
      mostrarLista.value = productosFiltrados.value.length > 0;
      indiceSeleccionado.value = -1; 
    } catch (error) {
      console.error('Error buscando productos', error);
    }
  }, 400); 
};

const navegarLista = (direccion) => {
  if (!mostrarLista.value || productosFiltrados.value.length === 0) return;
  indiceSeleccionado.value += direccion;
  if (indiceSeleccionado.value < 0) {
    indiceSeleccionado.value = productosFiltrados.value.length - 1;
  } else if (indiceSeleccionado.value >= productosFiltrados.value.length) {
    indiceSeleccionado.value = 0;
  }
};

const manejarEnterInput = () => {
  if (mostrarLista.value && productosFiltrados.value.length > 0) {
    const index = indiceSeleccionado.value >= 0 ? indiceSeleccionado.value : 0;
    seleccionarProducto(productosFiltrados.value[index]);
  } else {
    manejarEnterBusqueda(codigoBusqueda.value);
  }
};

const manejarEnterBusqueda = async (codigoManual = null) => {
  const codigo = codigoManual || codigoBusqueda.value.trim();
  if (!codigo) return;
  mensajeAlerta.value = '';
  try {
    const response = await axios.get(`${baseUrl}catalogos/productos/buscar-codigo/${codigo}`);
    if (response.data.success && response.data.data) {
      seleccionarProducto(response.data.data);
    } else {
      mostrarAlertaFlotante(`El código ${codigo} no existe.`, 'warning');
      codigoBusqueda.value = '';
    }
  } catch (error) {
    mostrarAlertaFlotante(`El código no existe en el catálogo.`, 'warning');
    codigoBusqueda.value = '';
  }
};

let scanBuffer = '';
let lastKeyTimeScan = 0;

const handleGlobalScan = (event) => {
  const isInputFocused = event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA';
  if (isInputFocused) return;

  const currentTime = Date.now();
  if (currentTime - lastKeyTimeScan > 100) { scanBuffer = ''; }
  lastKeyTimeScan = currentTime;

  if (event.key === 'Enter') {
    if (scanBuffer.length >= 4) { manejarEnterBusqueda(scanBuffer); scanBuffer = ''; }
  } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
    scanBuffer += event.key;
  }
};

const seleccionarProducto = (p) => {
  if (!p) return;
  agregarAEncontrados(p);
  agregarAlCarrito({
    id_presentacion: p.id_presentacion,
    producto: p.nombre,
    nombre: p.nombre,   
    precio: Number(p.precio || p.precio_venta || 0),
    stock: p.stock || 100,
    aplica_iva: Boolean(p.aplica_iva) 
  }, false);
  
  if (modalDetalleProducto) modalDetalleProducto.hide();
  codigoBusqueda.value = '';
  mostrarLista.value = false;
  nextTick(() => { inputBusqueda.value?.focus(); });
};

const ocultarLista = () => { setTimeout(() => { mostrarLista.value = false; }, 200); };
const verDetalleProducto = (p) => { productoDetalle.value = p; modalDetalleProducto.show(); };
const mostrarAlertaFlotante = (mensaje, tipo) => { mensajeAlerta.value = mensaje; setTimeout(() => { mensajeAlerta.value = ''; }, 4000); };

const agregarAEncontrados = (p) => {
  if (!ventaActual.value.productosBuscados) ventaActual.value.productosBuscados = [];
  const existeIndex = ventaActual.value.productosBuscados.findIndex(item => item.id_presentacion === p.id_presentacion);
  if (existeIndex !== -1) ventaActual.value.productosBuscados.splice(existeIndex, 1);
  
  ventaActual.value.productosBuscados.unshift({
    id_presentacion: p.id_presentacion, nombre: p.nombre || p.producto, precio: Number(p.precio || p.precio_venta || 0),
    stock: p.stock || 100, imagen: p.imagen || '', aplica_iva: Boolean(p.aplica_iva) 
  });
  if (ventaActual.value.productosBuscados.length > 4) ventaActual.value.productosBuscados.pop();
};

const eliminarDelCarrito = (index) => {
  const itemEliminado = ventaActual.value.carrito[index];
  ventaActual.value.carrito.splice(index, 1);
  const encontradoIndex = ventaActual.value.productosBuscados.findIndex(p => p.id_presentacion === itemEliminado.id_presentacion);
  if (encontradoIndex !== -1) ventaActual.value.productosBuscados.splice(encontradoIndex, 1);
  nextTick(() => { inputBusqueda.value?.focus(); });
};

const agregarAlCarrito = (prod, esPeso) => {
  if (prod.stock <= 0 && !esPeso) { mostrarAlertaFlotante(`Sin stock disponible.`, 'warning'); return; }
  const carrito = ventaActual.value.carrito;
  const existeIndex = carrito.findIndex(p => p.id_presentacion === prod.id_presentacion);
  
  if (existeIndex !== -1) {
    const itemExistente = carrito.splice(existeIndex, 1)[0];
    if (!esPeso && itemExistente.cantidad < prod.stock) { itemExistente.cantidad++; } 
    else if (esPeso) { Swal.fire({ icon: 'info', text: 'Los productos de báscula se agregan individualmente.', customClass: { popup: 'pos-swal' }}); } 
    else { mostrarAlertaFlotante(`Stock máximo alcanzado.`, 'warning'); }
    carrito.unshift(itemExistente);
  } else {
    carrito.unshift({ 
      id_presentacion: prod.id_presentacion, nombre: esPeso ? prod.nombre : (prod.producto || prod.nombre),
      precio: Number(prod.precio || prod.precio_venta || 0), cantidad: esPeso ? prod.cantidad : 1,
      stock: esPeso ? 9999 : prod.stock, esPeso: esPeso, aplica_iva: Boolean(prod.aplica_iva)
    });
  }
};

const cambiarCantidad = (index, delta) => {
  const nuevaCant = ventaActual.value.carrito[index].cantidad + delta;
  if (nuevaCant > 0) { ventaActual.value.carrito[index].cantidad = nuevaCant; } else { eliminarDelCarrito(index); }
  nextTick(() => { inputBusqueda.value?.focus(); });
};

const abrirModalCobro = () => { pagosMixtos.value = []; nuevoPagoMonto.value = ''; nuevoPagoMetodo.value = 1; limpiarDatosPagoActual(); modalCobro.show(); };
const limpiarDatosPagoActual = () => { datosPagoActual.value = { referencia: '', autorizacion: '' }; };
const agregarPago = () => {
  const monto = parseFloat(nuevoPagoMonto.value);
  if (isNaN(monto) || monto <= 0) return;
  pagosMixtos.value.push({ id_forma_pago: parseInt(nuevoPagoMetodo.value), monto: monto, referencia: datosPagoActual.value.referencia || null, autorizacion: datosPagoActual.value.autorizacion || null });
  nuevoPagoMonto.value = cambio.value < 0 ? Math.abs(cambio.value).toFixed(2) : '0';
  limpiarDatosPagoActual();
};
const quitarPago = (index) => { pagosMixtos.value.splice(index, 1); nuevoPagoMonto.value = '0'; limpiarDatosPagoActual(); };
const limpiarPagos = () => { pagosMixtos.value = []; nuevoPagoMonto.value = ''; limpiarDatosPagoActual(); nextTick(() => { inputBusqueda.value?.focus(); }); };

const procesarVenta = async () => {
  if (cambio.value < 0) return;
  try {
    const payload = {
      id_usuario: idUsuarioActual.value, 
      id_sucursal: idSucursalActual.value, 
      id_caja: idCajaActual.value, 
      id_sesion_caja: idSesionCajaActual.value,
      id_cliente: clienteSeleccionado.value,
      productos: ventaActual.value.carrito.map(p => ({ 
        id_presentacion: p.id_presentacion, cantidad: p.cantidad, precio: p.precio, peso: p.esPeso ? p.cantidad : null 
      })),
      pagos: pagosMixtos.value
    };
    const response = await axios.post(`${baseUrl}ventas`, payload);
    const idVentaGenerada = response.data.data.id_venta; 
    modalCobro.hide();
    
    let htmlContent = `
      <div class="text-center mt-3 mb-4"><h2 class="text-theme-blue display-4 fw-black mb-0">$${Number(response.data.data.cambio).toFixed(2)}</h2><p class="text-muted fw-bold text-uppercase">Cambio a entregar</p></div>
      <div class="text-start p-3 bg-light-blue rounded-4 mt-3 border border-light"><div class="d-flex justify-content-between mb-2"><span>Folio:</span> <b class="text-theme-blue">#${response.data.data.folio}</b></div><div class="d-flex justify-content-between mb-2"><span>Cobrado:</span> <b class="text-theme-green">$${Number(response.data.data.total).toFixed(2)}</b></div></div>`;
    if (response.data.data.abrir_cajon) htmlContent += `<p class="text-theme-green fw-bold mt-3 mb-0"><i class="bi bi-inbox-fill me-1"></i> Cajón abierto</p>`;
    
    Swal.fire({ title: '¡Cobro Exitoso!', html: htmlContent, icon: 'success', confirmButtonText: '<i class="bi bi-printer-fill me-2"></i>Imprimir Ticket', confirmButtonColor: '#0284c7', customClass: { popup: 'pos-swal' }
    }).then((result) => {
      if (result.isConfirmed) { verTicket(idVentaGenerada); }
      actualizarStockLocal();
      ventaActual.value.carrito = []; ventaActual.value.productosBuscados = [];
      if (ventasActivas.value.length > 1) eliminarPestana(ventaActualId.value);
      limpiarPagos();
    });
  } catch (error) { Swal.fire('Error', error.response?.data?.message || 'Error de validación', 'error'); }
};

const actualizarStockLocal = () => {
    ventaActual.value.carrito.forEach(itemVendido => {
        const prodLocal = todosLosProductos.value.find(p => p.id_presentacion === itemVendido.id_presentacion);
        if (prodLocal) prodLocal.stock = prodLocal.stock - itemVendido.cantidad;
        const prodEncontrado = ventaActual.value.productosBuscados.find(p => p.id_presentacion === itemVendido.id_presentacion);
        if (prodEncontrado) prodEncontrado.stock = prodEncontrado.stock - itemVendido.cantidad;
    });
};

const cargarHistorial = async () => {
  try { const response = await axios.get(`${baseUrl}ventas`); historial.value = response.data.data; modalHistorial.show();
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el historial.', customClass: { popup: 'pos-swal' }}); }
};

const verTicket = async (idVenta) => {
  if (!idVenta) return;
  try {
    const response = await axios.get(`${baseUrl}tickets/${idVenta}`);
    ticketData.value = response.data.ticket; 
    await nextTick(); window.print();
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo obtener el ticket.'}); }
};

const guardarVentaPendiente = async () => {
  if (ventaActual.value.carrito.length === 0) return;
  try {
    const payload = { id_usuario: idUsuarioActual.value, id_sesion_caja: idSesionCajaActual.value, datos: { productos: ventaActual.value.carrito, subtotal: subtotal.value, impuestos: iva.value, total: total.value } };
    await axios.post(`${baseUrl}ventas-pendientes`, payload);
    Swal.fire({ icon: 'success', title: 'Venta Guardada', timer: 2000, showConfirmButton: false, customClass: { popup: 'pos-swal' } });
    ventaActual.value.carrito = []; ventaActual.value.productosBuscados = [];
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar la venta.'}); }
};

const cargarPendientes = async () => {
  try { const response = await axios.get(`${baseUrl}ventas-pendientes/sesion/${idSesionCajaActual.value}`); ventasPendientes.value = response.data.data; modalPendientes.show();
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar las ventas guardadas.'}); }
};

const recuperarPendiente = async (id) => {
  try {
    const response = await axios.get(`${baseUrl}ventas-pendientes/${id}/recuperar`);
    ventaActual.value.carrito = response.data.data.datos.productos.map(p => ({ id_presentacion: p.id_presentacion, nombre: p.nombre, precio: Number(p.precio), cantidad: Number(p.cantidad), stock: p.stock || 100, esPeso: p.esPeso || false, aplica_iva: Boolean(p.aplica_iva) }));
    modalPendientes.hide();
    Swal.fire({ icon: 'success', title: 'Venta Recuperada', timer: 1500, showConfirmButton: false, customClass: { popup: 'pos-swal' } });
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo recuperar.'}); }
};

const cancelarVenta = (venta) => {
  desactivarFocoBootstrap();
  Swal.fire({ title: `¿Cancelar Venta?`, icon: 'warning', input: 'text', inputLabel: 'Motivo de cancelación', inputValidator: (v) => !v && '¡Necesitas un motivo!', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#cbd5e1', confirmButtonText: 'Sí, Cancelar', cancelButtonText: 'No', customClass: { popup: 'pos-swal' }, didOpen: () => { setTimeout(() => Swal.getInput()?.focus(), 200); }
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await axios.post(`${baseUrl}cancelaciones`, { id_venta: venta.id_venta, id_usuario: idUsuarioActual.value, motivo: result.value, tipo_cancelacion: 'total', monto_reembolsado: venta.total });
        Swal.fire({ icon: 'success', title: 'Cancelada', timer: 2000, showConfirmButton: false }); cargarHistorial(); 
      } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cancelar.' }); }
    }
  });
};

const abrirNuevaVenta = () => { contadorVentas.value++; const nuevaId = contadorVentas.value; ventasActivas.value.push({ id: nuevaId, nombre: `Venta ${nuevaId}`, carrito: [], productosBuscados: [] }); cambiarVentaActiva(nuevaId); };
const cambiarVentaActiva = (id) => { ventaActualId.value = id; mensajeAlerta.value = ''; mostrarLista.value = false; nextTick(() => { inputBusqueda.value?.focus(); }); };
const cerrarVenta = (id) => {
  if (ventasActivas.value.length === 1) return;
  const venta = ventasActivas.value.find(v => v.id === id);
  if (venta.carrito.length > 0) {
    Swal.fire({ title: '¿Cerrar esta venta?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#cbd5e1', confirmButtonText: 'Cerrar y borrar', cancelButtonText: 'Cancelar', customClass: { popup: 'pos-swal' } }).then((r) => { if (r.isConfirmed) eliminarPestana(id); });
  } else { eliminarPestana(id); }
};
const eliminarPestana = (id) => { ventasActivas.value = ventasActivas.value.filter(v => v.id !== id); if (ventaActualId.value === id) cambiarVentaActiva(ventasActivas.value[ventasActivas.length - 1].id); };

// =========================================================================
// VARIABLES PARA BÚSQUEDA DE CLIENTES (AGREGAR ESTAS LÍNEAS)
// =========================================================================
const clientBlockRef = ref(null);
const clientInputRef = ref(null);
const showClientDropdown = ref(false);
const clientSearchTerm = ref('');
const clientDisplayText = ref('Público General');
const clientHighlightIndex = ref(-1);

// Computed para clientes filtrados
const filteredClients = computed(() => {
  if (!clientSearchTerm.value || clientSearchTerm.value.trim() === '') {
    return clientes.value.slice(0, 50);
  }
  
  const search = clientSearchTerm.value.toLowerCase().trim();
  return clientes.value.filter(c => {
    const nombre = (c.nombre_comercial || '').toLowerCase();
    const rfc = (c.rfc || '').toLowerCase();
    const email = (c.email || '').toLowerCase();
    const telefono = (c.telefono || '').toLowerCase();
    
    return nombre.includes(search) || 
           rfc.includes(search) || 
           email.includes(search) || 
           telefono.includes(search);
  }).slice(0, 50);
});

const totalClientsShown = computed(() => {
  return 1 + filteredClients.value.length;
});

// =========================================================================
// FUNCIONES DE BÚSQUEDA DE CLIENTES (AGREGAR ESTAS FUNCIONES)
// =========================================================================
const openClientDropdown = () => {
  showClientDropdown.value = true;
  if (clientDisplayText.value === 'Público General') {
    clientSearchTerm.value = '';
  } else {
    clientSearchTerm.value = clientDisplayText.value;
  }
  clientHighlightIndex.value = -1;
  nextTick(() => {
    if (clientInputRef.value) {
      clientInputRef.value.select();
    }
  });
};

const toggleClientDropdown = () => {
  if (showClientDropdown.value) {
    closeClientDropdown();
  } else {
    openClientDropdown();
  }
};

const onClientInput = (event) => {
  const value = event.target.value;
  clientDisplayText.value = value;
  clientSearchTerm.value = value;
  clientHighlightIndex.value = -1;
  
  if (!showClientDropdown.value) {
    showClientDropdown.value = true;
  }
};

const navigateClients = (direction) => {
  if (!showClientDropdown.value) {
    openClientDropdown();
    return;
  }
  
  const maxIndex = filteredClients.value.length - 1;
  
  if (direction === 1) {
    if (clientHighlightIndex.value < maxIndex) {
      clientHighlightIndex.value++;
    } else {
      clientHighlightIndex.value = -1;
    }
  } else {
    if (clientHighlightIndex.value > -1) {
      clientHighlightIndex.value--;
    } else {
      clientHighlightIndex.value = maxIndex;
    }
  }
  
  scrollToHighlightedClient();
};

const selectHighlightedClient = () => {
  if (clientHighlightIndex.value === -1) {
    selectClient(1, 'Público General');
  } else if (filteredClients.value[clientHighlightIndex.value]) {
    const cliente = filteredClients.value[clientHighlightIndex.value];
    selectClient(cliente.id_cliente, cliente.nombre_comercial);
  }
};

const selectClient = (idCliente, nombreCliente) => {
  clienteSeleccionado.value = idCliente;
  clientDisplayText.value = nombreCliente;
  clientSearchTerm.value = '';
  showClientDropdown.value = false;
  clientHighlightIndex.value = -1;
};

const closeClientDropdown = () => {
  showClientDropdown.value = false;
  clientHighlightIndex.value = -1;
  
  if (clienteSeleccionado.value === 1) {
    clientDisplayText.value = 'Público General';
  } else {
    const cliente = clientes.value.find(c => c.id_cliente === clienteSeleccionado.value);
    clientDisplayText.value = cliente ? cliente.nombre_comercial : 'Público General';
  }
};

const scrollToHighlightedClient = () => {
  nextTick(() => {
    const container = document.querySelector('.client-dropdown-scroll');
    if (!container) return;
    
    const highlighted = container.querySelector('.client-dropdown-item.highlighted');
    if (highlighted) {
      highlighted.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  });
};

const handleClientClickOutside = (event) => {
  if (showClientDropdown.value && clientBlockRef.value && !clientBlockRef.value.contains(event.target)) {
    closeClientDropdown();
  }
};


// --- CICLO DE VIDA ---
onMounted(async () => {
  window.addEventListener('keydown', handleGlobalScan);
  cargarClientes();
  
  modalCobro = new bootstrap.Modal(document.getElementById('modalCobro')); modalHistorial = new bootstrap.Modal(document.getElementById('modalHistorial'));
  modalDetalleProducto = new bootstrap.Modal(document.getElementById('modalDetalleProducto')); modalPendientes = new bootstrap.Modal(document.getElementById('modalPendientes'));

  const savedVentas = localStorage.getItem('pos_ventas_activas'); if (savedVentas) try { ventasActivas.value = JSON.parse(savedVentas); } catch(e) {}
  const savedVentaId = localStorage.getItem('pos_venta_actual_id'); if (savedVentaId) ventaActualId.value = JSON.parse(savedVentaId);

  document.addEventListener('click', handleClientClickOutside);

  watch(ventasActivas, (newVal) => { localStorage.setItem('pos_ventas_activas', JSON.stringify(newVal)); }, { deep: true });
  watch(ventaActualId, (newVal) => { localStorage.setItem('pos_venta_actual_id', JSON.stringify(newVal)); });

  try {
    const response = await axios.get(`${baseUrl}catalogos/productos?pos=true`);
    todosLosProductos.value = response.data.data || [];
  } catch (error) { console.error('Error catálogo inicial', error); }
  
  nextTick(() => { inputBusqueda.value?.focus(); });
});

onUnmounted(() => { window.removeEventListener('keydown', handleGlobalScan); document.removeEventListener('click', handleClientClickOutside); });
</script>

<style scoped>
/* ==========================================================================
   VISIBILIDAD IMPRESIÓN VS PANTALLA
   ========================================================================== */
@media screen { .print-only { display: none !important; } }
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; margin: 0; padding: 0; }
    .print-only { display: block !important; width: 58mm !important; max-width: 58mm !important; margin: 0 !important; padding: 2mm !important; }
    .ticket-receipt { font-family: 'Courier New', Courier, monospace !important; color: #000000 !important; font-size: 10px !important; line-height: 1.3 !important; font-weight: 700 !important; }
    .t-center { text-align: center; } .t-large { font-size: 12px !important; } .t-bold { font-weight: 700 !important; }
    .t-row { display: flex !important; justify-content: space-between !important; width: 100% !important; }
    .t-item { margin-bottom: 4px !important; }
    .t-separator { border-top: 1px dashed #000000 !important; margin: 4px 0 !important; }
    .ticket-hr { border-top: 1px dashed #000 !important; opacity: 1 !important; margin: 8px 0; }
}

/* ==========================================================================
   PALETA DE COLORES - TEMA AZUL / VERDE MENTA
   ========================================================================== */
.pos-app-container { font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1e293b; }
.bg-app-theme { background-color: #f8fafc; } /* Fondo principal súper claro */
.bg-white { background-color: #ffffff; }

/* Variables de Texto */
.text-slate-800 { color: #1e293b !important; } /* Texto Oscuro */
.text-slate-600 { color: #475569 !important; }
.text-slate-500 { color: #64748b !important; }
.text-slate-300 { color: #cbd5e1 !important; }
.text-theme-blue { color: #0284c7 !important; } /* Azul Principal */
.text-theme-green { color: #059669 !important; } /* Verde Principal */
.text-orange { color: #ea580c !important; } /* Naranja Alertas */

/* Variables de Fondo Suaves */
.bg-light-blue { background-color: #f0f9ff !important; }
.bg-light-green { background-color: #ecfdf5 !important; }
.bg-light-orange { background-color: #fff7ed !important; }
.bg-light-gray { background-color: #f1f5f9 !important; }

/* Bordes */
.border-light { border-color: #e2e8f0 !important; }
.border-theme-blue { border-color: #0284c7 !important; }
.border-dashed { border: 2px dashed #cbd5e1 !important; }

/* Gradientes Océano/Menta */
.theme-gradient { background: linear-gradient(135deg, #0284c7 0%, #0d9488 100%); }
.theme-gradient-light { background: linear-gradient(135deg, #e0f2fe 0%, #ccfbf1 100%); }

.tracking-wider { letter-spacing: 0.05em; }
.fw-black { font-weight: 800 !important; }

/* ==========================================================================
   COMPONENTES DE LA CABECERA
   ========================================================================== */
.border-bottom-theme { border-bottom: 1px solid #e2e8f0; }

.btn-top-outline { border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 12px; padding: 10px 18px; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; }
.btn-top-outline:hover { border-color: #0284c7; background: #f0f9ff; color: #0284c7; }

.btn-icon-top { border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 12px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-size: 1.1rem; }
.btn-icon-top:hover { border-color: #0284c7; background: #f0f9ff; color: #0284c7; }

/* ==========================================================================
   PESTAÑAS DE VENTA
   ========================================================================== */
.sales-tabs { max-width: 600px; }
.sale-tab-btn { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; color: #64748b; white-space: nowrap; transition: all 0.2s ease; font-size: 0.95rem; }
.sale-tab-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.sale-tab-btn.active { background: #f0f9ff; border-color: #0284c7; color: #0284c7; }
.tab-close-btn { font-size: 1.2rem; border-radius: 6px; padding: 0 4px; transition: 0.2s; color: #94a3b8; }
.tab-close-btn:hover { background: #fee2e2; color: #ef4444; }

.btn-new-sale { background: transparent; border: 2px dashed #cbd5e1; border-radius: 10px; padding: 8px 14px; transition: 0.2s; }
.btn-new-sale:hover { background: #f0f9ff; border-color: #0284c7; }

/* ==========================================================================
   BARRA DE BÚSQUEDA Y AUTOCOMPLETADO
   ========================================================================== */
.search-bar-wrapper { transition: all 0.3s ease; }
.search-bar-wrapper:focus-within { border-color: #0284c7 !important; box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1) !important; }
.custom-search-input:focus { outline: none; box-shadow: none; border: none; }
.btn-search-theme { background-color: #0284c7; color: white; border: none; padding: 12px 28px; font-weight: 700; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.3); }
.btn-search-theme:hover { background-color: #0369a1; transform: translateY(-1px); }

.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; max-height: 350px; overflow-y: auto; }
.autocomplete-item { cursor: pointer; transition: background 0.2s ease; }
.autocomplete-item:hover { background-color: #f0f9ff !important; }

/* ==========================================================================
   GRID DE ATAJOS RÁPIDOS Y RESULTADOS
   ========================================================================== */
.quick-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; align-content: start; }
.quick-item-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; display: flex; align-items: center; cursor: pointer; transition: 0.2s ease; }
.quick-item-card:hover { border-color: #0284c7; box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.1) !important; transform: translateY(-3px); }
.card-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }

.search-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.search-result-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; display: flex; align-items: center; gap: 16px; transition: all 0.3s ease; }
.search-result-card:hover { border-color: #0284c7; box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.1) !important; transform: translateY(-3px); }
.result-img { width: 75px; height: 75px; object-fit: cover; border-radius: 12px; border: 1px solid #f1f5f9; flex-shrink: 0; }
.result-info { display: flex; flex-direction: column; overflow: hidden; width: 100%; }

/* ==========================================================================
   CONTROLES DE CANTIDAD Y CARRITO
   ========================================================================== */
.cart-panel { border-radius: 1.5rem; }
.cart-item-row { transition: background 0.2s; }
.cart-item-row:hover { background-color: #f8fafc; }
.qty-controller { height: 30px; }
.qty-btn { border: none; background: transparent; padding: 4px 10px; transition: 0.2s; font-size: 1rem; }
.qty-btn:hover { background-color: #e2e8f0; }

.btn-remove-hover { transition: 0.2s; }
.btn-remove-hover:hover { background-color: #ea580c !important; color: white !important; }

/* ==========================================================================
   BOTONES DE ACCIÓN PRINCIPAL (GUARDAR Y COBRAR)
   ========================================================================== */
.btn-guardar-venta { border: 2px solid #e0f2fe; background: #ffffff; color: #0284c7; padding: 10px; transition: 0.2s; }
.btn-guardar-venta:hover:not(:disabled) { background: #f0f9ff; border-color: #0284c7; }
.btn-guardar-venta:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-proceder-cobro { border: none; background: #10b981; color: white; padding: 10px; transition: 0.3s ease; }
.btn-proceder-cobro:hover:not(:disabled) { background: #059669; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3) !important; }
.btn-proceder-cobro:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; background: #cbd5e1; color: #f8fafc; }

/* Botones Auxiliares */
.btn-theme-green { background-color: #10b981; color: white; transition: 0.2s; border: none; }
.btn-theme-green:hover:not(:disabled) { background-color: #059669; color: white; transform: translateY(-1px); }

/* ==========================================================================
   TABLAS Y MODALES
   ========================================================================== */
.custom-table th { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
.btn-action-light { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
.btn-action-light:hover { background: #e2e8f0; border-color: #cbd5e1; }

.pos-swal { border-radius: 1.5rem !important; }

/* SCROLLBAR MODERNO */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.custom-scrollbar-hidden::-webkit-scrollbar { display: none; }
.custom-scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }

/* ==========================================================================
   HEADER MODERNO REDISTRIBUIDO
   ========================================================================== */
.pos-header-modern {
  display: flex;
  align-items: center;
  padding: 0 30px;
  background: white;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  border-bottom: 2px solid #e2e8f0;
  height: 100px;
  gap: 0;
}

/* BLOQUE 1: LOGO */
.header-block-logo {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 12px;
}

.brand-icon-modern {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;
}

.logo-text h4 {
  font-size: 1.1rem;
  line-height: 1.2;
}

.logo-text small {
  font-size: 0.75rem;
}

/* DIVISORES */
.header-divider-modern {
  width: 1px;
  height: 40px;
  background: #e2e8f0;
  margin: 0 16px;
  flex-shrink: 0;
}

/* BLOQUE 2: CLIENTE */
.header-block-client {
  flex-shrink: 0;
}

.client-select-modern {
  display: flex;
  align-items: center;
  background: #eff6ff;
  border: 1.5px solid #e2e8f0;
  border-radius: 50px;
  overflow: hidden;
  height: 38px;
  transition: all 0.2s ease;
}

.client-select-modern:focus-within {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.client-select-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px 0 12px;
  color: #3b82f6;
}

.client-select-input {
  border: none;
  background: transparent;
  font-size: 0.85rem;
  font-weight: 600;
  color: #1e293b;
  padding: 0 12px 0 0;
  height: 100%;
  cursor: pointer;
  outline: none;
  min-width: 180px;
  max-width: 220px;
}

.client-select-input:focus {
  box-shadow: none;
}

/* BLOQUE 3: PESTAÑAS */
.header-block-tabs {
  flex-grow: 1;
  overflow: hidden;
  display: flex;
  align-items: center;
}

.sales-tabs-modern {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 8px 4px;
  width: 100%;
  align-items: center;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sales-tabs-modern::-webkit-scrollbar {
  display: none;
}

.sale-tab-btn-modern {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 50px;
  background: white;
  color: #64748b;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
  white-space: nowrap;
}

.sale-tab-btn-modern:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #f0f9ff;
}

.sale-tab-btn-modern.active {
  background: linear-gradient(135deg, #3b82f6, #06b6d4);
  border-color: transparent;
  color: white;
  box-shadow: 0 3px 12px rgba(59, 130, 246, 0.35);
}

.sale-tab-btn-modern.active i {
  color: white !important;
}

.tab-badge-modern {
  background: rgba(255, 255, 255, 0.3);
  color: white;
  border-radius: 50px;
  padding: 1px 6px;
  font-size: 0.65rem;
  font-weight: 700;
}

.sale-tab-btn-modern.active .tab-badge-modern {
  background: white;
  color: #3b82f6;
}

.tab-close-modern {
  opacity: 0.6;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}

.tab-close-modern:hover {
  opacity: 1;
  color: #ef4444 !important;
}

.btn-new-sale-modern {
  width: 32px;
  height: 32px;
  border: 2px dashed #3b82f6;
  border-radius: 50%;
  background: transparent;
  color: #3b82f6;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.btn-new-sale-modern:hover {
  background: #3b82f6;
  color: white;
  border-style: solid;
}

/* BLOQUE 4: USUARIO */
.header-block-user {
  flex-shrink: 0;
}

.user-info-modern {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.user-name-modern {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
}

.user-box-modern {
  font-size: 0.7rem;
  font-weight: 600;
  color: #10b981;
  background: #ecfdf5;
  padding: 2px 8px;
  border-radius: 50px;
}

/* BLOQUE 5: ACCIONES */
.header-block-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.btn-icon-modern {
  width: 36px;
  height: 36px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.btn-icon-modern:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #f0f9ff;
}

.btn-action-modern {
  display: flex;
  align-items: center;
  padding: 6px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #475569;
  font-weight: 600;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-action-modern:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #f0f9ff;
}
</style>