<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VentaController extends Controller
{
    public function index(): JsonResponse
    {
        // Consulta directa para el historial
        $ventas = DB::table('ventas')
            ->orderBy('fecha', 'desc')
            ->take(50)
            ->get();

        return response()->json(['success' => true, 'data' => $ventas]);
    }

        public function store(Request $request): JsonResponse
    {
        // 1. Validación directamente en el controlador
        $data = $request->validate([
            'id_usuario' => 'required|integer',
            'id_sucursal' => 'required|integer',
            'id_caja' => 'required|integer',
            'id_sesion_caja' => 'required|integer',
            'id_cliente' => 'nullable|integer',
            'productos' => 'required|array|min:1',
            'productos.*.id_presentacion' => 'required|integer',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio' => 'required|numeric|min:0',
            'pagos' => 'required|array|min:1',
            'pagos.*.id_forma_pago' => 'required|integer',
            'pagos.*.monto' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            // --- VALIDACIÓN DE SESIÓN DE CAJA ---
            if (Schema::hasTable('sesiones_caja')) {
                $sesion = DB::table('sesiones_caja')
                    ->where('id_sesion_caja', $data['id_sesion_caja'])
                    ->where('estado', 'abierta')
                    ->first();

                if (!$sesion) {
                    // Si la sesión no está abierta, lanzamos el error y se va al catch
                    throw new \Exception("No hay una sesión de caja abierta para el ID proporcionado.");
                }
            }

            // 2. Calcular Totales
            $subtotal = 0;
            $impuestos = 0;
            $ivaPorcentaje = 0.18;

            foreach ($data['productos'] as $producto) {
                $subtotal += ($producto['cantidad'] * $producto['precio']);
                $impuestos += ($producto['cantidad'] * $producto['precio']) * $ivaPorcentaje;
            }

            $totalAPagar = $subtotal + $impuestos;
            $totalPagado = array_sum(array_column($data['pagos'], 'monto'));
            
            if ($totalPagado < $totalAPagar) {
                throw new \Exception("El monto pagado ({$totalPagado}) es insuficiente. Total a pagar: {$totalAPagar}");
            }

            $cambio = $totalPagado - $totalAPagar;
            $folio = 'VEN-' . time();

                        // 3. Inserción directa en la tabla ventas
            $id_venta = DB::table('ventas')->insertGetId([
                'folio' => 'TEMP', // Folio temporal
                'id_cliente' => $data['id_cliente'] ?? 1, // Cliente genérico por defecto
                'id_usuario' => $data['id_usuario'],
                'id_sucursal' => $data['id_sucursal'],
                'id_caja' => $data['id_caja'],
                'id_sesion_caja' => $data['id_sesion_caja'],
                'fecha' => now(),
                'subtotal' => $subtotal,
                'descuento' => 0.00,
                'impuestos' => $impuestos,
                'total' => $totalAPagar,
                'cambio' => $cambio,
                'tipo_pago' => count($data['pagos']) > 1 ? 'mixto' : 'efectivo',
                'estado' => 'completada',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // PUNTO 6: Folio secuencial empezando desde 1
            $folio = 'VEN-' . str_pad($id_venta, 5, '0', STR_PAD_LEFT); // Ej: VEN-00001
            DB::table('ventas')->where('id_venta', $id_venta)->update(['folio' => $folio]);

            // 4. Insertar Detalles de Venta y Descontar Inventario
            foreach ($data['productos'] as $producto) {
                DB::table('detalle_venta')->insert([
                    'id_venta' => $id_venta,
                    'id_presentacion' => $producto['id_presentacion'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                    'descuento' => 0.00,
                    'impuesto' => ($producto['precio'] * $producto['cantidad']) * $ivaPorcentaje,
                    'subtotal' => $producto['precio'] * $producto['cantidad'],
                    'peso' => $producto['peso'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Descontar Inventario y registrar movimiento
                if (Schema::hasTable('inventarios')) {
                    $inventario = DB::table('inventarios')
                        ->where('id_presentacion', $producto['id_presentacion'])
                        ->first();

                    if ($inventario) {
                        $saldoAnterior = $inventario->cantidad_actual;
                        $nuevoSaldo = $saldoAnterior - $producto['cantidad'];

                        DB::table('inventarios')
                            ->where('id_inventario', $inventario->id_inventario)
                            ->update(['cantidad_actual' => $nuevoSaldo]);

                        DB::table('movimientos_inventario')->insert([
                            'id_inventario' => $inventario->id_inventario,
                            'id_usuario' => $data['id_usuario'],
                            'tipo_movimiento' => 'salida',
                            'cantidad' => $producto['cantidad'],
                            'saldo_anterior' => $saldoAnterior,
                            'saldo_nuevo' => $nuevoSaldo,
                            'referencia' => $folio,
                            'motivo' => 'Venta en POS',
                            'created_at' => now()
                        ]);
                    }
                }
            }


            // 5. Insertar Pagos
            foreach ($data['pagos'] as $pago) {
                DB::table('pagos_venta')->insert([
                    'id_venta' => $id_venta,
                    'id_usuario' => $data['id_usuario'],
                    'id_forma_pago' => $pago['id_forma_pago'],
                    'fecha_pago' => now()->toDateString(),
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'autorizacion' => $pago['autorizacion'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

                        // 6. Registrar Movimiento de Caja
            if (Schema::hasTable('movimientos_caja')) {
                DB::table('movimientos_caja')->insert([
                    'id_sesion_caja' => $data['id_sesion_caja'],
                    'id_usuario' => $data['id_usuario'],
                    'tipo' => 'ingreso',
                    'monto' => $totalAPagar,
                    'concepto' => 'Venta: ' . $folio, // <-- CAMBIADO A 'concepto'
                    'referencia_tipo' => 'venta',     // <-- AGREGADO
                    'id_referencia' => $id_venta,     // <-- AGREGADO
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // 7. Responder al Frontend
            return response()->json([
                'success' => true,
                'message' => 'Venta registrada correctamente',
                'data' => [
                    'id_venta' => $id_venta,
                    'folio' => $folio,
                    'total' => $totalAPagar,
                    'cambio' => $cambio,
                    'abrir_cajon' => true
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Nos aseguramos de SIEMPRE devolver un JSON, incluso si hay error
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta: ' . $e->getMessage()
            ], 500);
        }
    }
}