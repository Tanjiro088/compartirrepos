import { defineStore } from 'pinia';

export const useGlobalStore = defineStore('global', {
    state: () => ({ 
        // ----------------- Base de Servidor ----------------------------------------------------------------
        
        // URL para PRUEBAS LOCALES (Laravel artisan serve)
        // baseUrl: 'http://127.0.0.1:8000/api/',
        // imagenUrlPublica: 'http://127.0.0.1:8000/img/',

        // Comentamos temporalmente las URLs del servidor de producción y red local
        baseUrl: 'http://192.168.200.5/Backend_inventarios/public/api/',
        imagenUrlPublica: 'http://192.168.200.5/Backend_inventarios/public/img/',

        // baseUrl: 'http://sisce.misantla.tecnm.mx/Backend_inventarios/public/api/',
        // imagenUrlPublica: 'http://sisce.misantla.tecnm.mx/Backend_inventarios/public/img/',
     }),
});