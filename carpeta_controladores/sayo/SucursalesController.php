<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SucursalesController extends Controller
{
    /**
     * Consultar todas las sucursales activas.
     */
    public function index()
    {
        // Usamos JOIN para enviar también la razón social de la empresa
        $sucursales = DB::select('
            SELECT s.*, e.razon_social as empresa_nombre 
            FROM sucursales s
            INNER JOIN empresas e ON s.id_empresa = e.id_empresa
            WHERE s.deleted_at IS NULL 
            ORDER BY s.id_sucursal DESC
        ');

        return response()->json($sucursales, 200);
    }

    /**
     * Guardar una nueva sucursal.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_empresa'      => 'required|integer',
            'nombre'          => 'required|string|max:150',
            'clave'           => 'nullable|string|max:20',
            'responsable'     => 'nullable|string|max:150',
            'telefono'        => 'nullable|string|max:30',
            'correo'          => 'nullable|email|max:150',
            'calle'           => 'nullable|string|max:150',
            'numero_exterior' => 'nullable|string|max:20',
            'numero_interior' => 'nullable|string|max:20',
            'colonia'         => 'nullable|string|max:120',
            'codigo_postal'   => 'nullable|string|max:10',
            'ciudad'          => 'nullable|string|max:120',
            'estado'          => 'nullable|string|max:120',
            'pais'            => 'nullable|string|max:120',
            'activa'          => 'nullable|boolean'
        ]);

        $activa = $request->has('activa') ? $request->activa : true;
        // Asignamos México por defecto si no viene, tal como indica el diccionario
        $pais = $request->pais ?: 'México';

        $insertado = DB::insert('
            INSERT INTO sucursales (
                id_empresa, nombre, clave, responsable, telefono, correo, 
                calle, numero_exterior, numero_interior, colonia, codigo_postal, 
                ciudad, estado, pais, activa, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_empresa, $request->nombre, $request->clave, $request->responsable,
            $request->telefono, $request->correo, $request->calle, $request->numero_exterior,
            $request->numero_interior, $request->colonia, $request->codigo_postal,
            $request->ciudad, $request->estado, $pais, $activa
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Sucursal registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la sucursal.'], 500);
    }

    /**
     * Mostrar una sucursal específica.
     */
    public function show($id_sucursal)
    {
        $sucursal = DB::select('
            SELECT s.*, e.razon_social as empresa_nombre 
            FROM sucursales s
            INNER JOIN empresas e ON s.id_empresa = e.id_empresa
            WHERE s.id_sucursal = ? AND s.deleted_at IS NULL 
            LIMIT 1
        ', [$id_sucursal]);

        if (empty($sucursal)) {
            return response()->json(['error' => 'Sucursal no encontrada o inactiva.'], 404);
        }

        return response()->json($sucursal[0], 200);
    }

    /**
     * Actualizar los datos de una sucursal.
     */
    public function update(Request $request, $id_sucursal)
    {
        $request->validate([
            'id_empresa'      => 'required|integer',
            'nombre'          => 'required|string|max:150',
            'clave'           => 'nullable|string|max:20',
            'responsable'     => 'nullable|string|max:150',
            'telefono'        => 'nullable|string|max:30',
            'correo'          => 'nullable|email|max:150',
            'calle'           => 'nullable|string|max:150',
            'numero_exterior' => 'nullable|string|max:20',
            'numero_interior' => 'nullable|string|max:20',
            'colonia'         => 'nullable|string|max:120',
            'codigo_postal'   => 'nullable|string|max:10',
            'ciudad'          => 'nullable|string|max:120',
            'estado'          => 'nullable|string|max:120',
            'pais'            => 'nullable|string|max:120',
            'activa'          => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_sucursal FROM sucursales WHERE id_sucursal = ? AND deleted_at IS NULL', [$id_sucursal]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Sucursal no encontrada.'], 404);
        }

        DB::update('
            UPDATE sucursales SET 
                id_empresa = ?, nombre = ?, clave = ?, responsable = ?, telefono = ?, correo = ?, 
                calle = ?, numero_exterior = ?, numero_interior = ?, colonia = ?, codigo_postal = ?, 
                ciudad = ?, estado = ?, pais = ?, activa = ?, updated_at = NOW() 
            WHERE id_sucursal = ?
        ', [
            $request->id_empresa, $request->nombre, $request->clave, $request->responsable,
            $request->telefono, $request->correo, $request->calle, $request->numero_exterior,
            $request->numero_interior, $request->colonia, $request->codigo_postal,
            $request->ciudad, $request->estado, $request->pais, $request->activa,
            $id_sucursal
        ]);

        return response()->json(['mensaje' => "Sucursal actualizada con éxito."], 200);
    }

    /**
     * Eliminar una sucursal (Borrado Lógico).
     */
    public function destroy($id_sucursal)
    {
        $existe = DB::select('SELECT id_sucursal FROM sucursales WHERE id_sucursal = ? AND deleted_at IS NULL', [$id_sucursal]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Sucursal no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE sucursales 
            SET deleted_at = NOW(), activa = false 
            WHERE id_sucursal = ?
        ', [$id_sucursal]);

        return response()->json(['mensaje' => "Sucursal eliminada lógicamente."], 200);
    }
}