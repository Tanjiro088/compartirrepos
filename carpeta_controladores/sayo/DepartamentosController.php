<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartamentosController extends Controller
{
    /**
     * Consultar todos los departamentos activos.
     */
    public function index()
    {
        // Seleccionamos solo los registros que NO han sido borrados lógicamente
        $departamentos = DB::select('
            SELECT * FROM departamentos 
            WHERE deleted_at IS NULL 
            ORDER BY id_departamento DESC
        ');

        return response()->json($departamentos, 200);
    }

    /**
     * Guardar un nuevo departamento.
     */
    public function store(Request $request)
    {
        // 1. Validación estricta
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'activo'      => 'nullable|boolean'
        ]);

        // 2. Asignamos un valor por defecto si "activo" no viene en la petición
        $activo = $request->has('activo') ? $request->activo : true;

        // 3. Inserción SQL cruda con parámetros seguros (?)
        $insertado = DB::insert('
            INSERT INTO departamentos (nombre, descripcion, activo, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ', [
            $request->nombre,
            $request->descripcion,
            $activo
        ]);

        if ($insertado) {
            return response()->json([
                'mensaje' => 'Departamento registrado con éxito.'
            ], 201);
        }

        return response()->json(['error' => 'No se pudo registrar el departamento.'], 500);
    }

    /**
     * Mostrar un departamento específico.
     */
    public function show($id_departamento)
    {
        // Buscamos el registro asegurándonos de que no esté eliminado
        $departamento = DB::select('
            SELECT * FROM departamentos 
            WHERE id_departamento = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_departamento]);

        if (empty($departamento)) {
            return response()->json(['error' => 'Departamento no encontrado o inactivo.'], 404);
        }

        return response()->json($departamento[0], 200);
    }

    /**
     * Actualizar los datos de un departamento.
     */
    public function update(Request $request, $id_departamento)
    {
        $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'activo'      => 'required|boolean'
        ]);

        // Verificamos si existe antes de actualizar
        $existe = DB::select('SELECT id_departamento FROM departamentos WHERE id_departamento = ? AND deleted_at IS NULL', [$id_departamento]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Departamento no encontrado.'], 404);
        }

        // Actualización manual de los campos y del updated_at
        DB::update('
            UPDATE departamentos 
            SET nombre = ?, descripcion = ?, activo = ?, updated_at = NOW() 
            WHERE id_departamento = ?
        ', [
            $request->nombre,
            $request->descripcion,
            $request->activo,
            $id_departamento
        ]);

        return response()->json([
            'mensaje' => "Departamento actualizado con éxito con el identificador: {$id_departamento}"
        ], 200);
    }

    /**
     * Eliminar un departamento (Borrado Lógico Manual).
     */
    public function destroy($id_departamento)
    {
        $existe = DB::select('SELECT id_departamento FROM departamentos WHERE id_departamento = ? AND deleted_at IS NULL', [$id_departamento]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Departamento no encontrado o ya eliminado.'], 404);
        }

        // Ejecutamos el borrado lógico: Llenamos deleted_at con la fecha actual y apagamos el estado activo
        DB::update('
            UPDATE departamentos 
            SET deleted_at = NOW(), activo = false 
            WHERE id_departamento = ?
        ', [$id_departamento]);

        return response()->json([
            'mensaje' => "Departamento eliminado lógicamente con el identificador: {$id_departamento}"
        ], 200);
    }

    /**
     * Retorna el ID, el nombre y el estado (activo/inactivo) de TODOS 
     * los departamentos para ser consumido por otros módulos.
     */
    public function listaBasica()
    {
        $departamentos = \Illuminate\Support\Facades\DB::select('
            SELECT id_departamento, nombre, activo 
            FROM departamentos 
            ORDER BY id_departamento ASC
        ');

        return response()->json($departamentos, 200);
    }
}