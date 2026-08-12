<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarcasController extends Controller
{
    /**
     * Consultar todas las marcas activas.
     */
    public function index()
    {
        $marcas = DB::select('
            SELECT * FROM marcas 
            WHERE deleted_at IS NULL 
            ORDER BY id_marca DESC
        ');

        return response()->json($marcas, 200);
    }

    /**
     * Guardar una nueva marca.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'activo'      => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO marcas (nombre, descripcion, activo, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ', [
            $request->nombre,
            $request->descripcion,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Marca registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la marca.'], 500);
    }

    /**
     * Mostrar una marca específica.
     */
    public function show($id_marca)
    {
        $marca = DB::select('
            SELECT * FROM marcas 
            WHERE id_marca = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_marca]);

        if (empty($marca)) {
            return response()->json(['error' => 'Marca no encontrada o inactiva.'], 404);
        }

        return response()->json($marca[0], 200);
    }

    /**
     * Actualizar los datos de una marca.
     */
    public function update(Request $request, $id_marca)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'activo'      => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_marca FROM marcas WHERE id_marca = ? AND deleted_at IS NULL', [$id_marca]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Marca no encontrada.'], 404);
        }

        DB::update('
            UPDATE marcas 
            SET nombre = ?, descripcion = ?, activo = ?, updated_at = NOW() 
            WHERE id_marca = ?
        ', [
            $request->nombre,
            $request->descripcion,
            $request->activo,
            $id_marca
        ]);

        return response()->json(['mensaje' => "Marca actualizada con éxito."], 200);
    }

    /**
     * Eliminar una marca (Borrado Lógico Manual).
     */
    public function destroy($id_marca)
    {
        $existe = DB::select('SELECT id_marca FROM marcas WHERE id_marca = ? AND deleted_at IS NULL', [$id_marca]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Marca no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE marcas 
            SET deleted_at = NOW(), activo = false 
            WHERE id_marca = ?
        ', [$id_marca]);

        return response()->json(['mensaje' => "Marca eliminada lógicamente."], 200);
    }
}