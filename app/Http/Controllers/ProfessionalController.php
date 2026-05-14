<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionalController extends Controller {

    public function index(Request $request){
        try {
            // union de tablas para sacar todos los datos
            $query= DB::table('Professional')
            ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
            ->join('Users', 'App_users.user_id', '=', 'Users.id')
            // columnas exactas a enviar
            ->select(
                'Professional.id as professional_id', 
                    'Users.name', 
                    'Users.surname', 
                    'Users.email',
                    'App_users.city',
                    'App_users.street_number',
                    'App_users.postal_code'
            );

            // revisa si envian un id de profession para filtrar mediante ese id
            if ($request->has('profession_id')){
                $query->join('Professional_profession', 'Professional.id', '=', 'Professional_profession.professional_id')
                ->where('Professional_profession.profession_id', '=', $request->profession_id);
            }

            $professionals = $query->get();

            return response()->json([
                'status'=>'success',
                'data'=>$professionals
            ],200);
        
        } catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>'obtener los profesionales: '.$e->getMessage()
            ],500);
        }
    }

    // mostrar profesionales por id
    public function show($id){
        try {
            $professional = DB::table('Professional')
            ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
            ->join('Users', 'App_users.user_id', '=', 'Users.id')
            ->select (
                'Professional.id as professional_id', 
                    'Users.name', 
                    'Users.surname', 
                    'Users.email',
                    'Users.mobile',
                    'App_users.city',
                    'App_users.street_number',
                    'App_users.postal_code'
            )
            ->where('Professional.id', '=', $id)
            ->first();

            if (!$professional){
                return response()->json([
                    'status'=>'error',
                    'message'=>'profesional no encontrado'
                ],404);
            }

            return response()->json([
                'status'=>'succes',
                'data'=>$professional
            ],200);
        } catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el profesional: ' . $e->getMessage()
            ],500);
        }
    }

    public function update(Request $request, $id)
    {
        // Validamos los datos
        $request->validate([
            'name' => 'sometimes|required|string',
            'surname' => 'sometimes|required|string',
            'mobile' => 'nullable|string',
            'street_number' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'postal_code' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'professions' => 'sometimes|array|min:1|max:5' // Los oficios nuevos
        ]);

        try {
            DB::beginTransaction();

            // Buscamos al profesional por su ID
            $professional = DB::table('Professional')->where('id', $id)->first();

            if (!$professional) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Profesional no encontrado.'
                ], 404);
            }

            // Buscamos sus perfiles padre para saber qué IDs editar
            $appUser = DB::table('App_users')->where('id', $professional->app_user_id)->first();

            // Actualizamos la tabla principal (Users)
            DB::table('Users')->where('id', $appUser->user_id)->update([
                'name' => $request->name,
                'surname' => $request->surname,
                'mobile' => $request->mobile,
            ]);

            // Actualizamos la dirección en App_users
            DB::table('App_users')->where('id', $appUser->id)->update([
                'street_number' => $request->street_number,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
            ]);

            // actualizar los oficios
            if ($request->has('professions')) {
                // Borramos todos sus oficios antiguos
                DB::table('Professional_profession')->where('professional_id', $professional->id)->delete();

                // Insertamos los oficios nuevos
                foreach ($request->professions as $professionId) {
                    DB::table('Professional_profession')->insert([
                        'professional_id' => $professional->id,
                        'profession_id' => $professionId
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Perfil de profesional actualizado correctamente.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el profesional: ' . $e->getMessage()
            ], 500);
        }
    }
}