<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriasController extends Controller
{
    /**
     * Consultar todas las categorías activas con su departamento correspondiente.
     */
    public function index()
    {
        $categorias = DB::select('
            SELECT c.*, d.nombre as departamento_nombre 
            FROM categorias c
            INNER JOIN departamentos d ON c.id_departamento = d.id_departamento
            WHERE c.deleted_at IS NULL 
            ORDER BY c.id_categoria DESC
        ');

        return response()->json($categorias, 200);
    }

    /**
     * Guardar una nueva categoría.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_departamento' => 'required|integer',
            'nombre'          => 'required|string|max:100',
            'descripcion'     => 'nullable|string',
            'activo'          => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO categorias (id_departamento, nombre, descripcion, activo, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_departamento,
            $request->nombre,
            $request->descripcion,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Categoría registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la categoría.'], 500);
    }

    /**
     * Mostrar una categoría específica.
     */
    public function show($id_categoria)
    {
        $categoria = DB::select('
            SELECT c.*, d.nombre as departamento_nombre 
            FROM categorias c
            INNER JOIN departamentos d ON c.id_departamento = d.id_departamento
            WHERE c.id_categoria = ? AND c.deleted_at IS NULL 
            LIMIT 1
        ', [$id_categoria]);

        if (empty($categoria)) {
            return response()->json(['error' => 'Categoría no encontrada o inactiva.'], 404);
        }

        return response()->json($categoria[0], 200);
    }

    /**
     * Actualizar los datos de una categoría.
     */
    public function update(Request $request, $id_categoria)
    {
        $request->validate([
            'id_departamento' => 'required|integer',
            'nombre'          => 'required|string|max:100',
            'descripcion'     => 'nullable|string',
            'activo'          => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_categoria FROM categorias WHERE id_categoria = ? AND deleted_at IS NULL', [$id_categoria]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        }

        DB::update('
            UPDATE categorias 
            SET id_departamento = ?, nombre = ?, descripcion = ?, activo = ?, updated_at = NOW() 
            WHERE id_categoria = ?
        ', [
            $request->id_departamento,
            $request->nombre,
            $request->descripcion,
            $request->activo,
            $id_categoria
        ]);

        return response()->json(['mensaje' => "Categoría actualizada con éxito."], 200);
    }

    /**
     * Eliminar una categoría (Borrado Lógico).
     */
    public function destroy($id_categoria)
    {
        $existe = DB::select('SELECT id_categoria FROM categorias WHERE id_categoria = ? AND deleted_at IS NULL', [$id_categoria]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Categoría no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE categorias 
            SET deleted_at = NOW(), activo = false 
            WHERE id_categoria = ?
        ', [$id_categoria]);

        return response()->json(['mensaje' => "Categoría eliminada lógicamente."], 200);
    }
}