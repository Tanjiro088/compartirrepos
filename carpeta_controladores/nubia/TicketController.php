<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketController extends Controller
{
        public function generar($id_venta): JsonResponse
    {
        try {
            $venta = DB::table('ventas')->where('id_venta', $id_venta)->first();
            if (!$venta) return response()->json(['success' => false, 'message' => 'Venta no encontrada'], 404);

            $detalles = DB::table('detalle_venta')->where('id_venta', $id_venta)->get();
            $pagos = DB::table('pagos_venta')->where('id_venta', $id_venta)->get();

            // Cajero y Caja
            $cajero = DB::table('usuarios')->where('id_usuario', $venta->id_usuario)->value('nombre') ?? 'Cajero';
            $caja = DB::table('cajas')->where('id_caja', $venta->id_caja)->value('nombre') ?? 'Caja';

            // Cliente
            $cliente = 'Público General';
            if ($venta->id_cliente && $venta->id_cliente != 1) {
                $cliente = DB::table('clientes')->where('id_cliente', $venta->id_cliente)->value('nombre_comercial') ?? 'Cliente';
            }

            // Productos
            $productosArray = [];
            foreach ($detalles as $d) {
                $prod = DB::table('productos')
                    ->join('presentaciones_producto', 'productos.id_producto', '=', 'presentaciones_producto.id_producto')
                    ->where('presentaciones_producto.id_presentacion', $d->id_presentacion)
                    ->select('productos.nombre', 'presentaciones_producto.nombre as presentacion')
                    ->first();

                $productosArray[] = [
                    'nombre' => ($prod ? $prod->nombre . ' ' . $prod->presentacion : 'Articulo ID: ' . $d->id_presentacion),
                    'cantidad' => $d->cantidad,
                    'precio' => $d->precio_unitario,
                    'total' => $d->subtotal
                ];
            }

            // Pagos
            $pagosArray = [];
            foreach ($pagos as $p) {
                $forma = DB::table('formas_pago')->where('id_forma_pago', $p->id_forma_pago)->value('nombre');
                $pagosArray[] = [
                    'forma' => $forma,
                    'monto' => $p->monto,
                    'referencia' => $p->referencia // Folio de transferencia o 4 dígitos de tarjeta
                ];
            }

            $ticketData = [
                'empresa' => 'ANDINA STORE',
                'direccion' => 'Av. Principal 123, Lima',
                'ruc' => '20601234567',
                'folio' => $venta->folio,
                'fecha' => $venta->fecha,
                'cajero' => $cajero,
                'caja' => $caja,
                'cliente' => $cliente,
                'productos' => $productosArray,
                'totales' => [
                    'subtotal' => $venta->subtotal,
                    'impuestos' => $venta->impuestos,
                    'total' => $venta->total,
                ],
                'pagos' => $pagosArray,
                'cambio' => $venta->cambio
            ];

            return response()->json(['success' => true, 'ticket' => $ticketData]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}