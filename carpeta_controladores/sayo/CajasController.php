<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajasController extends Controller
{
    /**
     * Consultar todas las cajas activas.
     */
    public function index()
    {
        // JOIN para traer el nombre de la sucursal a la que pertenece
        $cajas = DB::select('
            SELECT c.*, s.nombre as sucursal_nombre 
            FROM cajas c
            INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            WHERE c.deleted_at IS NULL 
            ORDER BY c.id_caja DESC
        ');

        return response()->json($cajas, 200);
    }

    /**
     * Guardar una nueva caja.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_sucursal'           => 'required|integer',
            'nombre'                => 'required|string|max:100',
            'tipo'                  => 'nullable|string|max:30',
            'descripcion'           => 'nullable|string|max:255',
            'serie_ticket'          => 'nullable|string|max:10',
            'numero_ticket_inicial' => 'nullable|integer',
            'activa'                => 'nullable|boolean'
        ]);

        $activa = $request->has('activa') ? $request->activa : true;
        $inicial = $request->numero_ticket_inicial ?: 1;
        $actual = $inicial; // Al crearla, el ticket actual es el inicial

        $insertado = DB::insert('
            INSERT INTO cajas (
                id_sucursal, nombre, tipo, descripcion, 
                serie_ticket, numero_ticket_inicial, numero_ticket_actual, activa, 
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_sucursal,
            $request->nombre,
            $request->tipo,
            $request->descripcion,
            $request->serie_ticket,
            $inicial,
            $actual,
            $activa
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Caja registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la caja.'], 500);
    }

    /**
     * Mostrar una caja específica.
     */
    public function show($id_caja)
    {
        $caja = DB::select('
            SELECT c.*, s.nombre as sucursal_nombre 
            FROM cajas c
            INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            WHERE c.id_caja = ? AND c.deleted_at IS NULL 
            LIMIT 1
        ', [$id_caja]);

        if (empty($caja)) {
            return response()->json(['error' => 'Caja no encontrada o inactiva.'], 404);
        }

        return response()->json($caja[0], 200);
    }

    /**
     * Actualizar los datos de una caja.
     */
    public function update(Request $request, $id_caja)
    {
        $request->validate([
            'id_sucursal'           => 'required|integer',
            'nombre'                => 'required|string|max:100',
            'tipo'                  => 'nullable|string|max:30',
            'descripcion'           => 'nullable|string|max:255',
            'serie_ticket'          => 'nullable|string|max:10',
            'numero_ticket_inicial' => 'nullable|integer',
            'numero_ticket_actual'  => 'nullable|integer',
            'activa'                => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_caja FROM cajas WHERE id_caja = ? AND deleted_at IS NULL', [$id_caja]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Caja no encontrada.'], 404);
        }

        DB::update('
            UPDATE cajas SET 
                id_sucursal = ?, nombre = ?, tipo = ?, descripcion = ?, 
                serie_ticket = ?, numero_ticket_inicial = ?, numero_ticket_actual = ?, 
                activa = ?, updated_at = NOW() 
            WHERE id_caja = ?
        ', [
            $request->id_sucursal,
            $request->nombre,
            $request->tipo,
            $request->descripcion,
            $request->serie_ticket,
            $request->numero_ticket_inicial,
            $request->numero_ticket_actual,
            $request->activa,
            $id_caja
        ]);

        return response()->json(['mensaje' => "Caja actualizada con éxito."], 200);
    }

    /**
     * Eliminar una caja (Borrado Lógico).
     */
    public function destroy($id_caja)
    {
        $existe = DB::select('SELECT id_caja FROM cajas WHERE id_caja = ? AND deleted_at IS NULL', [$id_caja]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Caja no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE cajas 
            SET deleted_at = NOW(), activa = false 
            WHERE id_caja = ?
        ', [$id_caja]);

        return response()->json(['mensaje' => "Caja eliminada lógicamente."], 200);
    }
}