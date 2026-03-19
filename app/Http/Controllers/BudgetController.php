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

    public function accept(Request $request, $budgetId){
        try {
            DB::beginTransaction();

            $user= $request->user();

            // el que acepta tiene que ser cliente
            $client = DB::table('Client')
            ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
            ->where('App_users.user_id', '=', $user->id)
            ->first();

            if($client){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los clientes pueden aceptar presupuestos.'
                ], 403);
            }

            // buscar presupuesto
            $budget = DB::table('Budgets')->where('id',$budgetId)->first();

            if (!$budget) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Presupuesto no encontrado.'
                ], 404);
            }

            // buscar tarea y ver que pertenezca a este cliente
            $task = DB::table('Tasks')->where('id', $budget->job_id)->first();

            if (!$task || $task->client_id !== $client->id) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'No tienes permiso para aceptar este presupuesto porque no es tu encargo.'
                ], 403);
            }

            // actualizar estado en bd

            DB::table('Budgets')
                ->where('id', $budgetId)
                ->update([
                    'budget_state_id' => 2, // 2=accepted
                    'accorded_date' => now() 
                ]);

            // actualizar estado tarea
            DB::table('Tasks')
                ->where('id', $task->id)
                ->update(['task_state_id' => 3]); // 3= in process

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Presupuesto aceptado! El profesional ya puede empezar a trabajar.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack(); // si algo salio mal se retrocede
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aceptar el presupuesto: ' . $e->getMessage()
            ], 500);

    }

    }
}