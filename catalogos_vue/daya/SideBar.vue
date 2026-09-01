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
  <aside class="sidebar">
    <div class="brand">
      <i class="bi bi-diagram-3-fill" style="color:var(--brand)"></i>
      <div>MÓDULOS<small>RH · Promociones · Garantías</small></div>
    </div>
    <template v-for="grp in nav" :key="grp.g">
      <div class="nav-group">{{ grp.g }}</div>
      <router-link
        v-for="it in grp.items"
        :key="it.path"
        :to="it.path"
        class="nav-item"
        active-class="active"
        :data-testid="'nav-' + it.key"
      >
        <i :class="'bi ' + it.icon"></i>{{ it.label }}
      </router-link>
    </template>
  </aside>
</template>