<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FoliosController extends Controller
{
    /**
     * Consultar todos los folios (incluyendo la sucursal).
     */
    public function index()
    {
        $folios = DB::select('
            SELECT f.*, s.nombre as sucursal_nombre 
            FROM folios f
            INNER JOIN sucursales s ON f.id_sucursal = s.id_sucursal
            ORDER BY f.id_folio DESC
        ');

        return response()->json($folios, 200);
    }

    /**
     * Guardar una nueva serie/folio.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_sucursal'        => 'required|integer',
            'tipo_documento'     => 'required|string|max:30',
            'serie'              => 'required|string|max:10',
            'correlativo_actual' => 'nullable|integer'
        ]);

        $correlativo = $request->correlativo_actual ?: 0;

        $insertado = DB::insert('
            INSERT INTO folios (id_sucursal, tipo_documento, serie, correlativo_actual, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_sucursal,
            $request->tipo_documento,
            $request->serie,
            $correlativo
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Folio registrado con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar el folio.'], 500);
    }

    /**
     * Mostrar un folio específico.
     */
    public function show($id_folio)
    {
        $folio = DB::select('
            SELECT f.*, s.nombre as sucursal_nombre 
            FROM folios f
            INNER JOIN sucursales s ON f.id_sucursal = s.id_sucursal
            WHERE f.id_folio = ?
            LIMIT 1
        ', [$id_folio]);

        if (empty($folio)) {
            return response()->json(['error' => 'Folio no encontrado.'], 404);
        }

        return response()->json($folio[0], 200);
    }

    /**
     * Actualizar (Ajustar) los datos de un folio.
     */
    public function update(Request $request, $id_folio)
    {
        $request->validate([
            'id_sucursal'        => 'required|integer',
            'tipo_documento'     => 'required|string|max:30',
            'serie'              => 'required|string|max:10',
            'correlativo_actual' => 'nullable|integer'
        ]);

        $existe = DB::select('SELECT id_folio FROM folios WHERE id_folio = ?', [$id_folio]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Folio no encontrado.'], 404);
        }

        DB::update('
            UPDATE folios 
            SET id_sucursal = ?, tipo_documento = ?, serie = ?, correlativo_actual = ?, updated_at = NOW() 
            WHERE id_folio = ?
        ', [
            $request->id_sucursal,
            $request->tipo_documento,
            $request->serie,
            $request->correlativo_actual,
            $id_folio
        ]);

        return response()->json(['mensaje' => "Folio actualizado con éxito."], 200);
    }

    /**
     * Eliminar un folio (Borrado Físico Definitivo, ya que no maneja deleted_at).
     */
    public function destroy($id_folio)
    {
        $existe = DB::select('SELECT id_folio FROM folios WHERE id_folio = ?', [$id_folio]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Folio no encontrado.'], 404);
        }

        DB::delete('DELETE FROM folios WHERE id_folio = ?', [$id_folio]);

        return response()->json(['mensaje' => "Folio eliminado del sistema."], 200);
    }
}