<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaController extends Controller
{
    /**
     * Consultar todas las unidades de medida activas.
     */
    public function index()
    {
        $unidades = DB::select('
            SELECT * FROM unidades_medida 
            WHERE deleted_at IS NULL 
            ORDER BY id_unidad_medida DESC
        ');

        return response()->json($unidades, 200);
    }

    /**
     * Guardar una nueva unidad de medida.
     */
    public function store(Request $request)
    {
        $request->validate([
            'codigo'      => 'required|string|max:10',
            'nombre'      => 'required|string|max:50',
            'abreviatura' => 'required|string|max:10',
            'activo'      => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO unidades_medida (codigo, nombre, abreviatura, activo, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->codigo,
            $request->nombre,
            $request->abreviatura,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Unidad de medida registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la unidad de medida.'], 500);
    }

    /**
     * Mostrar una unidad específica.
     */
    public function show($id_unidad_medida)
    {
        $unidad = DB::select('
            SELECT * FROM unidades_medida 
            WHERE id_unidad_medida = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_unidad_medida]);

        if (empty($unidad)) {
            return response()->json(['error' => 'Unidad no encontrada o inactiva.'], 404);
        }

        return response()->json($unidad[0], 200);
    }

    /**
     * Actualizar los datos de una unidad de medida.
     */
    public function update(Request $request, $id_unidad_medida)
    {
        $request->validate([
            'codigo'      => 'required|string|max:10',
            'nombre'      => 'required|string|max:50',
            'abreviatura' => 'required|string|max:10',
            'activo'      => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_unidad_medida FROM unidades_medida WHERE id_unidad_medida = ? AND deleted_at IS NULL', [$id_unidad_medida]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Unidad de medida no encontrada.'], 404);
        }

        DB::update('
            UPDATE unidades_medida 
            SET codigo = ?, nombre = ?, abreviatura = ?, activo = ?, updated_at = NOW() 
            WHERE id_unidad_medida = ?
        ', [
            $request->codigo,
            $request->nombre,
            $request->abreviatura,
            $request->activo,
            $id_unidad_medida
        ]);

        return response()->json(['mensaje' => "Unidad de medida actualizada con éxito."], 200);
    }

    /**
     * Eliminar una unidad de medida (Borrado Lógico).
     */
    public function destroy($id_unidad_medida)
    {
        $existe = DB::select('SELECT id_unidad_medida FROM unidades_medida WHERE id_unidad_medida = ? AND deleted_at IS NULL', [$id_unidad_medida]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Unidad no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE unidades_medida 
            SET deleted_at = NOW(), activo = false 
            WHERE id_unidad_medida = ?
        ', [$id_unidad_medida]);

        return response()->json(['mensaje' => "Unidad eliminada lógicamente."], 200);
    }
}