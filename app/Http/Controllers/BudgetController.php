<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller {
    public function store(Request $request, $taskId){
        try {
            $user = $request->user();

            // verificar que es un profesional
            $professional = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->first();

            if(!$professional){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los profesionales pueden enviar presupuestos.'
                ], 403);
            }

            $task = DB::table('Tasks')->where('id', $taskId)->first(); // verificar existencia de tarea y que sea del profesional

            if (!$task || $task->professional_id !== $professional->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tarea no encontrada o no tienes permiso para presupuestarla.'
                ], 404);
            }

            // insertar presupuesto en bd
            $budgetId = DB::table('Budgets')->insertGetId([
                'job_id' => $taskId, 
                'agreed_price' => $request->agreed_price, 
                'budget_state_id' => 1, // 1 = pending 
                'creation_date' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => '¡Presupuesto enviado al cliente con éxito!',
                'budget_id' => $budgetId
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el presupuesto: ' . $e->getMessage()
            ], 500);
             }
            }
}