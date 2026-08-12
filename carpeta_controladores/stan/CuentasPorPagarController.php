<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador: CuentasPorPagarController
 * Propósito: Gestionar cuentas por pagar, abonos parciales y totales.
 *            Basado en HU-COMP-004 y HU-COMP-005 del PDF.
 */
class CuentasPorPagarController extends Controller
{
    /**
     * Método: index
     * Propósito: Listar cuentas por pagar con JOIN a proveedores y compras.
     *            HU-COMP-004: Filtrar por proveedor, estado, fecha de vencimiento.
     *            Las cuentas vencidas se detectan automáticamente.
     */
    public function index(Request $request)
    {
        $hoy = date('Y-m-d');
        DB::update("
            UPDATE cuentas_por_pagar
            SET estado = 'vencido', updated_at = ?
            WHERE estado IN ('pendiente', 'parcial')
              AND fecha_vencimiento < ?
        ", [date('Y-m-d H:i:s'), $hoy]);

        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(1, min(100, (int) $request->input('por_pagina', 8)));
        $offset = ($pagina - 1) * $porPagina;

        $baseWhere = ' FROM cuentas_por_pagar cpp
            INNER JOIN proveedores p ON cpp.id_proveedor = p.id_proveedor
            INNER JOIN compras c ON cpp.id_compra = c.id_compra
            WHERE 1=1
        ';

        $where = '';
        $bindings = [];

        if ($request->filled('id_proveedor')) {
            $where .= ' AND cpp.id_proveedor = ?';
            $bindings[] = $request->id_proveedor;
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $where .= ' AND cpp.estado = ?';
            $bindings[] = $request->estado;
        }

        if ($request->filled('fecha_desde')) {
            $where .= ' AND cpp.fecha_vencimiento >= ?';
            $bindings[] = $request->fecha_desde;
        }

        if ($request->filled('fecha_hasta')) {
            $where .= ' AND cpp.fecha_vencimiento <= ?';
            $bindings[] = $request->fecha_hasta;
        }

        $total = DB::selectOne("SELECT COUNT(*) as total $baseWhere $where", $bindings);

        $query = "
            SELECT
                cpp.id_cuenta_pagar,
                cpp.id_compra,
                c.folio AS folio_compra,
                cpp.id_proveedor,
                p.nombre_comercial AS proveedor,
                cpp.monto_total,
                cpp.monto_pagado,
                cpp.saldo_pendiente,
                cpp.fecha_vencimiento,
                cpp.estado
            $baseWhere
            $where
            ORDER BY cpp.estado ASC, cpp.fecha_vencimiento ASC
            LIMIT ? OFFSET ?
        ";

        $cuentas = DB::select($query, array_merge($bindings, [$porPagina, $offset]));

        $totalAdeudoRow = DB::selectOne("
            SELECT COALESCE(SUM(cpp.saldo_pendiente), 0) AS total_adeudo,
                   COALESCE(SUM(CASE WHEN cpp.estado = 'vencido' THEN cpp.saldo_pendiente ELSE 0 END), 0) AS total_vencido
            $baseWhere $where
        ", $bindings);

        foreach ($cuentas as $c) {
            $c->monto_total     = (float) $c->monto_total;
            $c->monto_pagado    = (float) $c->monto_pagado;
            $c->saldo_pendiente = (float) $c->saldo_pendiente;
            $c->dias_vencidos   = (int) (strtotime(date('Y-m-d')) - strtotime($c->fecha_vencimiento)) / 86400;
        }

        return response()->json([
            'cuentas'        => $cuentas,
            'total'          => (int) $total->total,
            'pagina'         => $pagina,
            'por_pagina'     => $porPagina,
            'total_adeudo'   => round((float) $totalAdeudoRow->total_adeudo, 2),
            'total_vencido'  => round((float) $totalAdeudoRow->total_vencido, 2),
        ], 200);
    }

    /**
     * Método: abonar
     * Propósito: Registrar un abono (parcial o total) a una cuenta por pagar.
     *            HU-COMP-005: Pago parcial a proveedor.
     *            Transacción: inserta en pagos_compra + actualiza cuentas_por_pagar + compras.
     */
    public function abonar(Request $request, $id)
    {
        $validated = $request->validate([
            'id_usuario'     => 'required|integer|min:1',
            'efectivo'       => 'required|numeric|min:0',
            'tarjeta'        => 'required|numeric|min:0',
            'fecha_pago'     => 'required|date',
            'referencia'     => 'nullable|string|max:100',
        ]);

        $cuenta = DB::selectOne('
            SELECT cpp.*, c.id_proveedor AS compra_proveedor, c.folio
            FROM cuentas_por_pagar cpp
            INNER JOIN compras c ON cpp.id_compra = c.id_compra
            WHERE cpp.id_cuenta_pagar = ?
        ', [$id]);

        if (!$cuenta) {
            return response()->json(['Mensaje' => 'Cuenta por pagar no encontrada'], 404);
        }

        if ($cuenta->estado === 'pagado') {
            return response()->json(['Mensaje' => 'Esta cuenta ya está completamente pagada'], 422);
        }

        $montoAbono = round((float) $validated['efectivo'] + (float) $validated['tarjeta'], 2);

        if ($montoAbono <= 0) {
            return response()->json(['Mensaje' => 'El monto del abono debe ser mayor a cero'], 422);
        }

        if ($montoAbono > $cuenta->saldo_pendiente) {
            return response()->json(['Mensaje' => 'El abono no puede exceder el saldo pendiente'], 422);
        }

        $fechaActual = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {
            // 1. Insertar pago(s) en pagos_compra (HU-COMP-003)
            if ((float) $validated['efectivo'] > 0) {
                DB::insert('
                    INSERT INTO pagos_compra (id_compra, id_usuario, id_forma_pago, fecha_pago, monto, referencia, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $cuenta->id_compra,
                    $validated['id_usuario'],
                    1, // Efectivo
                    $validated['fecha_pago'],
                    $validated['efectivo'],
                    $validated['referencia'] ?? null,
                    $fechaActual,
                    $fechaActual
                ]);
            }

            if ((float) $validated['tarjeta'] > 0) {
                DB::insert('
                    INSERT INTO pagos_compra (id_compra, id_usuario, id_forma_pago, fecha_pago, monto, referencia, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $cuenta->id_compra,
                    $validated['id_usuario'],
                    2, // Tarjeta
                    $validated['fecha_pago'],
                    $validated['tarjeta'],
                    $validated['referencia'] ?? null,
                    $fechaActual,
                    $fechaActual
                ]);
            }

            // 1b. Registrar movimiento en caja (egreso) por el total abonado
            DB::insert('
                INSERT INTO movimientos_caja (id_sesion_caja, id_usuario, tipo, monto, concepto, referencia_tipo, id_referencia, created_at, updated_at)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $validated['id_usuario'],
                'egreso',
                $montoAbono,
                'Pago de compra ' . ($cuenta->folio ?? 'SN'),
                'compra',
                $cuenta->id_compra,
                $fechaActual,
                $fechaActual
            ]);

            // 2. Actualizar cuenta por pagar
            $nuevoMontoPagado = (float) $cuenta->monto_pagado + $montoAbono;
            $nuevoSaldo = (float) $cuenta->monto_total - $nuevoMontoPagado;
            $nuevoSaldo = max($nuevoSaldo, 0);

            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagado' : 'parcial';

            DB::update('
                UPDATE cuentas_por_pagar
                SET monto_pagado = ?,
                    saldo_pendiente = ?,
                    estado = ?,
                    updated_at = ?
                WHERE id_cuenta_pagar = ?
            ', [$nuevoMontoPagado, $nuevoSaldo, $nuevoEstado, $fechaActual, $id]);

            // 3. Actualizar estado de la compra
            if ($nuevoEstado === 'pagado') {
                DB::update('UPDATE compras SET estado = ?, updated_at = ? WHERE id_compra = ?', [
                    'pagado', $fechaActual, $cuenta->id_compra
                ]);
            } elseif ($nuevoEstado === 'parcial') {
                DB::update('UPDATE compras SET estado = ?, updated_at = ? WHERE id_compra = ?', [
                    'parcial', $fechaActual, $cuenta->id_compra
                ]);
            }

            DB::commit();

            return response()->json([
                'Mensaje'          => 'Abono registrado con éxito',
                'id_cuenta_pagar'  => $id,
                'monto_abonado'    => $montoAbono,
                'nuevo_saldo'      => $nuevoSaldo,
                'estado'           => $nuevoEstado,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error al registrar abono.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    protected function errorMsg(\Exception $e): string
    {
        return config('app.debug') ? $e->getMessage() : 'Error interno del servidor.';
    }
}

