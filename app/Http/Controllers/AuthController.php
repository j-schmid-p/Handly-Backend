<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // hablar con bd
use Illuminate\Support\Facades\Hash; // encriptación
use Illuminate\Validation\Rules\Password; // reglas contraseña
use App\Models\User; //modelo User
use Illuminate\Support\Facades\Mail; // para verificacion de mail

class AuthController extends Controller
{
    public function registerClient (Request $request){
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'dni' => 'required|string|unique:Users,dni', //comprobación de dni en la bd
            'email' => 'required|string|unique:Users,email',//comprobación de email en la bd
            'password' => ['required', Password::min(6)-> mixedCase()->symbols()],
            'street_number' => 'required|string',
            'city' => 'required|string',
            'postal_code' => "required|string",
            'country' => 'required|string'
        ]);

        try {
            DB::beginTransaction();
 
            // generar codigo
            $codigoVerificacion = (string) rand(100000, 999999);

             //insertar en Users
            $userId = DB::table('Users')->insertGetId([
                'rol_id' => 1, //Cliente
                'name' => $request->name,
                'surname'=> $request->surname,
                'dni'=> $request ->dni,
                'email' => $request->email,
                'mobile' => null, 
                'birthdate' => null,
                'password' => Hash::make($request->password),
                'verification_code' => $codigoVerificacion
            ]);

            //insertar en App_users
            $appUserId = DB::table('App_users')->insertGetId([
                'user_id' => $userId,
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'last_connection' => now(), //fecha actual automática
                'account_state_id' => 3, // 1 = active
                'account_creation_date' => now(),
            ]);

            //insertar en Client 
            DB::table('Client')->insert([
                'app_user_id'=> $appUserId
            ]);

            Mail::raw("¡Hola " . $request->name . "! Bienvenido a Handly.\n\nTu código de verificación de 6 dígitos es: " . $codigoVerificacion . "\n\nPor favor, introdúcelo en la aplicación para activar tu cuenta.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Código de Verificación - Handly');
            });
            
            // esto solo si lo demas se hizo bien
            DB::commit();

            // enviar codigo
            return response()->json ([
                'status'=> 'success',
                'message'=>'Cliente registrado correctamente.',
                'codigo_secreto' => $codigoVerificacion
            ],201);

            // si no rollback
        } catch(\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'=>'error',
                'message'=>'Hubo un error al registrarse: '. $e->getMessage()
            ], 500);

        }
    } 

    public function registerProfessional(Request $request){
        $request->validate ([
            'name' => 'required|string',
            'surname' => 'required|string',
            'dni' => 'required|string|unique:Users,dni',
            'email' => 'required|email|unique:Users,email',
            'password' => ['required', Password::min(6)->mixedCase()->symbols()],
            'street_number' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'country' => 'required|string',
            'mobile' => 'nullable|string', // <-- Nulleable significa opcional
            'birthdate' => 'nullable|date',
            'professions' => 'required|array|min:1|max:5' // lista de oficios en formato array 
        ]);

        try {
            DB::beginTransaction();

            // codigo para verificacion
            $codigoVerificacion = (string) rand(100000, 999999);
             
            //insertar en Users
            $userId = DB::table('Users')->insertGetId([
                'rol_id' => 2, //Professional
                'name' => $request->name,
                'surname'=> $request->surname,
                'dni'=> $request ->dni,
                'email' => $request->email,
                'mobile' => $request->mobile,       // <-- Si no lo mandan, Laravel pondrá null
                'birthdate' => $request->birthdate,
                'password' => Hash::make($request->password),
                'verification_code' => $codigoVerificacion
            ]);

            //insertar en App_users
            $appUserId = DB::table('App_users')->insertGetId([
                'user_id' => $userId,
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'last_connection' => now(), //fecha actual automática
                'account_state_id' => 3, // 3 = pending de default
                'account_creation_date' => now(),
            ]);

            //insertar en Professional
            $professionalId = DB::table('Professional')->insertGetId([
                'app_user_id'=> $appUserId
            ]);

            //guardar en tabla intermediaria Professional_professions
            foreach ($request-> professions as $professionId){
                DB::table('Professional_profession')->insert([
                    'professional_id'=>$professionalId,
                    'profession_id'=>$professionId
                ]);
            }

            Mail::raw("¡Hola " . $request->name . "! Bienvenido a Handly.\n\nTu código de verificación de 6 dígitos es: " . $codigoVerificacion . "\n\nPor favor, introdúcelo en la aplicación para activar tu perfil profesional.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Activa tu cuenta profesional - Handly');
            });

            DB::commit();

            return response()->json([
                'status'=>'success',
                'message'=>'professional added',
                'codigo_secreto' => $codigoVerificacion
            ],201);

        } catch (\Exception $e){
            DB::rollback();
            return response()->json([
                'status'=>'error',
                'message'=>'Hubo un error al registrarse: ', $e->getMessage()
            ], 500);
        }

    }

    public function login(Request $request){
        //exigimos correo y contraseña
        $request->validate([
            'email'=>'required|email',
            'password'=>'required'
        ]);

        //buscamos en bd por email
        $user = User::where('email', $request->email)->first();

        //comprobamos que existe
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'=>'error',
                'message'=>'Email o contraseña incoprrectos'
            ],401); // error: no autorizado
        }

        // estados
        $appUser = DB::table('App_users')->where('user_id', $user->id)->first();

        if ($appUser) {
            $estado = $appUser->account_state_id;

            // 3: pending aprobation | 4: in revision
            if ($estado == 3 || $estado == 4) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Tu cuenta está siendo revisada por un administrador. Vuelve a intentarlo más tarde.'
                ], 403);
            }

            // 2: banned
            if ($estado == 2) {
                return response()->json([
                    'status' => 'banned',
                    'message' => 'Esta cuenta ha sido suspendida.'
                ], 403);
            }

            // 5: inactive | 6: deleted
            if ($estado == 5 || $estado == 6) {
                return response()->json([
                    'status' => 'inactive',
                    'message' => 'Esta cuenta ya no está disponible.'
                ], 403);
            }
        }
         
        // si todo esta bien le creamos su token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'=>'succes',
            'message'=>'login correcto',
            'token'=> $token,
            'user'=> $user
        ]);
    }

    public function logout (Request $request){
        // borramos el token del user 
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'=>'success',
            'message'=>'sesion cerrada'
        ]);
    }

    public function deleteUser($id) {
        try {
            DB::beginTransaction();

            // 1. buscar si esta  en App_users
            $appUser = DB::table('App_users')->where('user_id', $id)->first();

            if ($appUser) {
                // si es profesional borrar sus oficios y su perfil
                $professional = DB::table('Professional')->where('app_user_id', $appUser->id)->first();
                if ($professional) {
                    DB::table('Professional_profession')->where('professional_id', $professional->id)->delete();
                    DB::table('Professional')->where('id', $professional->id)->delete();
                }

                // si por error se guardó como cliente, borrar su perfil
                DB::table('Client')->where('app_user_id', $appUser->id)->delete();

                // borrar de App_users
                DB::table('App_users')->where('id', $appUser->id)->delete();
            }

            // 5. borrar los tokens de acceso que tuviera generados
            DB::table('personal_access_tokens')->where('tokenable_id', $id)->delete();

            // 6. borrar el Usuario
            $deleted = DB::table('Users')->where('id', $id)->delete();

            if (!$deleted) {
                throw new \Exception("El usuario con ID $id no existe.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario borrado de la faz de la tierra de forma segura.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Hubo un error al borrar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyEmail(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string'
        ]);

        // Buscamos al usuario por su email
        $user = DB::table('Users')->where('email', $request->email)->first();

        // Si no existe o el código no coincide...
        if (!$user || $user->verification_code !== $request->code) {
            return response()->json([
                'status' => 'error', 
                'message' => 'El código es incorrecto o el usuario no existe.'
            ], 400);
        }

        // Si es correcto: Borramos el código para que no se pueda reusar
        DB::table('Users')->where('id', $user->id)->update([
            'verification_code' => null
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Cuenta verificada correctamente.'
        ]);
    }
}
