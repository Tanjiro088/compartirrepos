<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Catalogos\ProveedoresController;
use App\Http\Controllers\Catalogos\ComprasController;
use App\Http\Controllers\Catalogos\CuentasPorPagarController;
use App\Http\Controllers\Catalogos\MermasController;
use App\Http\Controllers\Catalogos\ProductosController;
use App\Http\Controllers\Catalogos\AlmacenesController;

// ============================================================================
// MÓDULO: PROVEEDORES
// ============================================================================
Route::prefix('proveedores')->group(function () {
    Route::get('/',       [ProveedoresController::class, 'index']);
    Route::post('/',      [ProveedoresController::class, 'store']);
    Route::put('/{id}',   [ProveedoresController::class, 'update']);
    Route::delete('/{id}', [ProveedoresController::class, 'destroy']);
    Route::patch('/{id}/calificar', [ProveedoresController::class, 'calificar']);
    Route::patch('/{id}/estado',    [ProveedoresController::class, 'estado']);
});

// ============================================================================
// MÓDULO: COMPRAS
// ============================================================================
Route::prefix('compras')->group(function () {
    Route::get('/',                      [ComprasController::class, 'index']);
    Route::get('/next-folio',            [ComprasController::class, 'nextFolio']);
    Route::post('/',                     [ComprasController::class, 'store']);
    Route::put('/{id}',                  [ComprasController::class, 'update']);
    Route::patch('/{id}/recepcion',      [ComprasController::class, 'registrarRecepcion']);
    Route::get('/disponibles-devolucion',[MermasController::class, 'comprasDisponiblesDevolucion']);
});

// ============================================================================
// MÓDULO: CUENTAS POR PAGAR
// ============================================================================
Route::prefix('cuentas')->group(function () {
    Route::get('/',             [CuentasPorPagarController::class, 'index']);
    Route::post('/{id}/abonar', [CuentasPorPagarController::class, 'abonar']);
});

// ============================================================================
// MÓDULO: MERMAS
// ============================================================================
Route::prefix('mermas')->group(function () {
    Route::get('/',                  [MermasController::class, 'index']);
    Route::get('/pendientes',        [MermasController::class, 'pendientes']);
    Route::get('/reporte-perdidas',  [MermasController::class, 'reportePerdidas']);
    Route::get('/exportar',          [MermasController::class, 'exportar']);
    Route::get('/next-folio',        [MermasController::class, 'nextFolio']);
    Route::post('/',                 [MermasController::class, 'store']);
    Route::get('/{id}',             [MermasController::class, 'show']);
    Route::put('/{id}',             [MermasController::class, 'update']);
    Route::patch('/{id}/estado',     [MermasController::class, 'actualizarEstado']);
});

// ============================================================================
// MÓDULO: PRODUCTOS
// ============================================================================
Route::prefix('productos-modulo')->group(function () {
    Route::get('/', [ProductosController::class, 'index']);
});

// ============================================================================
// MÓDULO: ALMACENES
// ============================================================================
Route::prefix('almacenes')->group(function () {
    Route::get('/', [AlmacenesController::class, 'index']);
});
