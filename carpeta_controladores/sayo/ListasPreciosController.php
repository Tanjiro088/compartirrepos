<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListasPreciosController extends Controller
{
    /**
     * Consultar todas las listas de precios activas.
     */
    public function index()
    {
        $listas = DB::select('
            SELECT * FROM listas_precios 
            WHERE deleted_at IS NULL 
            ORDER BY id_lista_precio DESC
        ');

        return response()->json($listas, 200);
    }

    /**
     * Guardar una nueva lista de precios.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'           => 'required|string|max:100',
            'descripcion'      => 'nullable|string|max:255',
            'tipo'             => 'nullable|string|max:30',
            'aplica_descuento' => 'nullable|boolean',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'activo'           => 'nullable|boolean'
        ]);

        $aplica_descuento = $request->has('aplica_descuento') ? $request->aplica_descuento : false;
        $descuento_global = $aplica_descuento ? ($request->descuento_global ?: 0.00) : 0.00;
        $activo = $request->has('activo') ? $request->activo : true;

        $insertado = DB::insert('
            INSERT INTO listas_precios (
                nombre, descripcion, tipo, aplica_descuento, descuento_global, activo, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->nombre,
            $request->descripcion,
            $request->tipo,
            $aplica_descuento,
            $descuento_global,
            $activo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Lista de precios registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la lista de precios.'], 500);
    }

    /**
     * Mostrar una lista específica.
     */
    public function show($id_lista_precio)
    {
        $lista = DB::select('
            SELECT * FROM listas_precios 
            WHERE id_lista_precio = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_lista_precio]);

        if (empty($lista)) {
            return response()->json(['error' => 'Lista no encontrada o inactiva.'], 404);
        }

        return response()->json($lista[0], 200);
    }

    /**
     * Actualizar los datos de una lista de precios.
     */
    public function update(Request $request, $id_lista_precio)
    {
        $request->validate([
            'nombre'           => 'required|string|max:100',
            'descripcion'      => 'nullable|string|max:255',
            'tipo'             => 'nullable|string|max:30',
            'aplica_descuento' => 'required|boolean',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'activo'           => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_lista_precio FROM listas_precios WHERE id_lista_precio = ? AND deleted_at IS NULL', [$id_lista_precio]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Lista no encontrada.'], 404);
        }

        $descuento_global = $request->aplica_descuento ? ($request->descuento_global ?: 0.00) : 0.00;

        DB::update('
            UPDATE listas_precios SET 
                nombre = ?, descripcion = ?, tipo = ?, aplica_descuento = ?, 
                descuento_global = ?, activo = ?, updated_at = NOW() 
            WHERE id_lista_precio = ?
        ', [
            $request->nombre,
            $request->descripcion,
            $request->tipo,
            $request->aplica_descuento,
            $descuento_global,
            $request->activo,
            $id_lista_precio
        ]);

        return response()->json(['mensaje' => "Lista actualizada con éxito."], 200);
    }

    /**
     * Eliminar una lista (Borrado Lógico).
     */
    public function destroy($id_lista_precio)
    {
        $existe = DB::select('SELECT id_lista_precio FROM listas_precios WHERE id_lista_precio = ? AND deleted_at IS NULL', [$id_lista_precio]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Lista no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE listas_precios 
            SET deleted_at = NOW(), activo = false 
            WHERE id_lista_precio = ?
        ', [$id_lista_precio]);

        return response()->json(['mensaje' => "Lista eliminada lógicamente."], 200);
    }
}