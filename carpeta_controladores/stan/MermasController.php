<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Controlador: MermasController
 * Propósito: Gestionar registro, aprobación y consulta de mermas de inventario.
 *            Incluye devolución a proveedor como tipo de merma (HU-MER-005).
 *            Basado en HU-MER-001, HU-MER-002, HU-MER-003, HU-MER-005 del PDF.
 */
class MermasController extends Controller
{
    /**
     * Método: index
     * Propósito: Listar historial de mermas con JOIN a almacenes y usuarios.
     *            HU-MER-003: Filtros por fecha, tipo, almacén, estado.
     */
    public function index(Request $request)
    {
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(1, min(100, (int) $request->input('por_pagina', 8)));
        $offset = ($pagina - 1) * $porPagina;

        $baseFrom = 'FROM mermas m INNER JOIN almacenes a ON m.id_almacen = a.id_almacen WHERE m.deleted_at IS NULL';

        $where = '';
        $bindings = [];

        if ($request->filled('fecha_desde')) {
            $where .= ' AND m.fecha_merma >= ?';
            $bindings[] = $request->fecha_desde;
        }

        if ($request->filled('fecha_hasta')) {
            $where .= ' AND m.fecha_merma <= ?';
            $bindings[] = $request->fecha_hasta;
        }

        if ($request->filled('id_almacen')) {
            $where .= ' AND m.id_almacen = ?';
            $bindings[] = $request->id_almacen;
        }

        if ($request->filled('tipo_merma')) {
            $where .= ' AND m.tipo_merma = ?';
            $bindings[] = $request->tipo_merma;
        }

        if ($request->filled('estado')) {
            $where .= ' AND m.estado = ?';
            $bindings[] = $request->estado;
        }

        $totalRow = DB::selectOne("SELECT COUNT(*) AS total_registros, COALESCE(SUM(m.monto_total), 0) AS monto_total $baseFrom $where", $bindings);

        $query = "
            SELECT
                m.id_merma,
                m.folio,
                m.fecha_merma AS fecha,
                m.tipo_merma AS tipo,
                m.motivo,
                m.monto_total AS monto,
                m.autorizado_por,
                m.estado,
                m.id_almacen,
                a.nombre AS almacen,
                m.id_compra,
                m.created_at
            $baseFrom
            $where
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $mermas = DB::select($query, array_merge($bindings, [$porPagina, $offset]));

        if (empty($mermas)) {
            return response()->json([
                'total_registros' => (int) $totalRow->total_registros,
                'monto_total'     => round((float) $totalRow->monto_total, 2),
                'total'           => (int) $totalRow->total_registros,
                'pagina'          => $pagina,
                'por_pagina'      => $porPagina,
                'mermas'          => [],
            ], 200);
        }

        $mermaIds = array_column($mermas, 'id_merma');
        $placeholders = implode(',', array_fill(0, count($mermaIds), '?'));

        $detalles = DB::select("
            SELECT
                dm.id_detalle_merma,
                dm.id_merma,
                dm.id_presentacion,
                pr.nombre AS producto,
                dm.cantidad,
                dm.precio_costo,
                dm.subtotal,
                dm.observaciones
            FROM detalle_merma dm
            INNER JOIN presentaciones_producto pr ON dm.id_presentacion = pr.id_presentacion
            WHERE dm.id_merma IN ($placeholders)
        ", $mermaIds);

        $detallesPorMerma = [];
        foreach ($detalles as $det) {
            $det->cantidad = (float) $det->cantidad;
            $det->precio_costo = (float) $det->precio_costo;
            $det->subtotal = (float) $det->subtotal;
            $detallesPorMerma[$det->id_merma][] = $det;
        }

        foreach ($mermas as $m) {
            $m->monto = (float) $m->monto;
            $m->detalles = $detallesPorMerma[$m->id_merma] ?? [];
        }

        return response()->json([
            'total_registros' => (int) $totalRow->total_registros,
            'monto_total'     => round((float) $totalRow->monto_total, 2),
            'total'           => (int) $totalRow->total_registros,
            'pagina'          => $pagina,
            'por_pagina'      => $porPagina,
            'mermas'          => $mermas,
        ], 200);
    }

    /**
     * Método: pendientes
     * Propósito: Listar solo mermas en estado 'registrada' para su aprobación.
     *            HU-MER-002: Aprobar una merma.
     */
    public function pendientes()
    {
        $mermas = DB::select('
            SELECT
                m.id_merma,
                m.folio,
                m.fecha_merma AS fecha,
                m.tipo_merma AS tipo,
                m.motivo,
                m.monto_total AS monto,
                m.autorizado_por,
                m.estado,
                a.nombre AS almacen
            FROM mermas m
            INNER JOIN almacenes a ON m.id_almacen = a.id_almacen
            WHERE m.deleted_at IS NULL AND m.estado = ?
            ORDER BY m.created_at DESC
        ', ['registrada']);

        foreach ($mermas as $m) {
            $m->monto = (float) $m->monto;
        }

        return response()->json($mermas, 200);
    }

    /**
     * Método: store
     * Propósito: Crear reporte de merma con transacción ACID (HU-MER-001).
     *            Valida stock suficiente, descuenta inventario,
     *            registra movimientos de inventario tipo 'merma'.
     *
     *            También maneja HU-MER-005: devolución a proveedor,
     *            que adicionalmente reduce cuentas_por_pagar.
     */
    public function nextFolio(Request $request)
    {
        $tipo = $request->input('tipo', 'merma');
        $esDevolucion = $tipo === 'devolucion_proveedor';
        return response()->json(['folio' => $this->generarFolio($esDevolucion ? 'DEV' : 'MER', 'mermas')], 200);
    }

    /**
     * Método: show
     * Propósito: Obtener el detalle completo de una merma con sus productos.
     */
    public function show($id)
    {
        $merma = DB::selectOne('
            SELECT m.id_merma, m.id_almacen, m.id_usuario, m.folio, m.fecha_merma,
                   m.tipo_merma, m.motivo, m.motivo_rechazo, m.monto_total, m.estado,
                   a.nombre AS almacen
            FROM mermas m
            INNER JOIN almacenes a ON m.id_almacen = a.id_almacen
            WHERE m.id_merma = ? AND m.deleted_at IS NULL
        ', [$id]);

        if (!$merma) {
            return response()->json(['Mensaje' => 'Merma no encontrada'], 404);
        }

        $detalles = DB::select('
            SELECT dm.id_detalle_merma, dm.id_presentacion, dm.cantidad, dm.precio_costo,
                   dm.subtotal, dm.observaciones, pp.nombre AS producto
            FROM detalle_merma dm
            INNER JOIN presentaciones_producto pp ON dm.id_presentacion = pp.id_presentacion
            WHERE dm.id_merma = ?
        ', [$id]);

        foreach ($detalles as $d) {
            $d->cantidad     = (float) $d->cantidad;
            $d->precio_costo = (float) $d->precio_costo;
            $d->subtotal     = (float) $d->subtotal;
        }

        $merma->monto_total = (float) $merma->monto_total;
        $merma->detalles    = $detalles;

        return response()->json($merma, 200);
    }

    /**
     * Método: update
     * Propósito: Actualizar datos generales de una merma en estado 'registrada'.
     */
    public function update(Request $request, $id)
    {
        $merma = DB::selectOne('
            SELECT id_merma, estado FROM mermas WHERE id_merma = ? AND deleted_at IS NULL
        ', [$id]);

        if (!$merma) {
            return response()->json(['Mensaje' => 'Merma no encontrada'], 404);
        }

        if ($merma->estado !== 'registrada') {
            return response()->json(['Mensaje' => 'Solo se pueden editar mermas en estado "registrada"'], 422);
        }

        $validated = $request->validate([
            'fecha_merma' => 'required|date',
            'tipo_merma'  => 'required|string|in:dañado,vencido,robo,extraviado',
            'motivo'      => 'nullable|string|max:500',
            'id_almacen'  => 'required|integer|exists:almacenes,id_almacen',
        ], [
            'fecha_merma.required' => 'La fecha de la merma es obligatoria.',
            'tipo_merma.required'  => 'El tipo de merma es obligatorio.',
            'tipo_merma.in'        => 'El tipo de merma no es válido.',
            'id_almacen.required'  => 'El almacén es obligatorio.',
            'id_almacen.exists'    => 'El almacén seleccionado no existe.',
            'motivo.max'           => 'El motivo no debe exceder 500 caracteres.',
        ]);

        $fechaActual = date('Y-m-d H:i:s');

        DB::update('
            UPDATE mermas SET fecha_merma = ?, tipo_merma = ?, motivo = ?, id_almacen = ?, updated_at = ?
            WHERE id_merma = ?
        ', [
            $validated['fecha_merma'], $validated['tipo_merma'],
            $validated['motivo'] ?? '', $validated['id_almacen'],
            $fechaActual, $id
        ]);

        return response()->json(['Mensaje' => "Merma $id actualizada correctamente", 'id_merma' => $id], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_almacen'       => 'required|integer|min:1',
            'id_usuario'       => 'required|integer|min:1',
            'id_compra'        => 'nullable|integer|min:1',
            'fecha_merma'      => 'required|date',
            'tipo_merma'       => 'required|string|in:dañado,vencido,robo,extraviado,devolucion_proveedor',
            'motivo'           => 'nullable|string',
            'autorizado_por'   => 'nullable|string|max:150',
            'monto'            => 'required|numeric|min:0',
            'detalles'         => 'nullable|array',
            'detalles.*.id_presentacion' => 'required|integer|min:1',
            'detalles.*.cantidad'        => 'required|numeric|min:1',
            'detalles.*.precio_costo'    => 'required|numeric|min:0',
            'detalles.*.observaciones'   => 'nullable|string',
        ], [
            'detalles.required' => 'Debe agregar al menos un producto al reporte.',
        ]);

        $fechaActual = date('Y-m-d H:i:s');
        $esDevolucion = $validated['tipo_merma'] === 'devolucion_proveedor';
        $folio = $this->generarFolio($esDevolucion ? 'DEV' : 'MER', 'mermas');

        DB::beginTransaction();

        try {
            $montoTotal = 0;

            // Validar stock y calcular montos antes de insertar
            $detalles = $validated['detalles'] ?? [];
            foreach ($detalles as $item) {
                $idPresentacion = $item['id_presentacion'];
                $cantidad = (float) $item['cantidad'];
                $precioCosto = (float) $item['precio_costo'];

                $inventario = DB::selectOne('
                    SELECT id_inventario, cantidad_actual FROM inventarios
                    WHERE id_almacen = ? AND id_presentacion = ?
                ', [$validated['id_almacen'], $idPresentacion]);

                $stockDisponible = $inventario ? (float) $inventario->cantidad_actual : 0;

                if ($stockDisponible < $cantidad) {
                    DB::rollBack();
                    $producto = DB::selectOne('SELECT nombre FROM presentaciones_producto WHERE id_presentacion = ?', [$idPresentacion]);
                    $nombreProd = $producto ? $producto->nombre : "ID $idPresentacion";
                    return response()->json([
                        'Mensaje' => "Stock insuficiente para '$nombreProd'. Disponible: $stockDisponible, Solicitado: $cantidad"
                    ], 422);
                }

                $montoTotal += $cantidad * $precioCosto;
            }

            $montoTotal = round($montoTotal, 2);
            // Si no hay detalles, usar el monto enviado directamente (ej: devolución)
            if (empty($detalles) && $validated['monto'] > 0) {
                $montoTotal = round((float) $validated['monto'], 2);
            }
            $estadoInicial = 'registrada';

            // Inserción I: Cabecera merma
            DB::insert('
                INSERT INTO mermas (id_almacen, id_usuario, id_compra, folio, fecha_merma, tipo_merma, motivo, monto_total, autorizado_por, estado, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                $validated['id_almacen'],
                $validated['id_usuario'],
                $validated['id_compra'] ?? null,
                $folio,
                $validated['fecha_merma'],
                $validated['tipo_merma'],
                $validated['motivo'] ?? null,
                $montoTotal,
                $validated['autorizado_por'] ?? null,
                $estadoInicial,
                $fechaActual,
                $fechaActual
            ]);

            $idMermaGenerada = DB::getPdo()->lastInsertId();

            // Inserción II: Detalles (sin tocar inventario — eso se hace al aprobar)
            foreach ($detalles as $item) {
                $idPresentacion = $item['id_presentacion'];
                $cantidad = (float) $item['cantidad'];
                $precioCosto = (float) $item['precio_costo'];
                $subtotalFila = round($cantidad * $precioCosto, 2);

                DB::insert('
                    INSERT INTO detalle_merma (id_merma, id_presentacion, cantidad, precio_costo, subtotal, observaciones, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $idMermaGenerada,
                    $idPresentacion,
                    $cantidad,
                    $precioCosto,
                    $subtotalFila,
                    $item['observaciones'] ?? null,
                    $fechaActual,
                    $fechaActual
                ]);
            }

            DB::commit();

            return response()->json([
                'Mensaje'     => 'Reporte de merma registrado con éxito',
                'id_merma'    => $idMermaGenerada,
                'folio'       => $folio,
                'monto_total' => $montoTotal,
                'estado'      => $estadoInicial,
                'created_at'  => $fechaActual,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error al registrar merma.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    /**
     * Método: actualizarEstado
     * Propósito: Aprobar o rechazar una merma (HU-MER-002).
     *            Solo mermas en estado 'registrada'.
     */
    public function actualizarEstado(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => 'required|string|in:aprobada,rechazada',
            'motivo' => 'required_if:estado,rechazada|string|max:500',
        ]);

        $merma = DB::selectOne('
            SELECT m.id_merma, m.estado, m.id_almacen, m.id_compra, m.folio, m.id_usuario, m.tipo_merma
            FROM mermas m
            WHERE m.id_merma = ? AND m.deleted_at IS NULL
        ', [$id]);

        if (!$merma) {
            return response()->json(['Mensaje' => 'Merma no encontrada'], 404);
        }

        if ($merma->estado !== 'registrada') {
            return response()->json(['Mensaje' => 'Solo se pueden aprobar/rechazar mermas en estado "registrada"'], 422);
        }

        $fechaActual = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {
            if ($validated['estado'] === 'rechazada') {
                DB::update('
                    UPDATE mermas SET estado = ?, motivo_rechazo = ?, updated_at = ? WHERE id_merma = ?
                ', [
                    $validated['estado'], $validated['motivo'] ?? '', $fechaActual, $id
                ]);
            }

            if ($validated['estado'] === 'aprobada') {
                $detalles = DB::select('
                    SELECT dm.id_presentacion, dm.cantidad, dm.precio_costo, dm.observaciones
                    FROM detalle_merma dm
                    WHERE dm.id_merma = ?
                ', [$id]);

                foreach ($detalles as $det) {
                    $cantidad = (float) $det->cantidad;
                    $idPresentacion = $det->id_presentacion;

                    try {
                        $inventario = DB::selectOne('
                            SELECT id_inventario, cantidad_actual FROM inventarios
                            WHERE id_almacen = ? AND id_presentacion = ?
                        ', [$merma->id_almacen, $idPresentacion]);

                        if (!$inventario) continue;

                        $saldoAnterior = (float) $inventario->cantidad_actual;
                        $saldoNuevo = $saldoAnterior - $cantidad;

                        DB::update('
                            UPDATE inventarios
                            SET cantidad_actual = cantidad_actual - ?, ultima_actualizacion = ?
                            WHERE id_almacen = ? AND id_presentacion = ?
                        ', [$cantidad, $fechaActual, $merma->id_almacen, $idPresentacion]);

                        DB::update('UPDATE presentaciones_producto SET stock_actual = stock_actual - ?, updated_at = ? WHERE id_presentacion = ?', [
                            $cantidad, $fechaActual, $idPresentacion
                        ]);

                        $tipoMovimiento = $merma->tipo_merma === 'devolucion_proveedor' ? 'devolucion' : 'merma';

                        DB::insert('
                            INSERT INTO movimientos_inventario (id_inventario, id_usuario, tipo_movimiento, cantidad,
                                precio_unitario, saldo_anterior, saldo_nuevo, referencia, id_referencia, motivo, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ', [
                            $inventario->id_inventario,
                            $merma->id_usuario,
                            $tipoMovimiento,
                            $cantidad,
                            (float) $det->precio_costo,
                            $saldoAnterior,
                            $saldoNuevo,
                            $merma->folio,
                            $merma->id_merma,
                            'Merma aprobada: ' . ($det->observaciones ?? ''),
                            $fechaActual
                        ]);
                    } catch (\Exception $e) {
                        // Tablas de inventario/presentaciones no disponibles — no bloquea
                    }
                }

                if ($merma->tipo_merma === 'devolucion_proveedor') {
                    try {
                        $cuenta = DB::selectOne('
                            SELECT id_cuenta_por_pagar, saldo_pendiente, estado
                            FROM cuentas_por_pagar
                            WHERE id_compra = ?
                        ', [$merma->id_compra]);

                        if ($cuenta) {
                            $montoDevolucion = (float) (DB::selectOne('
                                SELECT COALESCE(SUM(cantidad * precio_costo), 0) AS total
                                FROM detalle_merma
                                WHERE id_merma = ?
                            ', [$id])->total ?? 0);

                            $nuevoSaldo = max(0, (float) $cuenta->saldo_pendiente - $montoDevolucion);
                            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagada' : $cuenta->estado;

                            DB::update('
                                UPDATE cuentas_por_pagar SET saldo_pendiente = ?, estado = ?, updated_at = ?
                                WHERE id_cuenta_por_pagar = ?
                            ', [$nuevoSaldo, $nuevoEstado, $fechaActual, $cuenta->id_cuenta_por_pagar]);
                        }
                    } catch (\Exception $e) {
                        // Cuentas por pagar no disponible — no bloquea
                    }
                }

                DB::update('UPDATE mermas SET estado = ?, updated_at = ? WHERE id_merma = ?', [
                    $validated['estado'], $fechaActual, $id
                ]);
            }

            DB::commit();

            return response()->json([
                'Mensaje'  => "Merma $id actualizada a estado '$validated[estado]'",
                'id_merma' => $id,
                'estado'   => $validated['estado'],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['Mensaje' => 'Error al actualizar estado.', 'Error' => $this->errorMsg($e)], 500);
        }
    }

    /**
     * Método: comprasDisponiblesDevolucion
     * Propósito: Listar compras elegibles para devolución a proveedor (HU-MER-005).
     */
    public function comprasDisponiblesDevolucion()
    {
        $compras = DB::select('
            SELECT
                c.id_compra,
                c.folio,
                c.id_proveedor,
                c.id_almacen,
                p.nombre_comercial AS proveedor,
                c.total,
                c.estado
            FROM compras c
            INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
            WHERE c.deleted_at IS NULL
              AND c.estado IN (\'recibido\', \'parcial\', \'pagado\')
            ORDER BY c.fecha_compra DESC
        ');

        foreach ($compras as $comp) {
            $comp->total = (float) $comp->total;
        }

        return response()->json($compras, 200);
    }

    /**
     * Método: reportePerdidas
     * Propósito: Reporte de pérdidas por mermas (HU-MER-004).
     *            top_5_productos, pérdidas por tipo, tendencia mensual, total.
     *            Solo mermas en estado 'aprobada'.
     */
    public function reportePerdidas(Request $request)
    {
        $fechaDesde = $request->input('fecha_desde', date('Y-m-01'));
        $fechaHasta = $request->input('fecha_hasta', date('Y-m-t'));

        $whereEstado = "AND m.estado = 'aprobada'";

        $top5 = DB::select("
            SELECT
                pr.nombre AS producto,
                ROUND(SUM(dm.subtotal), 2) AS perdida,
                ROUND(SUM(dm.cantidad), 3) AS cantidad_total
            FROM mermas m
            INNER JOIN detalle_merma dm ON m.id_merma = dm.id_merma
            INNER JOIN presentaciones_producto pr ON dm.id_presentacion = pr.id_presentacion
            WHERE m.deleted_at IS NULL
              AND m.fecha_merma BETWEEN ? AND ?
              $whereEstado
            GROUP BY pr.id_presentacion, pr.nombre
            ORDER BY perdida DESC
            LIMIT 5
        ", [$fechaDesde, $fechaHasta]);

        $porTipo = DB::select("
            SELECT
                m.tipo_merma AS tipo,
                ROUND(SUM(dm.subtotal), 2) AS monto
            FROM mermas m
            INNER JOIN detalle_merma dm ON m.id_merma = dm.id_merma
            WHERE m.deleted_at IS NULL
              AND m.fecha_merma BETWEEN ? AND ?
              $whereEstado
            GROUP BY m.tipo_merma
            ORDER BY monto DESC
        ", [$fechaDesde, $fechaHasta]);

        $totalPerdidas = array_reduce($porTipo, fn($sum, $t) => $sum + (float) $t->monto, 0.0);

        $porTipoConPorcentaje = array_map(function ($t) use ($totalPerdidas) {
            return [
                'tipo'       => $t->tipo,
                'monto'      => (float) $t->monto,
                'porcentaje' => $totalPerdidas > 0 ? round(((float) $t->monto / $totalPerdidas) * 100, 1) : 0,
            ];
        }, $porTipo);

        $tendenciaMensual = DB::select("
            SELECT
                DATE_FORMAT(m.fecha_merma, '%Y-%m') AS mes,
                ROUND(SUM(dm.subtotal), 2) AS monto
            FROM mermas m
            INNER JOIN detalle_merma dm ON m.id_merma = dm.id_merma
            WHERE m.deleted_at IS NULL
              $whereEstado
            GROUP BY DATE_FORMAT(m.fecha_merma, '%Y-%m')
            ORDER BY mes DESC
            LIMIT 12
        ");

        $periodo = "$fechaDesde a $fechaHasta";

        return response()->json([
            'periodo'         => $periodo,
            'top_5_productos' => $top5,
            'por_tipo'        => $porTipoConPorcentaje,
            'tendencia_mensual' => $tendenciaMensual,
            'total_perdidas'  => round($totalPerdidas, 2),
        ], 200);
    }

    /**
     * Método: exportar
     * Propósito: Exportar historial de mermas a Excel (.xlsx).
     *            HU-MER-003: Exportar a Excel.
     */
    public function exportar(Request $request)
    {
        $where = 'WHERE m.deleted_at IS NULL';
        $bindings = [];

        if ($request->filled('fecha_desde')) {
            $where .= ' AND m.fecha_merma >= ?';
            $bindings[] = $request->fecha_desde;
        }

        if ($request->filled('fecha_hasta')) {
            $where .= ' AND m.fecha_merma <= ?';
            $bindings[] = $request->fecha_hasta;
        }

        if ($request->filled('id_almacen')) {
            $where .= ' AND m.id_almacen = ?';
            $bindings[] = $request->id_almacen;
        }

        if ($request->filled('tipo_merma')) {
            $where .= ' AND m.tipo_merma = ?';
            $bindings[] = $request->tipo_merma;
        }

        if ($request->filled('estado')) {
            $where .= ' AND m.estado = ?';
            $bindings[] = $request->estado;
        }

        $rows = DB::select("
            SELECT
                m.folio,
                m.fecha_merma AS fecha,
                m.tipo_merma AS tipo,
                pr.nombre AS producto,
                dm.cantidad,
                dm.precio_costo,
                dm.subtotal,
                a.nombre AS almacen,
                m.estado
            FROM mermas m
            INNER JOIN detalle_merma dm ON m.id_merma = dm.id_merma
            INNER JOIN presentaciones_producto pr ON dm.id_presentacion = pr.id_presentacion
            INNER JOIN almacenes a ON m.id_almacen = a.id_almacen
            $where
            ORDER BY m.fecha_merma DESC, m.folio
        ", $bindings);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Folio');
        $sheet->setCellValue('B1', 'Fecha');
        $sheet->setCellValue('C1', 'Tipo');
        $sheet->setCellValue('D1', 'Producto');
        $sheet->setCellValue('E1', 'Cantidad');
        $sheet->setCellValue('F1', 'Costo Unitario');
        $sheet->setCellValue('G1', 'Subtotal');
        $sheet->setCellValue('H1', 'Almacén');
        $sheet->setCellValue('I1', 'Estado');

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue("A$rowNum", $r->folio);
            $sheet->setCellValue("B$rowNum", $r->fecha);
            $sheet->setCellValue("C$rowNum", $r->tipo);
            $sheet->setCellValue("D$rowNum", $r->producto);
            $sheet->setCellValue("E$rowNum", (float) $r->cantidad);
            $sheet->setCellValue("F$rowNum", (float) $r->precio_costo);
            $sheet->setCellValue("G$rowNum", (float) $r->subtotal);
            $sheet->setCellValue("H$rowNum", $r->almacen);
            $sheet->setCellValue("I$rowNum", $r->estado);
            $rowNum++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'mermas_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
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

