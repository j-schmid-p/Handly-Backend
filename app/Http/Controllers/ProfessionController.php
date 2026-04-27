<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionController extends Controller
{
    public function index(){
        try {
            $professions = DB::table('Professions')->get();

            return response()->json([
                'status'=>'success',
                'data'=>'$professions'
            ],200);
        } catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>'error al obtener los oficios: '.$e->getMessage()
            ],500);
        }
    }

    // PARA ADMIN: Crear una nueva profesión
    public function store(Request $request) {
        try {
            // Validamos que nos manden el nombre
            $request->validate([
                'name' => 'required|string|max:100|unique:Professions,name'
            ]);

            $professionId = DB::table('Professions')->insertGetId([
                'name' => $request->name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Profesión creada correctamente.',
                'profession_id' => $professionId
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la profesión: ' . $e->getMessage()
            ], 500);
        }
    }
}