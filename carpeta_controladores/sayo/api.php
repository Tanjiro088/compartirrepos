<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepartamentosController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\MarcasController;
use App\Http\Controllers\UnidadesMedidaController;
use App\Http\Controllers\TiposPresentacionController;
use App\Http\Controllers\EmpresasController;
use App\Http\Controllers\SucursalesController;
use App\Http\Controllers\AlmacenesController;
use App\Http\Controllers\CajasController;
use App\Http\Controllers\FoliosController;
use App\Http\Controllers\ParametrosController;
use App\Http\Controllers\ListasPreciosController;
use App\Http\Controllers\UsuariosController;
use App\Http\Controllers\RolesPermisosController;
use App\Http\Controllers\ProductosController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Endpoint específico para que otros usuarios consuman solo ID y Nombre
Route::get('departamentos/lista', [DepartamentosController::class, 'listaBasica']);

// Crea automáticamente las rutas GET, POST, PUT y DELETE
Route::apiResource('departamentos', DepartamentosController::class);
Route::apiResource('categorias', CategoriasController::class);
Route::apiResource('marcas', MarcasController::class);
Route::apiResource('unidades-medida', UnidadesMedidaController::class);
Route::apiResource('tipos-presentacion', TiposPresentacionController::class);
Route::apiResource('empresas', EmpresasController::class);
Route::apiResource('sucursales', SucursalesController::class);
Route::apiResource('almacenes', AlmacenesController::class);
Route::apiResource('cajas', CajasController::class);
Route::apiResource('folios', FoliosController::class);
Route::apiResource('parametros', ParametrosController::class);
Route::apiResource('listas-precios', ListasPreciosController::class);
Route::apiResource('usuarios', UsuariosController::class);
Route::apiResource('roles', RolesPermisosController::class);
Route::apiResource('productos', ProductosController::class);