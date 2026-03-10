<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionalController extends Controller {

    public function index(){
        try {
            // union de tablas para sacar todos los datos
            $professionals = DB::table('Professional')
            ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
            ->join('Users', 'App_users.user_id', '=', 'Users.id')
            // columnas exactas a enviar
            ->select(
                'Professional.id as professional_id', 
                    'Users.name', 
                    'Users.surname', 
                    'Users.email',
                    'App_users.city'
            );

            // revisa si envian un id de profession para filtrar mediante ese id
            if ($request->has('profession_id')){
                $query->join('Professional_profession', 'Professional.id', '=', 'Professional_profession.professional_id')
                ->where('Professional_profession.profession_id', '=', $request->profession_id)
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
                    'App_users.street_number'
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
            ],500)
        }
    }
}