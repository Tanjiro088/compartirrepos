<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador: ProveedoresController
 * Propósito: Gestionar el ciclo de vida del catálogo de proveedores
 *            con JOIN a la tabla personas para datos fiscales y de contacto.
 *            Basado en HU-PROV-001, HU-PROV-002, HU-PROV-003 del PDF.
 */
class ProveedoresController extends Controller
{
    /**
     * Método: index
     * Propósito: Listar proveedores activos con datos de persona (nombre, RUC, teléfono, correo).
     *            HU-PROV-002: Listar y buscar proveedores.
     */
    public function index(Request $request)
    {
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(1, min(100, (int) $request->input('por_pagina', 8)));
        $offset = ($pagina - 1) * $porPagina;

        $baseWhere = 'WHERE p.deleted_at IS NULL';
        $bindings = [];

        if ($request->has('activo')) {
            $baseWhere .= ' AND p.activo = ?';
            $bindings[] = (int) $request->input('activo');
        }

        $baseFrom = "FROM proveedores p INNER JOIN personas pe ON p.id_persona = pe.id_persona $baseWhere";

        $total = DB::selectOne("SELECT COUNT(*) as total $baseFrom", $bindings);

        $concatExpr = DB::getDriverName() === 'sqlite'
            ? "pe.nombre || ' ' || pe.apellido_paterno"
            : "CONCAT(pe.nombre, ' ', pe.apellido_paterno)";

        $proveedores = DB::select("
            SELECT
                p.id_proveedor,
                p.id_persona,
                pe.numero_documento,
                COALESCE(NULLIF(TRIM(pe.razon_social), ''), pe.nombre_comercial, $concatExpr) AS razon_social,
                p.nombre_comercial,
                pe.telefono,
                pe.correo,
                p.condiciones_pago,
                p.calificacion,
                p.activo
            $baseFrom
            ORDER BY p.nombre_comercial ASC
            LIMIT ? OFFSET ?
        ", array_merge($bindings, [$porPagina, $offset]));

        foreach ($proveedores as $prov) {
            $prov->calificacion = (int) $prov->calificacion;
            $prov->activo = (bool) $prov->activo;
        }

        return response()->json([
            'total'      => (int) $total->total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'resultados' => $proveedores,
        ], 200);
    }

    /**
     * Método: store
     * Propósito: Registrar un nuevo proveedor (HU-PROV-001).
     *            Inserta en personas y proveedores en una transacción.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_persona'      => 'required|string|in:moral,fisica',
            'tipo_documento'    => 'nullable|string|in:RUC,RFC,CURP',
            'numero_documento'  => 'required|string|max:20',
            'nombre'            => 'nullable|string|max:150',
            'razon_social'      => 'nullable|string|max:200',
            'nombre_comercial'  => 'required|string|max:200',
            'calle'             => 'nullable|string|max:100',
            'colonia'           => 'nullable|string|max:100',
            'codigo_postal'     => 'nullable|string|max:10',
            'ciudad'            => 'nullable|string|max:80',
            'estado'            => 'nullable|string|max:80',
            'telefono'          => 'required|string|max:20',
            'correo'            => 'required|email|max:100',
            'condiciones_pago'  => 'nullable|string|max:100',
            'calificacion'      => 'nullable|integer|between:0,5',
        ], [
            'tipo_persona.required'     => 'Debe seleccionar el tipo de persona (moral o física).',
            'tipo_persona.in'           => 'El tipo de persona debe ser "moral" o "fisica".',
            'numero_documento.required' => 'El número de documento fiscal (RUC/RFC) es obligatorio.',
            'numero_documento.max'      => 'El documento fiscal no debe exceder 20 caracteres.',
            'nombre_comercial.required' => 'El nombre comercial del proveedor es obligatorio.',
            'nombre_comercial.max'      => 'El nombre comercial no debe exceder 200 caracteres.',
            'telefono.required'         => 'El número de teléfono es obligatorio.',
            'telefono.max'              => 'El teléfono no debe exceder 20 caracteres.',
            'correo.required'           => 'El correo electrónico es obligatorio.',
            'correo.email'              => 'Ingrese un formato de correo electrónico válido (ej: contacto@empresa.com).',
            'correo.max'                => 'El correo no debe exceder 100 caracteres.',
            'condiciones_pago.max'      => 'Las condiciones de pago no deben exceder 100 caracteres.',
            'calificacion.between'      => 'La calificación debe estar entre 0 y 5 estrellas.',
        ]);

        $fechaActual = date('Y-m-d H:i:s');

        $documentoExistente = DB::selectOne('SELECT id_persona FROM personas WHERE numero_documento = ?', [$validated['numero_documento']]);
        if ($documentoExistente) {
            return response()->json([
                'errors' => ['numero_documento' => ['El número de documento fiscal ya está registrado en el sistema.']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            $nombrePersona = $validated['nombre'] ?? ($validated['razon_social'] ?? $validated['nombre_comercial']);

            DB::insert('
                INSERT INTO personas (tipo_persona, tipo_documento, numero_documento, nombre, razon_social, nombre_comercial, telefono, correo, calle, colonia, codigo_postal, ciudad, estado, activo, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $validated['tipo_persona'],
                $validated['tipo_documento'] ?? 'RUC',
                $validated['numero_documento'],
                $nombrePersona,
                $validated['razon_social'] ?? ($validated['tipo_persona'] === 'moral' ? $nombrePersona : null),
                $validated['nombre_comercial'],
                $validated['telefono'],
                $validated['correo'],
                $validated['calle'] ?? null,
                $validated['colonia'] ?? null,
                $validated['codigo_postal'] ?? null,
                $validated['ciudad'] ?? null,
                $validated['estado'] ?? null,
                true,
                $fechaActual,
                $fechaActual
            ]);

            $idPersona = DB::getPdo()->lastInsertId();

            DB::insert('
                INSERT INTO proveedores (id_persona, nombre_comercial, condiciones_pago, calificacion, activo, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ', [
                $idPersona,
                $validated['nombre_comercial'],
                $validated['condiciones_pago'] ?? null,
                $validated['calificacion'] ?? 0,
                true,
                $fechaActual,
                $fechaActual
            ]);

            $idProveedor = DB::getPdo()->lastInsertId();

            DB::commit();

            $proveedor = DB::selectOne('
                SELECT p.id_proveedor, p.nombre_comercial, pe.numero_documento, p.activo, p.calificacion, p.created_at
                FROM proveedores p
                INNER JOIN personas pe ON p.id_persona = pe.id_persona
                WHERE p.id_proveedor = ?
            ', [$idProveedor]);

            $proveedor->calificacion = (int) $proveedor->calificacion;
            $proveedor->activo = (bool) $proveedor->activo;

            return response()->json($proveedor, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error al registrar proveedor.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    /**
     * Método: update
     * Propósito: Modificar datos generales del proveedor y su registro de persona asociado.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre_comercial'  => 'required|string|max:200',
            'condiciones_pago'  => 'nullable|string|max:100',
            'telefono'          => 'required|string|max:20',
            'correo'            => 'required|email|max:100',
            'nombre'            => 'nullable|string|max:150',
            'razon_social'      => 'nullable|string|max:200',
            'calle'             => 'nullable|string|max:100',
            'colonia'           => 'nullable|string|max:100',
            'codigo_postal'     => 'nullable|string|max:10',
            'ciudad'            => 'nullable|string|max:80',
            'estado'            => 'nullable|string|max:80',
        ]);

        $proveedor = DB::selectOne('
            SELECT p.id_proveedor, p.id_persona, pe.nombre, pe.razon_social
            FROM proveedores p
            INNER JOIN personas pe ON p.id_persona = pe.id_persona
            WHERE p.id_proveedor = ? AND p.deleted_at IS NULL
        ', [$id]);

        if (!$proveedor) {
            return response()->json(['Mensaje' => 'El proveedor especificado no existe'], 404);
        }

        $fechaActual = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {
            DB::update('
                UPDATE personas SET nombre = ?, razon_social = ?, nombre_comercial = ?, telefono = ?, correo = ?, calle = ?, colonia = ?, codigo_postal = ?, ciudad = ?, estado = ?, updated_at = ?
                WHERE id_persona = ?
            ', [
                $validated['nombre'] ?? $proveedor->nombre ?? '',
                $validated['razon_social'] ?? $proveedor->razon_social,
                $validated['nombre_comercial'],
                $validated['telefono'],
                $validated['correo'],
                $validated['calle'] ?? null,
                $validated['colonia'] ?? null,
                $validated['codigo_postal'] ?? null,
                $validated['ciudad'] ?? null,
                $validated['estado'] ?? null,
                $fechaActual,
                $proveedor->id_persona
            ]);

            DB::update('
                UPDATE proveedores SET nombre_comercial = ?, condiciones_pago = ?, updated_at = ?
                WHERE id_proveedor = ?
            ', [
                $validated['nombre_comercial'],
                $validated['condiciones_pago'] ?? null,
                $fechaActual,
                $id
            ]);

            DB::commit();
            return response()->json(['Mensaje' => 'Datos del proveedor actualizados correctamente'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error al actualizar proveedor.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    /**
     * Método: calificar
     * Propósito: Actualizar calificación del proveedor de 0 a 5 estrellas (HU-PROV-003).
     */
    public function calificar(Request $request, $id)
    {
        $validated = $request->validate([
            'calificacion' => 'required|integer|between:0,5'
        ]);

        $existe = DB::selectOne('SELECT id_proveedor FROM proveedores WHERE id_proveedor = ? AND deleted_at IS NULL', [$id]);

        if (!$existe) {
            return response()->json(['Mensaje' => 'Proveedor no encontrado'], 404);
        }

        DB::update('UPDATE proveedores SET calificacion = ?, updated_at = ? WHERE id_proveedor = ?', [
            $validated['calificacion'],
            date('Y-m-d H:i:s'),
            $id
        ]);

        return response()->json(['Mensaje' => 'Calificación del proveedor actualizada con éxito'], 200);
    }

    /**
     * Método: estado
     * Propósito: Activar o desactivar un proveedor (soft toggle lógico).
     */
    public function estado(Request $request, $id)
    {
        $validated = $request->validate([
            'activo' => 'required|boolean'
        ]);

        $existe = DB::selectOne('SELECT id_proveedor FROM proveedores WHERE id_proveedor = ? AND deleted_at IS NULL', [$id]);

        if (!$existe) {
            return response()->json(['Mensaje' => 'Proveedor no encontrado'], 404);
        }

        DB::update('UPDATE proveedores SET activo = ?, updated_at = ? WHERE id_proveedor = ?', [
            $validated['activo'],
            date('Y-m-d H:i:s'),
            $id
        ]);

        return response()->json(['Mensaje' => 'Estado operacional modificado correctamente'], 200);
    }

    /**
     * Método: destroy
     * Propósito: Soft-delete de un proveedor. Bloqueado si tiene compras asociadas.
     */
    public function destroy($id)
    {
        $proveedor = DB::selectOne('SELECT id_proveedor FROM proveedores WHERE id_proveedor = ? AND deleted_at IS NULL', [$id]);

        if (!$proveedor) {
            return response()->json(['Mensaje' => 'Proveedor no encontrado'], 404);
        }

        $comprasAsociadas = DB::selectOne('SELECT COUNT(*) as total FROM compras WHERE id_proveedor = ? AND deleted_at IS NULL', [$id]);

        if ($comprasAsociadas && (int) $comprasAsociadas->total > 0) {
            return response()->json(['Mensaje' => 'No se puede eliminar un proveedor con compras asociadas.'], 422);
        }

        DB::update('UPDATE proveedores SET deleted_at = ? WHERE id_proveedor = ?', [date('Y-m-d H:i:s'), $id]);

        return response()->json(['Mensaje' => 'Proveedor eliminado correctamente'], 200);
    }

    protected function errorMsg(\Exception $e): string
    {
        return config('app.debug') ? $e->getMessage() : 'Error interno del servidor.';
    }
}

