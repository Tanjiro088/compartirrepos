<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParametrosController extends Controller
{
    /**
     * Consultar todos los parámetros del sistema.
     */
    public function index()
    {
        $parametros = DB::select('
            SELECT p.*, e.razon_social as empresa_nombre 
            FROM parametros_sistema p
            INNER JOIN empresas e ON p.id_empresa = e.id_empresa
            ORDER BY p.id_parametro DESC
        ');

        return response()->json($parametros, 200);
    }

    /**
     * Guardar un nuevo parámetro.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_empresa'  => 'required|integer',
            'clave'       => 'required|string|max:50',
            'valor'       => 'nullable|string',
            'descripcion' => 'nullable|string|max:255'
        ]);

        $insertado = DB::insert('
            INSERT INTO parametros_sistema (id_empresa, clave, valor, descripcion, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->id_empresa,
            // Guardamos la clave en mayúsculas por convención
            strtoupper($request->clave),
            $request->valor,
            $request->descripcion
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Parámetro registrado con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar el parámetro.'], 500);
    }

    /**
     * Mostrar un parámetro específico.
     */
    public function show($id_parametro)
    {
        $parametro = DB::select('
            SELECT p.*, e.razon_social as empresa_nombre 
            FROM parametros_sistema p
            INNER JOIN empresas e ON p.id_empresa = e.id_empresa
            WHERE p.id_parametro = ?
            LIMIT 1
        ', [$id_parametro]);

        if (empty($parametro)) {
            return response()->json(['error' => 'Parámetro no encontrado.'], 404);
        }

        return response()->json($parametro[0], 200);
    }

    /**
     * Actualizar los datos de un parámetro.
     */
    public function update(Request $request, $id_parametro)
    {
        $request->validate([
            'id_empresa'  => 'required|integer',
            'clave'       => 'required|string|max:50',
            'valor'       => 'nullable|string',
            'descripcion' => 'nullable|string|max:255'
        ]);

        $existe = DB::select('SELECT id_parametro FROM parametros_sistema WHERE id_parametro = ?', [$id_parametro]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Parámetro no encontrado.'], 404);
        }

        DB::update('
            UPDATE parametros_sistema 
            SET id_empresa = ?, clave = ?, valor = ?, descripcion = ?, updated_at = NOW() 
            WHERE id_parametro = ?
        ', [
            $request->id_empresa,
            strtoupper($request->clave),
            $request->valor,
            $request->descripcion,
            $id_parametro
        ]);

        return response()->json(['mensaje' => "Parámetro actualizado con éxito."], 200);
    }

    /**
     * Eliminar un parámetro (Borrado Físico Definitivo).
     */
    public function destroy($id_parametro)
    {
        $existe = DB::select('SELECT id_parametro FROM parametros_sistema WHERE id_parametro = ?', [$id_parametro]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Parámetro no encontrado.'], 404);
        }

        DB::delete('DELETE FROM parametros_sistema WHERE id_parametro = ?', [$id_parametro]);

        return response()->json(['mensaje' => "Parámetro eliminado del sistema."], 200);
    }
}