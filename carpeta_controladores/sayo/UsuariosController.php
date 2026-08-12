<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosController extends Controller
{
    /**
     * Consultar todos los usuarios activos y su rol principal.
     */
    public function index()
    {
        $usuarios = DB::select('
            SELECT u.id_usuario, u.id_empleado, u.usuario, u.correo, u.ultimo_acceso, u.bloqueado, u.activo,
                   (SELECT r.nombre FROM roles r INNER JOIN usuario_rol ur ON r.id_rol = ur.id_rol WHERE ur.id_usuario = u.id_usuario LIMIT 1) as rol_nombre,
                   (SELECT ur.id_rol FROM usuario_rol ur WHERE ur.id_usuario = u.id_usuario LIMIT 1) as id_rol
            FROM usuarios u
            WHERE u.deleted_at IS NULL
            ORDER BY u.id_usuario DESC
        ');

        return response()->json($usuarios, 200);
    }

    /**
     * Guardar un nuevo usuario y asignarle su rol.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_empleado' => 'required|integer',
            'usuario'     => 'required|string|max:50',
            'password'    => 'required|string|min:6',
            'correo'      => 'nullable|email|max:150',
            'id_rol'      => 'required|integer',
            'activo'      => 'nullable|boolean'
        ]);

        $activo = $request->has('activo') ? $request->activo : true;

        DB::beginTransaction();
        try {
            DB::insert('
                INSERT INTO usuarios (id_empleado, usuario, password, correo, bloqueado, activo, created_at, updated_at)
                VALUES (?, ?, ?, ?, false, ?, NOW(), NOW())
            ', [
                $request->id_empleado,
                $request->usuario,
                Hash::make($request->password), // Encriptación obligatoria
                $request->correo,
                $activo
            ]);

            $id_usuario = DB::getPdo()->lastInsertId();

            // Insertamos la relación en la tabla pivote
            DB::insert('
                INSERT INTO usuario_rol (id_usuario, id_rol, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ', [$id_usuario, $request->id_rol]);

            DB::commit();
            return response()->json(['mensaje' => 'Usuario registrado con éxito.'], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'No se pudo registrar el usuario. Es posible que el username o correo ya existan.'], 500);
        }
    }

    /**
     * Mostrar un usuario específico.
     */
    public function show($id_usuario)
    {
        $usuario = DB::select('
            SELECT u.id_usuario, u.id_empleado, u.usuario, u.correo, u.ultimo_acceso, u.bloqueado, u.activo,
                   (SELECT ur.id_rol FROM usuario_rol ur WHERE ur.id_usuario = u.id_usuario LIMIT 1) as id_rol
            FROM usuarios u
            WHERE u.id_usuario = ? AND u.deleted_at IS NULL
            LIMIT 1
        ', [$id_usuario]);

        if (empty($usuario)) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        return response()->json($usuario[0], 200);
    }

    /**
     * Actualizar los datos de un usuario.
     */
    public function update(Request $request, $id_usuario)
    {
        $request->validate([
            'id_empleado' => 'required|integer',
            'usuario'     => 'required|string|max:50',
            'correo'      => 'nullable|email|max:150',
            'id_rol'      => 'required|integer',
            'activo'      => 'required|boolean'
        ]);

        $existe = DB::select('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND deleted_at IS NULL', [$id_usuario]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        DB::beginTransaction();
        try {
            // Si el campo de contraseña viene con datos, la actualizamos y encriptamos
            if ($request->filled('password')) {
                DB::update('
                    UPDATE usuarios SET id_empleado = ?, usuario = ?, password = ?, correo = ?, activo = ?, updated_at = NOW() 
                    WHERE id_usuario = ?
                ', [
                    $request->id_empleado, $request->usuario, Hash::make($request->password), 
                    $request->correo, $request->activo, $id_usuario
                ]);
            } else {
                // Si la contraseña viene vacía, no la tocamos
                DB::update('
                    UPDATE usuarios SET id_empleado = ?, usuario = ?, correo = ?, activo = ?, updated_at = NOW() 
                    WHERE id_usuario = ?
                ', [
                    $request->id_empleado, $request->usuario, $request->correo, 
                    $request->activo, $id_usuario
                ]);
            }

            // Actualizamos la tabla pivote de roles (borrar viejo, insertar nuevo)
            DB::delete('DELETE FROM usuario_rol WHERE id_usuario = ?', [$id_usuario]);
            DB::insert('INSERT INTO usuario_rol (id_usuario, id_rol, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [$id_usuario, $request->id_rol]);

            DB::commit();
            return response()->json(['mensaje' => "Usuario actualizado con éxito."], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'No se pudo actualizar el usuario.'], 500);
        }
    }

    /**
     * Eliminar un usuario (Borrado Lógico y bloqueo de seguridad).
     */
    public function destroy($id_usuario)
    {
        $existe = DB::select('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND deleted_at IS NULL', [$id_usuario]);
        
        if (empty($existe)) {
            return response()->json(['error' => 'Usuario no encontrado o ya eliminado.'], 404);
        }

        // Al eliminar, lo marcamos borrado, inactivo y bloqueado por seguridad
        DB::update('
            UPDATE usuarios 
            SET deleted_at = NOW(), activo = false, bloqueado = true 
            WHERE id_usuario = ?
        ', [$id_usuario]);

        return response()->json(['mensaje' => "Usuario eliminado lógicamente."], 200);
    }
}