<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { jsPDF } from 'jspdf';
import { money } from '../helpers';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import TableToolbar from '../components/TableToolbar.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const nominas = ref([]);
const cat = reactive({ usuarios: [], formas_pago: [] });
const loading = ref(false);
const modal = ref(false);
const modalTitle = ref('');
const form = reactive({});

const headers = ['Empleado', 'Periodo', 'Ingresos', 'Descuentos', 'Neto', 'Estado', ''];

// Búsqueda y filtro por estado.
const q = ref('');
const estadoFilter = ref('');
const estadosNomina = [
  { value: 'generada', label: 'Generada' },
  { value: 'pagada', label: 'Pagada' },
  { value: 'anulada', label: 'Anulada' },
];
const nominasFiltradas = computed(() => {
  const term = q.value.trim().toLowerCase();
  return nominas.value.filter((n) => {
    const coincide = !term || [n.empleado, n.numero_empleado, n.periodo]
      .some((v) => String(v || '').toLowerCase().includes(term));
    const estadoOk = !estadoFilter.value || n.estado === estadoFilter.value;
    return coincide && estadoOk;
  });
});

const loadCat = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/catalogos`);
    Object.assign(cat, data);
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(`${global.baseUrl}/nominas`);
    nominas.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

// Ajuste manual de nómina (una por empleado).
const verNomina = async (n) => {
  try {
    const { data: d } = await axios.get(`${global.baseUrl}/nominas/${n.id_nomina}`);
    Object.keys(form).forEach((k) => delete form[k]);
    Object.assign(form, d);
    modalTitle.value = 'Nómina · ' + (n.empleado || '');
    modal.value = true;
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const guardar = async () => {
  try {
    await axios.put(`${global.baseUrl}/nominas/${form.id_nomina}`, form);
    toast.value.notify('Nómina actualizada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const pagarNomina = async (n) => {
  try {
    const response = await axios.post(`${global.baseUrl}/nominas/${n.id_nomina}/pagar`, { 
      referencia: 'PAGO-' + n.id_nomina 
    });
    toast.value.notify('Nómina pagada');
    load();
  } catch (e) {
    // Imprimimos el error real que viene del servidor Laravel en la consola
    console.error("Detalle del error del servidor:", e.response?.data || e.message);
    toast.value.apiErr(e);
  }
};

// Genera el recibo de nómina en PDF del lado del cliente (jsPDF).
const reciboNomina = async (n) => {
  try {
    const { data: d } = await axios.get(`${global.baseUrl}/nominas/${n.id_nomina}`);
    const doc = new jsPDF();
    const L = 18;
    let y = 20;
    doc.setFillColor(47, 111, 91);
    doc.rect(0, 0, 210, 26, 'F');
    doc.setTextColor(255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text('DAYARI ERP', L, 15);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Recibo de Nómina', L, 21);
    doc.setTextColor(30);
    y = 38;
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text(`Empleado: ${n.empleado || ''}`, L, y);
    y += 6;
    doc.setFont('helvetica', 'normal');
    doc.text(`N° Empleado: ${n.numero_empleado || '—'}`, L, y);
    doc.text(`Periodo: ${d.periodo}`, 120, y);
    y += 6;
    doc.text(`Fecha de pago: ${d.fecha_pago}`, L, y);
    doc.text(`Estado: ${d.estado}`, 120, y);
    y += 6;
    doc.text(`Recibo N°: NOM-${String(d.id_nomina).padStart(5, '0')}`, L, y);
    y += 10;
    doc.setDrawColor(220);
    doc.line(L, y, 192, y);
    y += 8;
    const row = (a, b, neg) => {
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(neg ? 192 : 30, neg ? 57 : 30, neg ? 44 : 30);
      doc.text(a, L, y);
      doc.text((neg ? '-$' : '$') + Number(b || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 }), 192, y, { align: 'right' });
      doc.setTextColor(30);
      y += 6;
    };
    doc.setFont('helvetica', 'bold');
    doc.text('PERCEPCIONES', L, y);
    y += 6;
    row('Salario base', d.salario_base);
    if (+d.bonificaciones) row('Bonificaciones', d.bonificaciones);
    if (+d.comisiones) row('Comisiones', d.comisiones);
    if (+d.horas_extras) row('Horas extras', d.horas_extras);
    if (+d.otros_ingresos) row('Otros ingresos', d.otros_ingresos);
    doc.setFont('helvetica', 'bold');
    row('Total ingresos', d.total_ingresos);
    y += 2;
    doc.setFont('helvetica', 'bold');
    doc.text('DEDUCCIONES', L, y);
    y += 6;
    if (+d.descuentos_legales) row('ISR / legales', d.descuentos_legales, true);
    if (+d.descuentos_prestamos) row('Préstamos', d.descuentos_prestamos, true);
    if (+d.descuentos_anticipos) row('Anticipos', d.descuentos_anticipos, true);
    if (+d.otros_descuentos) row('Otros descuentos', d.otros_descuentos, true);
    doc.setFont('helvetica', 'bold');
    row('Total descuentos', d.total_descuentos, true);
    y += 4;
    doc.setDrawColor(220);
    doc.line(L, y, 192, y);
    y += 9;
    doc.setFillColor(234, 243, 239);
    doc.rect(L, y - 6, 174, 11, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13);
    doc.setTextColor(47, 111, 91);
    doc.text('NETO A PAGAR', L + 3, y + 1);
    doc.text('$' + Number(d.total_neto).toLocaleString('es-MX', { minimumFractionDigits: 2 }), 189, y + 1, { align: 'right' });
    doc.setTextColor(120);
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.text('Documento generado por DAYARI ERP · ' + new Date().toLocaleString('es-MX'), L, 285);
    doc.save(`recibo_nomina_${d.id_nomina}.pdf`);
    toast.value.notify('Recibo PDF generado');
  } catch (e) {
    toast.value.apiErr(e);
  }
};

onMounted(() => {
  loadCat();
  load();
});
</script>

<template>
  <section class="container-fluid py-4">
    <PageHeader title="Nómina" subtitle="Una nómina por empleado. Anticipos y préstamos se reflejan automáticamente." />

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <template v-else>
      <TableToolbar
        v-model:search="q"
        v-model:estado="estadoFilter"
        placeholder="Buscar por empleado, N° or periodo…"
        :estados="estadosNomina"
        id-prefix="nominas"
      />
      <DataTable :headers="headers" :empty="!nominasFiltradas.length" empty-text="Sin nóminas">
        <tr v-for="n in nominasFiltradas" :key="n.id_nomina">
          <td class="align-middle">
            <div class="fw-semibold">{{ n.empleado }}</div>
            <div class="text-muted small">{{ n.numero_empleado }}</div>
          </td>
          <td class="align-middle">{{ n.periodo }}</td>
          <td class="align-middle fw-semibold">{{ money(n.total_ingresos) }}</td>
          <td class="align-middle text-danger fw-semibold">{{ money(n.total_descuentos) }}</td>
          <td class="align-middle fw-bold">{{ money(n.total_neto) }}</td>
          <td class="align-middle"><StatusBadge :estado="n.estado" /></td>
          <td class="align-middle text-end">
            <button class="btn btn-outline-secondary btn-sm me-1 d-inline-flex align-items-center gap-1" @click="verNomina(n)">
              <i class="bi bi-pencil-square"></i> Ajustar
            </button>
            <button v-if="n.estado === 'pagada'" class="btn btn-outline-secondary btn-sm me-1 d-inline-flex align-items-center gap-1" @click="reciboNomina(n)" data-testid="recibo-btn">
              <i class="bi bi-file-earmark-pdf"></i> Recibo
            </button>
            <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" :disabled="n.estado === 'pagada'" @click="pagarNomina(n)">
              <i class="bi bi-check2"></i> Pagar
            </button>
          </td>
        </tr>
      </DataTable>
    </template>

    <ModalBase v-if="modal" :title="modalTitle" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Periodo</label>
          <input type="text" class="form-control" v-model="form.periodo" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Días laborados</label>
          <input type="number" class="form-control" v-model="form.dias_laborados" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Salario base</label>
          <input type="text" class="form-control" :value="money(form.salario_base)" disabled />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Bonificaciones</label>
          <input type="number" step="0.01" class="form-control" v-model="form.bonificaciones" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Comisiones</label>
          <input type="number" step="0.01" class="form-control" v-model="form.comisiones" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Horas extras</label>
          <input type="number" step="0.01" class="form-control" v-model="form.horas_extras" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Otros ingresos</label>
          <input type="number" step="0.01" class="form-control" v-model="form.otros_ingresos" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Descuentos legales (ISR)</label>
          <input type="number" step="0.01" class="form-control" v-model="form.descuentos_legales" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Desc. préstamos (auto)</label>
          <input type="text" class="form-control" :value="money(form.descuentos_prestamos)" disabled />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Desc. anticipos (auto)</label>
          <input type="text" class="form-control" :value="money(form.descuentos_anticipos)" disabled />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Observaciones</label>
          <textarea class="form-control" v-model="form.observaciones" rows="2"></textarea>
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
