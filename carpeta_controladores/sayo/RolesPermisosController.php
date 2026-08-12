<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolesPermisosController extends Controller
{
    /**
     * Consultar todos los roles.
     */
    public function index()
    {
        $roles = DB::select('
            SELECT * FROM roles 
            WHERE deleted_at IS NULL 
            ORDER BY id_rol DESC
        ');

        return response()->json($roles, 200);
    }

    /**
     * Guardar un nuevo rol y su matriz de permisos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:255',
            'permisos'    => 'array' // Array de objetos con 'modulo' y 'accion'
        ]);

        DB::beginTransaction();
        try {
            // 1. Insertamos el Rol
            DB::insert('
                INSERT INTO roles (nombre, descripcion, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
            ', [$request->nombre, $request->descripcion]);

            $id_rol = DB::getPdo()->lastInsertId();

            // 2. Procesamos y vinculamos cada permiso marcado en la matriz
            if ($request->has('permisos')) {
                foreach ($request->permisos as $p) {
                    // Verificamos si el permiso ya existe en el catálogo general
                    $permiso = DB::select('SELECT id_permiso FROM permisos WHERE modulo = ? AND accion = ?', [$p['modulo'], $p['accion']]);
                    
                    if (empty($permiso)) {
                        // Si no existe, lo creamos dinámicamente
                        DB::insert('INSERT INTO permisos (modulo, accion, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$p['modulo'], $p['accion']]);
                        $id_permiso = DB::getPdo()->lastInsertId();
                    } else {
                        $id_permiso = $permiso[0]->id_permiso;
                    }

                    // Vinculamos en la tabla muchos a muchos
                    DB::insert('
                        INSERT INTO rol_permiso (id_rol, id_permiso, created_at, updated_at) 
                        VALUES (?, ?, NOW(), NOW())
                    ', [$id_rol, $id_permiso]);
                }
            }

            DB::commit();
            return response()->json(['mensaje' => 'Rol y permisos registrados con éxito.'], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'No se pudo registrar el rol.'], 500);
        }
    }

    /**
     * Mostrar un rol específico con todos sus permisos desglosados.
     */
    public function show($id_rol)
    {
        $rol = DB::select('SELECT * FROM roles WHERE id_rol = ? AND deleted_at IS NULL LIMIT 1', [$id_rol]);

        if (empty($rol)) {
            return response()->json(['error' => 'Rol no encontrado.'], 404);
        }

        // Traemos todos los permisos vinculados a este rol mediante JOIN
        $permisos = DB::select('
            SELECT p.modulo, p.accion 
            FROM permisos p
            INNER JOIN rol_permiso rp ON p.id_permiso = rp.id_permiso
            WHERE rp.id_rol = ?
        ', [$id_rol]);

        $rol[0]->permisos = $permisos;

        return response()->json($rol[0], 200);
    }

    /**
     * Actualizar un rol y su matriz de permisos.
     */
    public function update(Request $request, $id_rol)
    {
        $request->validate([
            'nombre'      => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:255',
            'permisos'    => 'array'
        ]);

        $existe = DB::select('SELECT id_rol FROM roles WHERE id_rol = ? AND deleted_at IS NULL', [$id_rol]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Rol no encontrado.'], 404);
        }

        DB::beginTransaction();
        try {
            // Actualizamos los datos básicos
            DB::update('
                UPDATE roles SET nombre = ?, descripcion = ?, updated_at = NOW() 
                WHERE id_rol = ?
            ', [$request->nombre, $request->descripcion, $id_rol]);

            // Reseteamos los permisos de este rol para insertar los nuevos (Clean Slate)
            DB::delete('DELETE FROM rol_permiso WHERE id_rol = ?', [$id_rol]);

            if ($request->has('permisos')) {
                foreach ($request->permisos as $p) {
                    $permiso = DB::select('SELECT id_permiso FROM permisos WHERE modulo = ? AND accion = ?', [$p['modulo'], $p['accion']]);
                    if (empty($permiso)) {
                        DB::insert('INSERT INTO permisos (modulo, accion, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$p['modulo'], $p['accion']]);
                        $id_permiso = DB::getPdo()->lastInsertId();
                    } else {
                        $id_permiso = $permiso[0]->id_permiso;
                    }
                    DB::insert('INSERT INTO rol_permiso (id_rol, id_permiso, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$id_rol, $id_permiso]);
                }
            }

            DB::commit();
            return response()->json(['mensaje' => "Rol actualizado con éxito."], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'No se pudo actualizar el rol.'], 500);
        }
    }

    /**
     * Eliminar un rol (aplicando la regla de negocio HU-CONF-009).
     */
    public function destroy($id_rol)
    {
        // 1. Validar que el rol exista
        $existe = DB::select('SELECT id_rol FROM roles WHERE id_rol = ? AND deleted_at IS NULL', [$id_rol]);
        if (empty($existe)) {
            return response()->json(['error' => 'Rol no encontrado.'], 404);
        }

        // 2. Regla de Negocio: No eliminar si tiene usuarios
        $usuarios_asignados = DB::select('SELECT id_usuario_rol FROM usuario_rol WHERE id_rol = ? LIMIT 1', [$id_rol]);
        if (!empty($usuarios_asignados)) {
            return response()->json(['error' => 'No puedes eliminar este rol porque hay usuarios asignados a él.'], 400);
        }

        // 3. Borrado Lógico
        DB::update('UPDATE roles SET deleted_at = NOW() WHERE id_rol = ?', [$id_rol]);

        return response()->json(['mensaje' => "Rol eliminado lógicamente."], 200);
    }
}