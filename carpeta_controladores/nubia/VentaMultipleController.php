<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaMultipleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'id_usuario' => 'required|integer',
            'id_sesion_caja' => 'required|integer',
            'ventas' => 'required|array|min:2', // Mínimo 2 ventas para ser múltiple
            'ventas.*.productos' => 'required|array',
            'pagos' => 'required|array'
        ]);

        try {
            DB::beginTransaction();

            $folioVM = 'VM-' . time();

            // 1. Crear el encabezado de la venta múltiple (Query Builder puro)
            $idVentaMultiple = DB::table('ventas_multiples')->insertGetId([
                'folio' => $folioVM,
                'id_usuario' => $request->id_usuario,
                'id_sesion_caja' => $request->id_sesion_caja,
                'fecha' => now(),
                'estado' => 'activa',
                'subtotal' => 0.00, // Se calcula después
                'descuento' => 0.00,
                'impuestos' => 0.00,
                'total' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalGlobal = 0;
            $impuestosGlobal = 0;
            $ivaPorcentaje = 0.18; // 18%

            // 2. Procesar cada sub-venta
            foreach ($request->ventas as $subVentaData) {
                $subtotalSubVenta = 0;
                
                foreach ($subVentaData['productos'] as $prod) {
                    // Usamos el precio que envía el frontend (como acordamos en el paso anterior)
                    $precio = $prod['precio'] ?? 20.00; 
                    $subtotalSubVenta += $precio * $prod['cantidad'];
                }

                $impuestosSubVenta = $subtotalSubVenta * $ivaPorcentaje;
                $totalSubVenta = $subtotalSubVenta + $impuestosSubVenta;
                
                $totalGlobal += $totalSubVenta;
                $impuestosGlobal += $impuestosSubVenta;

                // Insertar la sub-venta usando Query Builder
                $idSubVenta = DB::table('ventas')->insertGetId([
                    'folio' => 'SUB-' . time() . '-' . rand(100, 999),
                    'id_usuario' => $request->id_usuario,
                    'id_sucursal' => 1, // Simulado
                    'id_caja' => 1, // Simulado
                    'id_sesion_caja' => $request->id_sesion_caja,
                    'fecha' => now(),
                    'subtotal' => $subtotalSubVenta,
                    'descuento' => 0.00,
                    'impuestos' => $impuestosSubVenta,
                    'total' => $totalSubVenta,
                    'estado' => 'completada',
                    'tipo_pago' => 'multiple',
                    'venta_multiple_id' => $idVentaMultiple, // Se vincula a la venta múltiple
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Aquí también insertarías los detalles de cada sub-venta usando DB::table('detalle_venta')->insert(...)
                // (Omitido para no hacer el bloque tan largo, pero la lógica es idéntica a VentaController)
            }

            // 3. Actualizar los totales de la venta múltiple con el global calculado
            DB::table('ventas_multiples')->where('id_venta_multiple', $idVentaMultiple)->update([
                'subtotal' => $totalGlobal - $impuestosGlobal,
                'impuestos' => $impuestosGlobal,
                'total' => $totalGlobal,
                'estado' => 'completada',
                'updated_at' => now()
            ]);

            // 4. Registrar el pago global (ej. en la primera sub-venta)
            foreach ($request->pagos as $pago) {
                DB::table('pagos_venta')->insert([
                    'id_venta' => $idSubVenta, // Asociado a la última sub-venta por simplicidad en este ejemplo
                    'id_usuario' => $request->id_usuario,
                    'id_forma_pago' => $pago['id_forma_pago'],
                    'fecha_pago' => now()->toDateString(),
                    'monto' => $pago['monto'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ventas múltiples procesadas correctamente.',
                'data' => [
                    'id_venta_multiple' => $idVentaMultiple,
                    'folio' => $folioVM,
                    'total' => $totalGlobal
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}