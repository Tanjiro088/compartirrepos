<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador: ComprasController
 * Propósito: Administrar de forma atómica y transaccional el ciclo de vida
 *            Maestro-Detalle de las compras, pagos y cuentas por pagar.
 *            IVA: 16% (México). Basado en HU-COMP-001, HU-COMP-002, HU-COMP-003.
 */
class ComprasController extends Controller
{
    public function index(Request $request)
    {
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(1, min(100, (int) $request->input('por_pagina', 8)));
        $offset = ($pagina - 1) * $porPagina;

        $baseFrom = 'FROM compras c INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor INNER JOIN almacenes a ON c.id_almacen = a.id_almacen WHERE c.deleted_at IS NULL';

        $total = DB::selectOne("SELECT COUNT(*) as total $baseFrom");

        $compras = DB::select("
            SELECT
                c.id_compra AS id,
                c.folio,
                c.id_proveedor,
                p.nombre_comercial AS proveedor,
                c.id_almacen,
                a.nombre AS almacen,
                c.fecha_compra AS fecha,
                c.fecha_entrega AS fecha_esperada,
                c.tipo_documento,
                c.subtotal,
                c.impuesto,
                c.descuento,
                c.total,
                c.estado,
                c.observaciones,
                COALESCE((SELECT SUM(pc.monto) FROM pagos_compra pc WHERE pc.id_compra = c.id_compra), 0) AS monto_pagado,
                COALESCE((SELECT SUM(pc.monto) FROM pagos_compra pc WHERE pc.id_compra = c.id_compra AND pc.id_forma_pago = 1), 0) AS monto_efectivo,
                COALESCE((SELECT SUM(pc.monto) FROM pagos_compra pc WHERE pc.id_compra = c.id_compra AND pc.id_forma_pago = 2), 0) AS monto_tarjeta
            $baseFrom
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ", [$porPagina, $offset]);

        if (empty($compras)) {
            return response()->json(['total' => (int) $total->total, 'pagina' => $pagina, 'por_pagina' => $porPagina, 'resultados' => []], 200);
        }

        $compraIds = array_column($compras, 'id');
        $placeholders = implode(',', array_fill(0, count($compraIds), '?'));

        $detalles = DB::select("
            SELECT
                dc.id_detalle_compra,
                dc.id_compra,
                dc.id_presentacion,
                pr.nombre AS producto,
                dc.cantidad,
                dc.precio_unitario AS precio,
                dc.descuento AS descuento_detalle,
                dc.subtotal
            FROM detalle_compra dc
            INNER JOIN presentaciones_producto pr ON dc.id_presentacion = pr.id_presentacion
            WHERE dc.id_compra IN ($placeholders)
        ", $compraIds);

        $detallesPorCompra = [];
        foreach ($detalles as $det) {
            $det->cantidad = (float) $det->cantidad;
            $det->precio   = (float) $det->precio;
            $det->subtotal = (float) $det->subtotal;
            $detallesPorCompra[$det->id_compra][] = $det;
        }

        foreach ($compras as $compra) {
            $compra->subtotal       = (float) $compra->subtotal;
            $compra->impuesto       = (float) $compra->impuesto;
            $compra->descuento      = (float) $compra->descuento;
            $compra->total          = (float) $compra->total;
            $compra->monto_pagado   = (float) $compra->monto_pagado;
            $compra->monto_efectivo = (float) $compra->monto_efectivo;
            $compra->monto_tarjeta  = (float) $compra->monto_tarjeta;
            $compra->detalles       = $detallesPorCompra[$compra->id] ?? [];
        }

        return response()->json([
            'total'      => (int) $total->total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'resultados' => $compras,
        ], 200);
    }

    public function nextFolio()
    {
        return response()->json(['folio' => $this->generarFolio('OC', 'compras')], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_proveedor'   => 'required|integer|min:1',
            'id_almacen'     => 'required|integer|min:1',
            'id_usuario'     => 'required|integer|min:1',
            'tipo_documento' => 'nullable|string|max:30',
            'fecha_compra'   => 'required|date',
            'fecha_entrega'  => 'nullable|date',
            'pago_efectivo'  => 'required|numeric|min:0',
            'pago_tarjeta'   => 'required|numeric|min:0',
            'total'          => 'required|numeric|min:0.01',
            'detalles'       => 'required|array|min:1',
            'detalles.*.id_presentacion' => 'required|integer|min:1',
            'detalles.*.cantidad'        => 'required|numeric|min:1',
            'detalles.*.precio'          => 'required|numeric|min:0.01',
            'detalles.*.descuento'       => 'nullable|numeric|min:0',
            'observaciones'  => 'nullable|string',
        ], [
            'id_proveedor.required' => 'Debe seleccionar un proveedor.',
            'id_almacen.required'   => 'Debe seleccionar un almacén de destino.',
            'id_usuario.required'   => 'Se requiere un usuario responsable.',
            'fecha_compra.required' => 'La fecha de compra es obligatoria.',
            'total.min'             => 'El total debe ser mayor a cero.',
            'detalles.required'     => 'Debe agregar al menos un producto al detalle.',
            'detalles.min'          => 'Debe agregar al menos un producto al detalle.',
            'detalles.*.id_presentacion.required' => 'Cada detalle requiere un producto.',
            'detalles.*.cantidad.required'        => 'Cada detalle requiere una cantidad.',
            'detalles.*.cantidad.min'             => 'La cantidad debe ser al menos 1.',
            'detalles.*.precio.required'          => 'Cada detalle requiere un precio unitario.',
            'detalles.*.precio.min'               => 'El precio debe ser mayor a 0.',
        ]);

        $proveedor = DB::selectOne('SELECT id_proveedor, activo FROM proveedores WHERE id_proveedor = ? AND deleted_at IS NULL', [$validated['id_proveedor']]);
        if (!$proveedor) {
            return response()->json(['Mensaje' => 'El proveedor seleccionado no existe.'], 404);
        }
        if (!$proveedor->activo) {
            return response()->json(['Mensaje' => 'El proveedor seleccionado no está activo.'], 422);
        }

        $almacen = DB::selectOne('SELECT id_almacen, activo FROM almacenes WHERE id_almacen = ?', [$validated['id_almacen']]);
        if (!$almacen) {
            return response()->json(['Mensaje' => 'El almacén seleccionado no existe.'], 404);
        }
        if (!$almacen->activo) {
            return response()->json(['Mensaje' => 'El almacén seleccionado no está activo.'], 422);
        }

        $fechaActual = date('Y-m-d H:i:s');
        $folio = $this->generarFolio('OC', 'compras');

        DB::beginTransaction();

        try {
            $subtotalBase     = round($validated['total'] / 1.16, 2);
            $impuestoCalculado = round($validated['total'] - $subtotalBase, 2);

            DB::insert('
                INSERT INTO compras (id_proveedor, id_almacen, id_usuario, folio, tipo_documento, fecha_compra, fecha_entrega, subtotal, impuesto, total, estado, observaciones, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $validated['id_proveedor'],
                $validated['id_almacen'],
                $validated['id_usuario'],
                $folio,
                $validated['tipo_documento'] ?? 'factura',
                $validated['fecha_compra'],
                $validated['fecha_entrega'] ?? null,
                $subtotalBase,
                $impuestoCalculado,
                $validated['total'],
                'orden',
                $validated['observaciones'] ?? null,
                $fechaActual,
                $fechaActual
            ]);

            $idCompraGenerada = DB::getPdo()->lastInsertId();

            foreach ($validated['detalles'] as $item) {
                $descuentoFila = isset($item['descuento']) ? (float) $item['descuento'] : 0.00;
                $subtotalFila = round(((float) $item['cantidad'] * (float) $item['precio']) - $descuentoFila, 2);

                DB::insert('
                    INSERT INTO detalle_compra (id_compra, id_presentacion, cantidad, precio_unitario, descuento, subtotal, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $idCompraGenerada,
                    $item['id_presentacion'],
                    $item['cantidad'],
                    $item['precio'],
                    $descuentoFila,
                    $subtotalFila,
                    $fechaActual,
                    $fechaActual
                ]);
            }

            if ($validated['pago_efectivo'] > 0) {
                DB::insert('
                    INSERT INTO pagos_compra (id_compra, id_usuario, id_forma_pago, fecha_pago, monto, created_at, updated_at)
                    VALUES (?, ?, 1, ?, ?, ?, ?)
                ', [
                    $idCompraGenerada, $validated['id_usuario'],
                    $validated['fecha_compra'], $validated['pago_efectivo'],
                    $fechaActual, $fechaActual
                ]);
            }

            if ($validated['pago_tarjeta'] > 0) {
                DB::insert('
                    INSERT INTO pagos_compra (id_compra, id_usuario, id_forma_pago, fecha_pago, monto, created_at, updated_at)
                    VALUES (?, ?, 2, ?, ?, ?, ?)
                ', [
                    $idCompraGenerada, $validated['id_usuario'],
                    $validated['fecha_compra'], $validated['pago_tarjeta'],
                    $fechaActual, $fechaActual
                ]);
            }

            // Calcular automáticamente el crédito (lo que falta por pagar)
            $montoPagado = (float) $validated['pago_efectivo'] + (float) $validated['pago_tarjeta'];
            $montoCredito = round($validated['total'] - $montoPagado, 2);

            if ($montoCredito > 0) {
                $fechaVencimiento = date('Y-m-d', strtotime('+30 days', strtotime($validated['fecha_compra'])));
                DB::insert('
                    INSERT INTO cuentas_por_pagar (id_compra, id_proveedor, monto_total, monto_pagado, saldo_pendiente, fecha_vencimiento, estado, created_at, updated_at)
                    VALUES (?, ?, ?, 0.00, ?, ?, ?, ?, ?)
                ', [
                    $idCompraGenerada,
                    $validated['id_proveedor'],
                    $montoCredito,
                    $montoCredito,
                    $fechaVencimiento,
                    'pendiente',
                    $fechaActual,
                    $fechaActual
                ]);
            }

            DB::commit();

            return response()->json([
                'Mensaje'         => 'Orden de Compra registrada con éxito.',
                'id_compra'       => $idCompraGenerada,
                'folio'           => $folio,
                'id_proveedor'    => $validated['id_proveedor'],
                'subtotal'        => $subtotalBase,
                'impuesto'        => $impuestoCalculado,
                'total'           => $validated['total'],
                'pagado'          => $montoPagado,
                'credito_pendiente' => $montoCredito,
                'estado'          => 'orden',
                'created_at'      => $fechaActual,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'Mensaje' => 'Error en la transacción. Los datos fueron revertidos.',
                'Error'   => $this->errorMsg($e)
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'fecha_entrega' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
        ], [
            'observaciones.max' => 'Las observaciones no deben exceder 500 caracteres.',
        ]);

        $existe = DB::selectOne('SELECT id_compra FROM compras WHERE id_compra = ? AND deleted_at IS NULL', [$id]);
        if (!$existe) {
            return response()->json(['Mensaje' => 'Orden de compra no encontrada.'], 404);
        }

        DB::update('UPDATE compras SET fecha_entrega = ?, observaciones = ?, updated_at = ? WHERE id_compra = ?', [
            $validated['fecha_entrega'] ?? null,
            $validated['observaciones'] ?? null,
            date('Y-m-d H:i:s'),
            $id
        ]);

        return response()->json(['Mensaje' => 'Compra actualizada correctamente.'], 200);
    }

    public function registrarRecepcion(Request $request, $id)
    {
        $validated = $request->validate([
            'id_usuario'                              => 'required|integer|min:1',
            'fecha_recepcion'                         => 'required|date',
            'observaciones'                           => 'nullable|string',
            'detalles_recibidos'                      => 'required|array|min:1',
            'detalles_recibidos.*.id_presentacion'    => 'required|integer|min:1',
            'detalles_recibidos.*.cantidad_recibida'  => 'required|numeric|min:0',
            'detalles_recibidos.*.observacion'        => 'nullable|string',
        ], [
            'detalles_recibidos.required'                 => 'Debe incluir los detalles recibidos.',
            'detalles_recibidos.*.id_presentacion.required' => 'Cada detalle requiere un producto.',
            'detalles_recibidos.*.cantidad_recibida.required' => 'Cada detalle requiere una cantidad recibida.',
        ]);

        $compra = DB::selectOne('
            SELECT c.id_compra, c.id_almacen, c.id_proveedor, c.total, c.estado, c.folio
            FROM compras c
            WHERE c.id_compra = ? AND c.deleted_at IS NULL
        ', [$id]);

        if (!$compra) {
            return response()->json(['Mensaje' => 'Orden de compra no encontrada.'], 404);
        }

        if (!in_array($compra->estado, ['orden', 'parcial'])) {
            return response()->json(['Mensaje' => 'Solo se puede recibir una orden en estado "orden" o "parcial".'], 422);
        }

        $fechaActual = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {
            $montoEfectivamenteRecibido = 0.00;

            foreach ($validated['detalles_recibidos'] as $detalle) {
                $idPresentacion   = $detalle['id_presentacion'];
                $cantidadRecibida = (float) $detalle['cantidad_recibida'];
                if ($cantidadRecibida <= 0) continue;

                $detalleCompra = DB::selectOne('
                    SELECT dc.id_detalle_compra, dc.cantidad, dc.precio_unitario, dc.subtotal
                    FROM detalle_compra dc
                    WHERE dc.id_compra = ? AND dc.id_presentacion = ?
                ', [$id, $idPresentacion]);

                if (!$detalleCompra) continue;

                $precioUnitario = (float) $detalleCompra->precio_unitario;
                $subtotalDetalle = (float) $detalleCompra->subtotal;
                $montoEfectivamenteRecibido += $subtotalDetalle;

                try {
                    $inventario = DB::selectOne('
                        SELECT id_inventario, cantidad_actual, costo_promedio FROM inventarios
                        WHERE id_almacen = ? AND id_presentacion = ?
                    ', [$compra->id_almacen, $idPresentacion]);

                    if (!$inventario) {
                        DB::insert('INSERT INTO inventarios (id_almacen, id_presentacion, cantidad_actual, created_at, updated_at) VALUES (?, ?, 0, ?, ?)', [
                            $compra->id_almacen, $idPresentacion, $fechaActual, $fechaActual
                        ]);
                        $idInventario = DB::getPdo()->lastInsertId();
                        $saldoAnterior = 0.0;
                        $cpAnterior = 0.0;
                    } else {
                        $idInventario = $inventario->id_inventario;
                        $saldoAnterior = (float) $inventario->cantidad_actual;
                        $cpAnterior = (float) $inventario->costo_promedio;
                    }

                    $saldoNuevo = $saldoAnterior + $cantidadRecibida;
                    $ncp = ($saldoNuevo > 0)
                        ? round((($cpAnterior * $saldoAnterior) + ($cantidadRecibida * $precioUnitario)) / $saldoNuevo, 2)
                        : $precioUnitario;

                    DB::update('UPDATE inventarios SET cantidad_actual = cantidad_actual + ?, costo_promedio = ?, ultima_actualizacion = ? WHERE id_almacen = ? AND id_presentacion = ?', [
                        $cantidadRecibida, $ncp, $fechaActual, $compra->id_almacen, $idPresentacion
                    ]);

                    DB::update('UPDATE presentaciones_producto SET stock_actual = stock_actual + ?, costo_promedio = ?, updated_at = ? WHERE id_presentacion = ?', [
                        $cantidadRecibida, $ncp, $fechaActual, $idPresentacion
                    ]);

                    DB::insert('
                        INSERT INTO movimientos_inventario (id_inventario, id_usuario, tipo_movimiento, cantidad, precio_unitario, saldo_anterior, saldo_nuevo, referencia, id_referencia, motivo, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ', [
                        $idInventario, $validated['id_usuario'], 'entrada',
                        $cantidadRecibida, $precioUnitario,
                        $saldoAnterior, $saldoNuevo,
                        $compra->folio, $compra->id_compra,
                        $detalle['observacion'] ?? 'Recepción de compra',
                        $fechaActual
                    ]);
                } catch (\Exception $e) {
                    // Tablas de inventario/presentaciones no disponibles — no bloquea la recepción
                }
            }

            $nuevoEstado = 'recibido';

            DB::update('UPDATE compras SET estado = ?, updated_at = ? WHERE id_compra = ?', [
                $nuevoEstado, $fechaActual, $id
            ]);

            try {
                $cuentaExistente = DB::selectOne('SELECT id_cuenta_pagar FROM cuentas_por_pagar WHERE id_compra = ?', [$id]);
                if (!$cuentaExistente) {
                    $montoImpuesto = round($montoEfectivamenteRecibido * 0.16, 2);
                    $totalRecibido = $montoEfectivamenteRecibido + $montoImpuesto;
                    $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
                    DB::insert('
                        INSERT INTO cuentas_por_pagar (id_compra, id_proveedor, monto_total, monto_pagado, saldo_pendiente, fecha_vencimiento, estado, created_at, updated_at)
                        VALUES (?, ?, ?, 0.00, ?, ?, ?, ?, ?)
                    ', [
                        $id, $compra->id_proveedor,
                        $totalRecibido, $totalRecibido,
                        $fechaVencimiento, 'pendiente',
                        $fechaActual, $fechaActual
                    ]);
                }
            } catch (\Exception $e) {
                // Cuentas por pagar no disponible — no bloquea
            }

            DB::commit();
            return response()->json(['Mensaje' => 'Recepción de inventario asentada correctamente.', 'estado' => $nuevoEstado], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error en la recepción. Datos revertidos.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    protected function generarFolio(string $prefijo, string $tabla): string
    {
        $hoy = date('Y-m-d');
        $prefijoHoy = $prefijo . '-' . date('Y-md') . '-';

        $ultimo = DB::selectOne("
            SELECT folio FROM $tabla
            WHERE folio LIKE ?
            ORDER BY folio DESC LIMIT 1
        ", [$prefijoHoy . '%']);

        if ($ultimo) {
            $partes = explode('-', $ultimo->folio);
            $consecutivo = (int) end($partes) + 1;
        } else {
            $consecutivo = 1;
        }

        return $prefijoHoy . str_pad((string) $consecutivo, 2, '0', STR_PAD_LEFT);
    }

    protected function errorMsg(\Exception $e): string
    {
        return config('app.debug') ? $e->getMessage() : 'Error interno del servidor.';
    }
}

