<script setup>
// Menú lateral de navegación. Cada módulo (controlador) tiene su propia ruta.
const nav = [
  {
    g: 'Recursos Humanos',
    items: [
      { key: 'personas', path: '/personas', label: 'Personas', icon: 'bi-person-badge' },
      { key: 'empleados', path: '/empleados', label: 'Empleados', icon: 'bi-people' },
      { key: 'puestos', path: '/puestos', label: 'Puestos', icon: 'bi-briefcase' },
      { key: 'nominas', path: '/nominas', label: 'Nómina', icon: 'bi-cash-stack' },
      { key: 'anticipos', path: '/anticipos', label: 'Anticipos', icon: 'bi-wallet2' },
      { key: 'prestamos', path: '/prestamos', label: 'Préstamos', icon: 'bi-bank' },
      { key: 'descuentos', path: '/descuentos', label: 'Descuentos automáticos', icon: 'bi-scissors' },
      { key: 'historial', path: '/historial', label: 'Historial de pagos', icon: 'bi-clock-history' },
    ],
  },
  {
    g: 'Promociones',
    items: [
      { key: 'promociones', path: '/promociones', label: 'Promociones', icon: 'bi-megaphone' },
      { key: 'combos', path: '/combos', label: 'Combos', icon: 'bi-box-seam' },
    ],
  },
  {
    g: 'Garantías',
    items: [
      { key: 'garantias', path: '/garantias', label: 'Garantías', icon: 'bi-shield-check' },
      { key: 'series', path: '/series', label: 'Series de producto', icon: 'bi-upc-scan' },
      { key: 'reporte-garantias', path: '/reporte-garantias', label: 'Reporte de garantías', icon: 'bi-bar-chart' },
    ],
  },
];
</script>

<template>
  <aside class="d-flex flex-column flex-shrink-0 p-3 bg-light border-end" style="width: 280px; min-height: 100vh;">
    <div class="d-flex align-items-center gap-2 mb-4 px-2 text-decoration-none">
      <i class="bi bi-diagram-3-fill fs-4 text-primary"></i>
      <div class="fw-bold lh-sm text-dark">
        MÓDULOS
        <small class="d-block text-muted fw-normal" style="font-size: 0.75rem;">RH · Promociones · Garantías</small>
      </div>
    </div>
    
    <nav class="nav flex-column gap-1">
      <template v-for="grp in nav" :key="grp.g">
        <div class="text-uppercase text-muted fw-bold px-2 py-1 mt-3 mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">
          {{ grp.g }}
        </div>
        <router-link
          v-for="it in grp.items"
          :key="it.path"
          :to="it.path"
          class="nav-link d-flex align-items-center gap-2 py-2 px-3 rounded text-dark"
          active-class="active bg-primary text-white shadow-sm"
          :data-testid="'nav-' + it.key"
        >
          <i :class="['bi', it.icon, 'fs-5']"></i>
          <span>{{ it.label }}</span>
        </router-link>
      </template>
    </nav>
  </aside>
</template>

<style scoped>
.nav-link:hover:not(.active) {
  background-color: rgba(0, 0, 0, 0.04);
}
</style>
