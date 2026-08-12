<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductosController extends Controller
{
    /**
     * Consultar todos los productos con su categoría, marca y su presentación principal.
     */
    public function index()
    {
        $productos = DB::select('
            SELECT p.id_producto, p.codigo_interno, p.nombre, p.precio_venta, p.activo,
                   p.id_categoria, p.id_marca, 
                   c.nombre as categoria_nombre, m.nombre as marca_nombre,
                   (SELECT SUM(stock_actual) FROM presentaciones_producto pp WHERE pp.id_producto = p.id_producto AND pp.deleted_at IS NULL) as stock_total,
                   (SELECT sku FROM presentaciones_producto pp WHERE pp.id_producto = p.id_producto AND pp.deleted_at IS NULL LIMIT 1) as sku_principal,
                   /* Corregido: ip.url en lugar de ip.url_imagen */
                   (SELECT ip.url FROM imagenes_producto ip INNER JOIN presentaciones_producto pp2 ON ip.id_presentacion = pp2.id_presentacion WHERE pp2.id_producto = p.id_producto AND pp2.deleted_at IS NULL LIMIT 1) as imagen_url
            FROM productos p
            INNER JOIN categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN marcas m ON p.id_marca = m.id_marca
            ORDER BY p.id_producto DESC
        ');
        
        return response()->json($productos, 200);
    }

    /**
     * Guardar un nuevo producto y sus presentaciones.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_categoria'   => 'required',
            'codigo_interno' => 'required|string|max:50',
            'nombre'         => 'required|string|max:200',
            'precio_compra'  => 'required|numeric',
            'precio_venta'   => 'required|numeric',
            'presentaciones' => 'required'
        ]);

        DB::beginTransaction();
        try {
            // 1. Insertar el Producto Base con TODOS sus atributos de trazabilidad e impuestos
            DB::insert('
                INSERT INTO productos (
                    id_categoria, id_marca, codigo_interno, nombre, descripcion, 
                    precio_compra, precio_venta, precio_mayoreo, utilidad, 
                    aplica_iva, aplica_ieps, aplica_ish, 
                    requiere_lote, requiere_serie, requiere_caducidad, 
                    activo, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ', [
                $request->id_categoria, $request->id_marca, $request->codigo_interno, $request->nombre, $request->descripcion, 
                $request->precio_compra, $request->precio_venta, $request->precio_mayoreo ?: 0, $request->utilidad ?: 0, 
                $request->aplica_iva ? 1 : 0, $request->aplica_ieps ? 1 : 0, $request->aplica_ish ? 1 : 0,
                $request->requiere_lote ? 1 : 0, $request->requiere_serie ? 1 : 0, $request->requiere_caducidad ? 1 : 0
            ]);

            $id_producto = DB::getPdo()->lastInsertId();
            $presentaciones = is_string($request->presentaciones) ? json_decode($request->presentaciones, true) : $request->presentaciones;

            // 2. Insertar las presentaciones con logística, stock y conversión
            foreach ($presentaciones as $index => $pres) {
                DB::insert('
                    INSERT INTO presentaciones_producto (
                        id_producto, id_unidad_medida, id_tipo_presentacion, nombre, sku, 
                        factor_conversion, stock_minimo, stock_maximo, peso, volumen, 
                        activa, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ', [
                    $id_producto, $pres['id_unidad_medida'], $pres['id_tipo_presentacion'] ?? null, 
                    $pres['nombre'], $pres['sku'], 
                    $pres['factor_conversion'] ?: 1, $pres['stock_minimo'] ?: 0, $pres['stock_maximo'] ?: 0, 
                    $pres['peso'] ?: 0, $pres['volumen'] ?: 0
                ]);

                $id_presentacion = DB::getPdo()->lastInsertId();

                if ($request->hasFile("imagen_variante_{$index}")) {
                    $rutaImagen = $request->file("imagen_variante_{$index}")->store('productos', 'public');
                    DB::insert('INSERT INTO imagenes_producto (id_presentacion, url, principal, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())', [$id_presentacion, $rutaImagen]);
                }
            }

            DB::commit();
            return response()->json(['mensaje' => 'Producto registrado con éxito.'], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar el producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un producto específico con todas sus presentaciones (SKUs).
     */
    public function show($id_producto)
    {
        $producto = DB::select('SELECT * FROM productos WHERE id_producto = ? AND deleted_at IS NULL LIMIT 1', [$id_producto]);

        if (empty($producto)) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        // Corregido: ip.url as url_imagen
        $presentaciones = DB::select('
            SELECT pp.id_presentacion, pp.id_unidad_medida, pp.id_tipo_presentacion, pp.nombre, pp.sku, ip.url as url_imagen 
            FROM presentaciones_producto pp
            LEFT JOIN imagenes_producto ip ON pp.id_presentacion = ip.id_presentacion
            WHERE pp.id_producto = ? AND pp.deleted_at IS NULL
        ', [$id_producto]);

        $producto[0]->presentaciones = $presentaciones;

        return response()->json($producto[0], 200);
    }

    /**
     * Actualizar los datos de un producto y sus presentaciones.
     */
    public function update(Request $request, $id_producto)
    {
        $request->validate([
            'id_categoria'   => 'required',
            'codigo_interno' => 'required|string|max:50',
            'nombre'         => 'required|string|max:200',
            'precio_compra'  => 'required|numeric',
            'precio_venta'   => 'required|numeric',
            'presentaciones' => 'required'
        ]);

        $existe = DB::select('SELECT id_producto FROM productos WHERE id_producto = ? AND deleted_at IS NULL', [$id_producto]);
        if (empty($existe)) return response()->json(['error' => 'Producto no encontrado.'], 404);

        DB::beginTransaction();
        try {
            DB::update('
                UPDATE productos SET 
                    id_categoria = ?, id_marca = ?, codigo_interno = ?, nombre = ?, descripcion = ?, 
                    precio_compra = ?, precio_venta = ?, precio_mayoreo = ?, utilidad = ?, 
                    aplica_iva = ?, aplica_ieps = ?, aplica_ish = ?, 
                    requiere_lote = ?, requiere_serie = ?, requiere_caducidad = ?, 
                    updated_at = NOW()
                WHERE id_producto = ?
            ', [
                $request->id_categoria, $request->id_marca, $request->codigo_interno, $request->nombre, $request->descripcion,
                $request->precio_compra, $request->precio_venta, $request->precio_mayoreo ?: 0, $request->utilidad ?: 0, 
                $request->aplica_iva ? 1 : 0, $request->aplica_ieps ? 1 : 0, $request->aplica_ish ? 1 : 0,
                $request->requiere_lote ? 1 : 0, $request->requiere_serie ? 1 : 0, $request->requiere_caducidad ? 1 : 0,
                $id_producto
            ]);

            $presentaciones = is_string($request->presentaciones) ? json_decode($request->presentaciones, true) : $request->presentaciones;
            $ids_recibidos = [];

            foreach ($presentaciones as $index => $pres) {
                $id_presentacion = $pres['id_presentacion'] ?? null;

                if ($id_presentacion) {
                    DB::update('
                        UPDATE presentaciones_producto SET 
                            id_unidad_medida = ?, id_tipo_presentacion = ?, nombre = ?, sku = ?, 
                            factor_conversion = ?, stock_minimo = ?, stock_maximo = ?, peso = ?, volumen = ?, 
                            updated_at = NOW() 
                        WHERE id_presentacion = ?
                    ', [
                        $pres['id_unidad_medida'], $pres['id_tipo_presentacion'] ?? null, $pres['nombre'], $pres['sku'], 
                        $pres['factor_conversion'] ?: 1, $pres['stock_minimo'] ?: 0, $pres['stock_maximo'] ?: 0, 
                        $pres['peso'] ?: 0, $pres['volumen'] ?: 0, $id_presentacion
                    ]);
                } else {
                    DB::insert('
                        INSERT INTO presentaciones_producto (
                            id_producto, id_unidad_medida, id_tipo_presentacion, nombre, sku, 
                            factor_conversion, stock_minimo, stock_maximo, peso, volumen, activa, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ', [
                        $id_producto, $pres['id_unidad_medida'], $pres['id_tipo_presentacion'] ?? null, $pres['nombre'], $pres['sku'],
                        $pres['factor_conversion'] ?: 1, $pres['stock_minimo'] ?: 0, $pres['stock_maximo'] ?: 0, $pres['peso'] ?: 0, $pres['volumen'] ?: 0
                    ]);
                    $id_presentacion = DB::getPdo()->lastInsertId();
                }
                $ids_recibidos[] = $id_presentacion;

                if ($request->hasFile("imagen_variante_{$index}")) {
                    $rutaImagen = $request->file("imagen_variante_{$index}")->store('productos', 'public');
                    $imgExistente = DB::select('SELECT id_imagen FROM imagenes_producto WHERE id_presentacion = ? LIMIT 1', [$id_presentacion]);
                    if (empty($imgExistente)) {
                        DB::insert('INSERT INTO imagenes_producto (id_presentacion, url, principal, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())', [$id_presentacion, $rutaImagen]);
                    } else {
                        DB::update('UPDATE imagenes_producto SET url = ?, updated_at = NOW() WHERE id_presentacion = ?', [$rutaImagen, $id_presentacion]);
                    }
                }
            }

            if (count($ids_recibidos) > 0) {
                $placeholders = implode(',', array_fill(0, count($ids_recibidos), '?'));
                $params = array_merge([$id_producto], $ids_recibidos);
                DB::update("UPDATE presentaciones_producto SET deleted_at = NOW(), activa = false WHERE id_producto = ? AND id_presentacion NOT IN ($placeholders)", $params);
            }

            DB::commit();
            return response()->json(['mensaje' => 'Producto actualizado con éxito.'], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar el producto.'], 500);
        }
    }

    /**
     * Eliminar un producto (Borrado Lógico en cascada).
     */
    /**
     * Alternar el estado de un producto (Borrado Lógico / Restauración).
     */
    public function destroy($id_producto)
    {
        // 1. Buscamos el estado actual del producto
        $producto = DB::select('SELECT activo FROM productos WHERE id_producto = ? LIMIT 1', [$id_producto]);
        
        if (empty($producto)) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        // 2. Determinamos los nuevos valores a aplicar
        $estadoActual = $producto[0]->activo;
        $nuevoEstado = $estadoActual ? 0 : 1; // Si está en 1 pasa a 0, y viceversa
        $fechaBorrado = $estadoActual ? 'NOW()' : 'NULL'; // Si lo apagamos le pone fecha, si lo prendemos la borra

        // 3. Actualizamos el producto padre
        DB::update("
            UPDATE productos 
            SET deleted_at = $fechaBorrado, activo = ? 
            WHERE id_producto = ?
        ", [$nuevoEstado, $id_producto]);

        // 4. Actualizamos en cascada todas sus presentaciones/SKUs
        DB::update("
            UPDATE presentaciones_producto 
            SET deleted_at = $fechaBorrado, activa = ? 
            WHERE id_producto = ?
        ", [$nuevoEstado, $id_producto]);

        $mensaje = $nuevoEstado ? 'Producto habilitado correctamente.' : 'Producto deshabilitado correctamente.';
        return response()->json(['mensaje' => $mensaje], 200);
    }
}