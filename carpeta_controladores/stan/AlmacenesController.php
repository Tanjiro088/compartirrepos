<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de almacenes para selects de compras/mermas/devoluciones.
 * No sustituye el CRUD de almacenes de la unidad propietaria.
 */
class AlmacenesController extends Controller
{
    /**
     * Listar almacenes operativos (no eliminados y activos).
     * Sin JOIN a sucursales para no excluir filas válidas.
     */
    public function index()
    {
        $almacenes = DB::select('
            SELECT
                a.id_almacen,
                a.nombre
            FROM almacenes a
            WHERE a.deleted_at IS NULL
              AND a.activo = 1
            ORDER BY a.nombre ASC
        ');

        return response()->json($almacenes, 200);
    }
}
