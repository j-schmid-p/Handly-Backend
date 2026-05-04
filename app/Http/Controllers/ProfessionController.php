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
                'name_profession' => 'required|string|max:100|unique:Professions,name_profession',
                'min_price' => 'required|numeric|min:0'
            ]);

            $professionId = DB::table('Professions')->insertGetId([
                'name_profession' => $request->name_profession,
                'min_price' => $request->min_price
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Profesión creada correctamente.',
                'data' => [
                    'id' => $professionId,
                    'name_profession' => $request->name_profession,
                    'min_price' => $request->min_price
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la profesión: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Validar, nombre debe ser único, EXCEPTO para este mismo ID.
        $request->validate([
            'name_profession' => 'required|string|unique:Profession,name_profession,' . $id,
            'min_price' => 'sometimes|required|numeric|min:0'
        ]);

        try {
            $updateData = [];
            
            // Verificamos qué campos nos han enviado para actualizar
            if ($request->has('name_profession')) {
                $updateData['name_profession'] = $request->name_profession;
            }
            if ($request->has('min_price')) {
                $updateData['min_price'] = $request->min_price;
            }

            if (empty($updateData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se enviaron datos para actualizar.'
                ], 400);
            }

            $updated = DB::table('Professions')->where('id', $id)->update($updateData);

            if (!$updated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Profesión no encontrada o los datos son exactamente iguales a los que ya tenía.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Profesión actualizada correctamente.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la profesión: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // borramos primero la relación con todos los profesionales que tengan este oficio
            DB::table('Professional_profession')->where('profession_id', $id)->delete();

            // borramos la profesión de verdad
            $deleted = DB::table('Professions')->where('id', $id)->delete();

            if (!$deleted) {
                throw new \Exception("La profesión con ID $id no existe.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profesión eliminada de forma segura (y desvinculada de todos los profesionales).'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar la profesión: ' . $e->getMessage()
            ], 500);
        }
    }
}