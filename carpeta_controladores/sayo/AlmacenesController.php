<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlmacenesController extends Controller
{
    /**
     * Consultar todos los almacenes activos.
     */
    public function index()
    {
        // JOIN para traer el nombre de la sucursal a la que pertenece
        $almacenes = DB::select('
            SELECT a.*, s.nombre as sucursal_nombre 
            FROM almacenes a
            INNER JOIN sucursales s ON a.id_sucursal = s.id_sucursal
            WHERE a.deleted_at IS NULL 
            ORDER BY a.id_almacen DESC
        ');

        return response()->json($almacenes, 200);
    }

    /**
     * Guardar un nuevo almacén.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_sucursal' => 'required|integer',
            'nombre'      => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:255',
            'responsable' => 'nullable|string|max:150',
            'activo'      => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO almacenes (id_sucursal, nombre, descripcion, responsable, activo, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_sucursal,
            $request->nombre,
            $request->descripcion,
            $request->responsable,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Almacén registrado con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar el almacén.'], 500);
    }

    /**
     * Mostrar un almacén específico.
     */
    public function show($id_almacen)
    {
        $almacen = DB::select('
            SELECT a.*, s.nombre as sucursal_nombre 
            FROM almacenes a
            INNER JOIN sucursales s ON a.id_sucursal = s.id_sucursal
            WHERE a.id_almacen = ? AND a.deleted_at IS NULL 
            LIMIT 1
        ', [$id_almacen]);

        if (empty($almacen)) {
            return response()->json(['error' => 'Almacén no encontrado o inactivo.'], 404);
        }

        return response()->json($almacen[0], 200);
    }

    /**
     * Actualizar los datos de un almacén.
     */
    public function update(Request $request, $id_almacen)
    {
        $request->validate([
            'id_sucursal' => 'required|integer',
            'nombre'      => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:255',
            'responsable' => 'nullable|string|max:150',
            'activo'      => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND deleted_at IS NULL', [$id_almacen]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Almacén no encontrado.'], 404);
        }

        DB::update('
            UPDATE almacenes 
            SET id_sucursal = ?, nombre = ?, descripcion = ?, responsable = ?, activo = ?, updated_at = NOW() 
            WHERE id_almacen = ?
        ', [
            $request->id_sucursal,
            $request->nombre,
            $request->descripcion,
            $request->responsable,
            $request->activo,
            $id_almacen
        ]);

        return response()->json(['mensaje' => "Almacén actualizado con éxito."], 200);
    }

    /**
     * Eliminar un almacén (Borrado Lógico).
     */
    public function destroy($id_almacen)
    {
        $existe = DB::select('SELECT id_almacen FROM almacenes WHERE id_almacen = ? AND deleted_at IS NULL', [$id_almacen]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Almacén no encontrado o ya eliminado.'], 404);
        }

        DB::update('
            UPDATE almacenes 
            SET deleted_at = NOW(), activo = false 
            WHERE id_almacen = ?
        ', [$id_almacen]);

        return response()->json(['mensaje' => "Almacén eliminado lógicamente."], 200);
    }
}