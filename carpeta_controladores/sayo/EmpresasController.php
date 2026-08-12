<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmpresasController extends Controller
{
    /**
     * Consultar todas las empresas activas.
     */
    public function index()
    {
        $empresas = DB::select('
            SELECT * FROM empresas 
            WHERE deleted_at IS NULL 
            ORDER BY id_empresa DESC
        ');

        return response()->json($empresas, 200);
    }

    /**
     * Guardar una nueva empresa.
     */
    public function store(Request $request)
    {
        $request->validate([
            'ruc'              => 'required|string|max:20',
            'razon_social'     => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'direccion'        => 'nullable|string',
            'telefono'         => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:150',
            'sitio_web'        => 'nullable|string|max:150',
            'regimen_fiscal'   => 'nullable|string|max:50',
            'activa'           => 'nullable|boolean'
        ]);

        $activa = $request->has('activa') ? $request->activa : true;

        $insertado = DB::insert('
            INSERT INTO empresas (ruc, razon_social, nombre_comercial, direccion, telefono, email, sitio_web, regimen_fiscal, activa, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $request->ruc,
            $request->razon_social,
            $request->nombre_comercial,
            $request->direccion,
            $request->telefono,
            $request->email,
            $request->sitio_web,
            $request->regimen_fiscal,
            $activa
        ]);

        if ($insertado) {
            return response()->json(['mensaje' => 'Empresa registrada con éxito.'], 201);
        }

        return response()->json(['error' => 'No se pudo registrar la empresa.'], 500);
    }

    /**
     * Mostrar una empresa específica.
     */
    public function show($id_empresa)
    {
        $empresa = DB::select('
            SELECT * FROM empresas 
            WHERE id_empresa = ? AND deleted_at IS NULL 
            LIMIT 1
        ', [$id_empresa]);

        if (empty($empresa)) {
            return response()->json(['error' => 'Empresa no encontrada o inactiva.'], 404);
        }

        return response()->json($empresa[0], 200);
    }

    /**
     * Actualizar los datos de una empresa.
     */
    public function update(Request $request, $id_empresa)
    {
        $request->validate([
            'ruc'              => 'required|string|max:20',
            'razon_social'     => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'direccion'        => 'nullable|string',
            'telefono'         => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:150',
            'sitio_web'        => 'nullable|string|max:150',
            'regimen_fiscal'   => 'nullable|string|max:50',
            'activa'           => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_empresa FROM empresas WHERE id_empresa = ? AND deleted_at IS NULL', [$id_empresa]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Empresa no encontrada.'], 404);
        }

        DB::update('
            UPDATE empresas 
            SET ruc = ?, razon_social = ?, nombre_comercial = ?, direccion = ?, telefono = ?, email = ?, sitio_web = ?, regimen_fiscal = ?, activa = ?, updated_at = NOW() 
            WHERE id_empresa = ?
        ', [
            $request->ruc,
            $request->razon_social,
            $request->nombre_comercial,
            $request->direccion,
            $request->telefono,
            $request->email,
            $request->sitio_web,
            $request->regimen_fiscal,
            $request->activa,
            $id_empresa
        ]);

        return response()->json(['mensaje' => "Empresa actualizada con éxito."], 200);
    }

    /**
     * Eliminar una empresa (Borrado Lógico).
     */
    public function destroy($id_empresa)
    {
        $existe = DB::select('SELECT id_empresa FROM empresas WHERE id_empresa = ? AND deleted_at IS NULL', [$id_empresa]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Empresa no encontrada o ya eliminada.'], 404);
        }

        DB::update('
            UPDATE empresas 
            SET deleted_at = NOW(), activa = false 
            WHERE id_empresa = ?
        ', [$id_empresa]);

        return response()->json(['mensaje' => "Empresa eliminada lógicamente."], 200);
    }
}