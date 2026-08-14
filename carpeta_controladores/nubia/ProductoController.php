<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductoController extends Controller
{
        public function index(): JsonResponse
    {
        try {
            $productos = DB::table('productos')
                ->leftJoin('presentaciones_producto', 'productos.id_producto', '=', 'presentaciones_producto.id_producto')
                ->leftJoin('codigos_barras', 'presentaciones_producto.id_presentacion', '=', 'codigos_barras.id_presentacion')
                ->leftJoin('categorias', 'productos.id_categoria', '=', 'categorias.id_categoria')
                ->leftJoin('marcas', 'productos.id_marca', '=', 'marcas.id_marca')
                ->leftJoin('imagenes_producto', function($join){
                    $join->on('presentaciones_producto.id_presentacion', '=', 'imagenes_producto.id_presentacion')
                         ->where('imagenes_producto.principal', true);
                })
                ->leftJoin('inventarios', 'presentaciones_producto.id_presentacion', '=', 'inventarios.id_presentacion')
                ->select(
                    'presentaciones_producto.id_presentacion',
                    'productos.nombre',
                    'productos.descripcion',
                    'productos.codigo_interno as codigo_producto',
                    'productos.precio_venta as precio',
                    DB::raw('IFNULL(categorias.nombre, "Sin categoría") as categoria'),
                    DB::raw('IFNULL(marcas.nombre, "Sin marca") as marca'),
                    'codigos_barras.codigo as codigo_barras',
                    'inventarios.cantidad_actual as stock',
                    'imagenes_producto.url as imagen'
                )
                ->take(100)
                ->get();

            return response()->json(['success' => true, 'data' => $productos]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    // public function buscarPorCodigo($codigo): JsonResponse
    // {
    //     try {
    //         $colsPresentaciones = Schema::getColumnListing('presentaciones_producto');
    //         $colsProductos = Schema::getColumnListing('productos');
    //         $colsInventarios = Schema::getColumnListing('inventarios');

    //         $colDescProducto = in_array('descripcion', $colsProductos) ? 'productos.descripcion' : (in_array('description', $colsProductos) ? 'productos.description' : DB::raw('NULL'));
    //         $colPrecio = in_array('precio_venta', $colsPresentaciones) ? 'presentaciones_producto.precio_venta' : (in_array('precio_venta', $colsProductos) ? 'productos.precio_venta' : DB::raw('NULL'));
    //         $colStock = in_array('cantidad_actual', $colsInventarios) ? 'inventarios.cantidad_actual' : (in_array('cantidactual', $colsInventarios) ? 'inventarios.cantidactual' : DB::raw('NULL'));

    //         $producto = DB::table('codigos_barras')
    //             ->join('presentaciones_producto', 'codigos_barras.id_presentacion', '=', 'presentaciones_producto.id_presentacion')
    //             ->join('productos', 'presentaciones_producto.id_producto', '=', 'productos.id_producto')
    //             ->leftJoin('categorias', 'productos.id_categoria', '=', 'categorias.id_categoria')
    //             ->leftJoin('marcas', 'productos.id_marca', '=', 'marcas.id_marca')
    //             ->leftJoin('imagenes_producto', function($join){
    //                 $join->on('presentaciones_producto.id_presentacion', '=', 'imagenes_producto.id_presentacion')
    //                      ->where('imagenes_producto.principal', true);
    //             })
    //             ->leftJoin('inventarios', 'presentaciones_producto.id_presentacion', '=', 'inventarios.id_presentacion')
    //             ->select(
    //                 'presentaciones_producto.id_presentacion',
    //                 'productos.nombre',
    //                 $colDescProducto . ' as descripcion',
    //                 $colPrecio . ' as precio',
    //                 'categorias.nombre as categoria',
    //                 'marcas.nombre as marca',
    //                 'codigos_barras.codigo as codigo_barras',
    //                 $colStock . ' as stock',
    //                 'imagenes_producto.url as imagen'
    //             )
    //             ->where('codigos_barras.codigo', $codigo)
    //             ->first();

    //         if (!$producto) {
    //             return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
    //         }

    //         return response()->json(['success' => true, 'data' => $producto]);

    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    //     }
    // }
}