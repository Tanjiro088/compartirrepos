<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaPendienteController extends Controller
{
    // Guardar venta pendiente
    public function guardar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_usuario' => 'required|integer|exists:usuarios,id_usuario',
            'id_sesion_caja' => 'required|integer|exists:sesiones_caja,id_sesion_caja',
            'id_cliente' => 'nullable|integer',
            'datos' => 'required|array',
            'datos.productos' => 'required|array',
            'datos.subtotal' => 'required|numeric',
            'datos.total' => 'required|numeric'
        ]);

        try {
            $id = DB::table('ventas_pendientes')->insertGetId([
                'id_usuario' => $data['id_usuario'],
                'id_sesion_caja' => $data['id_sesion_caja'],
                'id_cliente' => $data['id_cliente'] ?? 1, // Público general por defecto
                'fecha_creacion' => now(),
                'fecha_expiracion' => now()->addHours(24), // Regla: Expira en 24h
                'datos' => json_encode($data['datos']),
                'estado' => 'activa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta guardada como pendiente',
                'data' => ['id_venta_pendiente' => $id]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    // Listar ventas pendientes de la sesión actual
    public function listar($idSesionCaja): JsonResponse
    {
        // Regla: Solo traer las que no han expirado y están activas
        $pendientes = DB::table('ventas_pendientes')
            ->where('id_sesion_caja', $idSesionCaja)
            ->where('estado', 'activa')
            ->where('fecha_expiracion', '>', now())
            ->orderBy('fecha_creacion', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $pendientes]);
    }

    // Recuperar venta pendiente
    public function recuperar($id): JsonResponse
    {
        $pendiente = DB::table('ventas_pendientes')->where('id_venta_pendiente', $id)->first();

        if (!$pendiente) {
            return response()->json(['success' => false, 'message' => 'Venta pendiente no encontrada'], 404);
        }

        if ($pendiente->fecha_expiracion < now()) {
            DB::table('ventas_pendientes')->where('id_venta_pendiente', $id)->update(['estado' => 'expirada']);
            return response()->json(['success' => false, 'message' => 'Esta venta pendiente ya expiró'], 410);
        }

        DB::table('ventas_pendientes')->where('id_venta_pendiente', $id)->update([
            'estado' => 'recuperada',
            'updated_at' => now()
        ]);

        $pendiente->datos = json_decode($pendiente->datos, true);

        return response()->json(['success' => true, 'data' => $pendiente]);
    }
    
    // Eliminar (si se decide no usarla)
    public function eliminar($id): JsonResponse
    {
        DB::table('ventas_pendientes')->where('id_venta_pendiente', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Venta pendiente eliminada']);
    }
}