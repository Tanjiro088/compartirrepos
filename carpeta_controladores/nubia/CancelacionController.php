<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CancelacionController extends Controller
{
    public function cancelar(Request $request)
    {
        $data = $request->validate([
            'id_venta' => 'required|integer|exists:ventas,id_venta',
            'id_usuario' => 'required|integer',
            'motivo' => 'required|string|max:255',
            'tipo_cancelacion' => 'required|in:total,parcial',
            'monto_reembolsado' => 'required|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $venta = DB::table('ventas')->where('id_venta', $request->id_venta)->first();

            if (!$venta || $venta->estado === 'cancelada') {
                throw new \Exception("La venta no existe o ya está cancelada.");
            }

            // 1. Registrar la cancelación
            DB::table('cancelaciones_venta')->insert([
                'id_venta' => $venta->id_venta,
                'id_usuario' => $request->id_usuario,
                'fecha_cancelacion' => now(),
                'motivo' => $request->motivo,
                'tipo_cancelacion' => $request->tipo_cancelacion,
                'monto_reembolsado' => $request->monto_reembolsado,
                'id_venta_original' => $request->tipo_cancelacion === 'parcial' ? $venta->id_venta : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Cambiar estado de la venta
            DB::table('ventas')->where('id_venta', $venta->id_venta)->update([
                'estado' => 'cancelada',
                'updated_at' => now()
            ]);

            // 3. Revertir Inventario y registrar movimiento de devolución
            $detalles = DB::table('detalle_venta')->where('id_venta', $venta->id_venta)->get();
            
            foreach ($detalles as $detalle) {
                if (Schema::hasTable('inventarios')) {
                    $inventario = DB::table('inventarios')
                        ->where('id_presentacion', $detalle->id_presentacion)
                        ->first();

                    if ($inventario) {
                        $saldoAnterior = $inventario->cantidad_actual;
                        $nuevoSaldo = $saldoAnterior + $detalle->cantidad; // Sumamos lo que se vendió

                        DB::table('inventarios')
                            ->where('id_inventario', $inventario->id_inventario)
                            ->update(['cantidad_actual' => $nuevoSaldo]);

                        DB::table('movimientos_inventario')->insert([
                            'id_inventario' => $inventario->id_inventario,
                            'id_usuario' => $request->id_usuario,
                            'tipo_movimiento' => 'devolucion', // Movimiento de entrada
                            'cantidad' => $detalle->cantidad,
                            'saldo_anterior' => $saldoAnterior,
                            'saldo_nuevo' => $nuevoSaldo,
                            'referencia' => 'Cancelación Venta: ' . $venta->folio,
                            'motivo' => $request->motivo,
                            'created_at' => now()
                        ]);
                    }
                }
            }

            // 4. Revertir movimiento de caja (Egreso)
            if (Schema::hasTable('movimientos_caja')) {
                DB::table('movimientos_caja')->insert([
                    'id_sesion_caja' => $venta->id_sesion_caja,
                    'id_usuario' => $request->id_usuario,
                    'tipo' => 'egreso', // Egreso por cancelación
                    'monto' => $request->monto_reembolsado,
                    'concepto' => 'Cancelación: ' . $venta->folio,
                    'referencia_tipo' => 'cancelacion',
                    'id_referencia' => $venta->id_venta,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada correctamente e inventario revertido.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar: ' . $e->getMessage()
            ], 500);
        }
    }
}