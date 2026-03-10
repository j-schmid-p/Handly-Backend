<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // hablar con bd
use Illuminate\Support\Facades\Hash; // encriptación
use Illuminate\Validation\Rules\Password; // reglas contraseña
use App\Models\User; //modelo User

class AuthController extends Controller
{
    public function registerClient (Request $request){
        $request->validate([
            'name' => 'required|string',
            'surname' => 'required|string',
            'dni' => 'required|string|unique:Users,dni', //comprobación de dni en la bd
            'email' => 'required|string|unique:Users,email',//comprobación de email en la bd
            'password' => ['required', Password::min(6)-> mixedCase()->symbols()],
            'birthdate' => 'required|date',
            'street_number' => 'required|string',
            'city' => 'required|string',
            'postal_code' => "required|string",
            'country' => 'required|string'
        ]);

        try {
            DB::beginTransaction();
             //insertar en Users

            $userId = DB::table('Users')->insertGetId([
                'rol_id' => 1, //Cliente
                'name' => $request->name,
                'surname'=> $request->surname,
                'dni'=> $request ->dni,
                'email' => $request->email,
                'mobile' => $request->mobile ?? '', //vacio si no manda
                'birthdate' => $request->birthdate,
                'password' => Hash::make($request->password),
            ]);

            //insertar en App_users
            $appUserId = DB::table('App_users')->insertGetId([
                'user_id' => $userId,
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'last_connection' => now(), //fecha actual automática
                'account_state_id' => 1, // 1 = active
                'account_creation_date' => now(),
            ]);

            //insertar en Client 
            DB::table('Client')->Insert([
                'app_user_id'=> $appUserId
            ]);
            
            // esto solo si lo demas se hizo bien
            DB::commit();

            return response()->json ([
                'status'=> 'succes',
                'message'=>'Cliente registrado correctamente'
            ],201);

            // si no rollback
        } catch(\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'=>'error',
                'message'=>'Hubo un error al registrarse: ', $e->getMessage()
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
            'birthdate' => 'required|date',
            'street_number' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'country' => 'required|string',
            'professions' => 'required|array|min:1' // lista de oficios en formato array 
        ]);

        try {
            DB::beginTransaction();
             
            //insertar en Users
            $userId = DB::table('Users')->insertGetId([
                'rol_id' => 2, //Professional
                'name' => $request->name,
                'surname'=> $request->surname,
                'dni'=> $request ->dni,
                'email' => $request->email,
                'mobile' => $request->mobile ?? '', //vacio si no manda
                'birthdate' => $request->birthdate,
                'password' => Hash::make($request->password),
            ]);

            //insertar en App_users
            $appUserId = DB::table('App_users')->insertGetId([
                'user_id' => $userId,
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
                'last_connection' => now(), //fecha actual automática
                'account_state_id' => 1, // 1 = active
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

            DB::commit();

            return response()->json([
                'status'=>'succes',
                'message'=>'professional added'
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
        ])
    }
}
