<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        try {
            // Sacamos a todos los usuarios, pero sin la contraseña por seguridad
            $users = DB::table('Users')
                ->select('id', 'rol_id', 'name', 'surname', 'email', 'mobile', 'dni')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la lista de usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProfile(Request $request)
    {
        $user = $request->user(); // obtenemos user

        $role = DB::table('Roles')->where('id', $user->rol_id)->first(); // buscamos el nombre del rol que tiene

        $user->role_name = $role ? $role->name : 'DESCONOCIDO';

        if (isset($user->birthdate)) {
            $user->birthdate = Carbon::parse($user->birthdate)->format('d/m/Y'); // formateo de fecha
        }

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    // muestra user dependiendo de su rol
    public function show($id)
    {
        try {
            // busca al usuario en la tabla principal
            $user = DB::table('Users')->where('id', $id)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            // busca sus datos comunes en App_users
            $appUser = DB::table('App_users')->where('user_id', $user->id)->first();

            // respuesta JSON (lo que devuelve)
            $userData = [
                'id' => $user->id,
                'rol_id' => $user->rol_id,
                'name' => $user->name,
                'surname' => $user->surname,
                'dni' => $user->dni,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'birthdate' => $user->birthdate,
            ];

            // si tiene datos de dirección (App_users), los añadimos 
            if ($appUser) {
                $userData['address'] = [
                    'street_number' => $appUser->street_number,
                    'city' => $appUser->city,
                    'postal_code' => $appUser->postal_code,
                    'country' => $appUser->country,
                ];
                $userData['account_state_id'] = $appUser->account_state_id;

                // dependiendo del rol, buscamos en tablas diferentes
                if ($user->rol_id == 1) {
                    // CLIENTE
                    $client = DB::table('Client')->where('app_user_id', $appUser->id)->first();
                    $userData['profile_type'] = 'Cliente';
                    // (mas columnas especificas si las encontramos)

                } elseif ($user->rol_id == 2) {
                    // PROFESIONAL
                    $professional = DB::table('Professional')->where('app_user_id', $appUser->id)->first();
                    $userData['profile_type'] = 'Profesional';

                    // vamos a buscar sus oficios
                    if ($professional) {
                        $professions = DB::table('Professional_profession')
                            ->where('professional_id', $professional->id)
                            ->pluck('profession_id'); // Pluck nos saca solo los IDs en un array [1, 3]

                        $userData['professions'] = $professions;
                    }
                }
            }

            // devolvemos el paquete completo
            return response()->json([
                'status' => 'success',
                'data' => $userData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hubo un error al obtener el usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateClient(Request $request, $id)
    {
        // vaalidamos datos que recibimos
        $request->validate([
            'name' => 'sometimes|required|string',
            'surname' => 'sometimes|required|string',
            'mobile' => 'nullable|string',
            'street_number' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'postal_code' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
        ]);

        try {
            DB::beginTransaction();

            // actualizamos la tabla principal (Users)
            DB::table('Users')->where('id', $id)->update([
                'name' => $request->name,
                'surname' => $request->surname,
                'mobile' => $request->mobile,
            ]);

            // actualizamos la dirección en App_users
            DB::table('App_users')->where('user_id', $id)->update([
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Perfil de cliente actualizado correctamente.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    public function changeState(Request $request, $id)
    {
        // Validamos que nos manden un estado valido
        $request->validate([
            'account_state_id' => 'required|integer'
        ]);

        try {
            // account_state_id esta  en App_users entonces se busca por user_id
            $updated = DB::table('App_users')->where('user_id', $id)->update([
                'account_state_id' => $request->account_state_id
            ]);

            if (!$updated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Perfil de usuario no encontrado o el estado ya era ese.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de la cuenta actualizado correctamente.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar el estado de la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadDocuments(Request $request) {
        // 1. Validamos que lleguen las 3 imágenes y el email del usuario
        $request->validate([
            'email' => 'required|email|exists:Users,email', // Valida que el email exista en la BD
            'selfie' => 'required|file|image|max:5120', // Máximo 5MB por foto
            'document_front' => 'required|file|image|max:5120',
            'document_back' => 'required|file|image|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // 2. Buscamos al usuario por su email
            $user = DB::table('Users')->where('email', $request->email)->first();

            // Convertimos las fotos a texto Base64
            $selfieBase64 = 'data:' . $request->file('selfie')->getMimeType() . ';base64,' . base64_encode(file_get_contents($request->file('selfie')->getRealPath()));
            $docFrontBase64 = 'data:' . $request->file('document_front')->getMimeType() . ';base64,' . base64_encode(file_get_contents($request->file('document_front')->getRealPath()));
            $docBackBase64 = 'data:' . $request->file('document_back')->getMimeType() . ';base64,' . base64_encode(file_get_contents($request->file('document_back')->getRealPath()));

            // 4. Hacemos el UPDATE en la tabla App_users usando el ID del usuario
            DB::table('App_users')->where('user_id', $user->id)->update([
                'selfie' => $selfieBase64,
                'document_front' => $docFrontBase64,
                'document_back' => $docBackBase64,
                'account_state_id' => 4 // Pasamos la cuenta a estado "in revision"
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Documentos recibidos, guardados en BD y cuenta en revisión.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar los documentos: ' . $e->getMessage()
            ], 500);
        }
    }


}
