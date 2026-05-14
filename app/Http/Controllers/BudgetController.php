<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BudgetController extends Controller {
    public function store(Request $request, $taskId)
    {
        $request->validate([
            'agreed_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'includes_materials' => 'required|boolean'
        ]);

        try {
            DB::beginTransaction();
            $user = $request->user();

            // ver que esta en la tabla de users de la app
            $appUser = DB::table('App_users')->where('user_id', $user->id)->first();
            
            // buscar en tabla de profesionales
            $professional = $appUser ? DB::table('Professional')->where('app_user_id', $appUser->id)->first() : null;

            if (!$professional) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los profesionales pueden enviar presupuestos.'
                ], 403);
            }

            // buscar la tarea
            $task = DB::table('Tasks')->where('id', $taskId)->first();

            if (!$task || $task->professional_id !== $professional->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tarea no encontrada o no tienes permiso para presupuestarla.',
                ], 403);
            }

            //guardar presupuesto en bd
            $budgetId = DB::table('Budgets')->insertGetId([
                'job_id' => $taskId, 
                'agreed_price' => $request->agreed_price,
                'notes' => $request->notes, 
                'includes_materials' => $request->includes_materials, 
                'budget_state_id' => 1, // 1 = Pendiente 
                'creation_date' => now()
            ]);

            // logica de notis
            $clientAppUser = DB::table('Client')
                ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
                ->where('Client.id', $task->client_id)
                ->select('App_users.user_id')
                ->first();

            if ($clientAppUser) {
                DB::table('Notifications')->insert([
                    'user_id' => $clientAppUser->user_id,
                    'task_id' => $taskId,
                    'title' => 'Nuevo presupuesto recibido',
                    'message' => 'Un profesional ha enviado un presupuesto para tu tarea.',
                    'is_read' => 0,
                    'created_at' => now() 
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Presupuesto enviado al cliente con éxito!',
                'budget_id' => $budgetId
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function accept(Request $request, $budgetId)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();

            // verificar el perfil en app users
            $appUser = DB::table('App_users')->where('user_id', $user->id)->first();
            
            // ver que este en clientes
            $client = $appUser ? DB::table('Client')->where('app_user_id', $appUser->id)->first() : null;

            if (!$client) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los clientes pueden aceptar presupuestos.'
                ], 403);
            }

            // buscar el presupuesto
            $budget = DB::table('Budgets')->where('id', $budgetId)->first();

            if (!$budget) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Presupuesto no encontrado.'
                ], 404);
            }

            // buscamos tarea y el dueño de la tarea
            $task = DB::table('Tasks')->where('id', $budget->job_id)->first();

            if (!$task || $task->client_id != $client->id) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'No tienes permiso para aceptar este presupuesto porque no es tu encargo.'
                ], 403);
            }

            // generar qr
            $nuevoQr = Str::random(20);

            // actualizar estados
            DB::table('Budgets')->where('id', $budgetId)->update([
                'budget_state_id' => 2 // 2 = negociating
            ]);

            DB::table('Tasks')->where('id', $task->id)->update([
                'task_state_id' => 4, // 4 accepted
                'token_qr' => $nuevoQr 
            ]); 

            $profAppUser = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('Professional.id', $task->professional_id)
                ->select('App_users.user_id')
                ->first();

            if ($profAppUser) {
                DB::table('Notifications')->insert([
                    'user_id' => $profAppUser->user_id,
                    'task_id' => $task->id,
                    'title' => 'Presupuesto aceptado',
                    'message' => '¡El cliente ha aceptado tu oferta! Ya puedes empezar el trabajo.',
                    'is_read' => 0,
                    'created_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => '¡Presupuesto aceptado! El profesional ya puede empezar a trabajar.',
                'token_qr' => $nuevoQr
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aceptar el presupuesto: ' . $e->getMessage()
            ], 500);
        }
    }
            
}