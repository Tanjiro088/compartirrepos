import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import ComprasView from './ComprasView.vue';

describe('ComprasView.vue', () => {
    it('debería renderizar el título del módulo', () => {
        const wrapper = mount(ComprasView);
        expect(wrapper.text()).toContain('Módulo de Compras');
    });

    it('debería tener el botón de registrar compra', () => {
        const wrapper = mount(ComprasView);
        const button = wrapper.find('button.btn-outline-primary');
        expect(button.exists()).toBe(true);
    });
});