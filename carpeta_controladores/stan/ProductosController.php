<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador: ProductosController
 * Propósito: Exponer el catálogo de presentaciones_producto para
 *            los buscadores predictivos del frontend (mermas, compras).
 */
class ProductosController extends Controller
{
    /**
     * Método: index
     * Propósito: Listar todos los productos activos con su precio de costo.
     */
    public function index(Request $request)
    {
        $productos = DB::select('
            SELECT
                id_presentacion AS id,
                nombre,
                costo_promedio,
                activa
            FROM presentaciones_producto
            WHERE activa = 1
            ORDER BY nombre ASC
        ');

        foreach ($productos as $p) {
            $p->costo_promedio = (float) $p->costo_promedio;
            $p->activa         = (bool) $p->activa;
        }

        return response()->json($productos, 200);
    }
}
