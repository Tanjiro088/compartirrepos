<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TiposPresentacionController extends Controller
{
    /**
     * Consultar todos los tipos de presentación activos.
     */
    public function index()
    {
        $tipos = DB::select('
            SELECT * FROM tipos_presentacion 
            WHERE deleted_at IS NULL 
            ORDER BY id_tipo_presentacion DESC
        ');

        return response()->json($tipos, 200);
    }

    /**
     * Guardar un nuevo tipo de presentación.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO tipos_presentacion (nombre, descripcion, activo, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ', [
            $request->nombre,
            $request->descripcion,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Tipo de presentación registrado con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar el tipo de presentación.'], 500);
    }

    /**
     * Mostrar un tipo específico.
     */
    public function show($id_tipo_presentacion)
    {
        $tipo = DB::select('
            SELECT * FROM tipos_presentacion 
            WHERE id_tipo_presentacion = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_tipo_presentacion]);

        if (empty($tipo)) {
            return response()->json(['error' => 'Tipo de presentación no encontrado o inactivo.'], 404);
        }

        return response()->json($tipo[0], 200);
    }

    /**
     * Actualizar los datos de un tipo de presentación.
     */
    public function update(Request $request, $id_tipo_presentacion)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_tipo_presentacion FROM tipos_presentacion WHERE id_tipo_presentacion = ? AND deleted_at IS NULL', [$id_tipo_presentacion]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Tipo de presentación no encontrado.'], 404);
        }

        DB::update('
            UPDATE tipos_presentacion 
            SET nombre = ?, descripcion = ?, activo = ?, updated_at = NOW() 
            WHERE id_tipo_presentacion = ?
        ', [
            $request->nombre,
            $request->descripcion,
            $request->activo,
            $id_tipo_presentacion
        ]);

        return response()->json(['mensaje' => "Tipo de presentación actualizado con éxito."], 200);
    }

    /**
     * Eliminar un tipo de presentación (Borrado Lógico).
     */
    public function destroy($id_tipo_presentacion)
    {
        $existe = DB::select('SELECT id_tipo_presentacion FROM tipos_presentacion WHERE id_tipo_presentacion = ? AND deleted_at IS NULL', [$id_tipo_presentacion]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Tipo no encontrado o ya eliminado.'], 404);
        }

        DB::update('
            UPDATE tipos_presentacion 
            SET deleted_at = NOW(), activo = false 
            WHERE id_tipo_presentacion = ?
        ', [$id_tipo_presentacion]);

        return response()->json(['mensaje' => "Tipo eliminado lógicamente."], 200);
    }
}