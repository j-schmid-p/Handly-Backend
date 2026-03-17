<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // generar textos aleatorios

class TaskController extends Controller
{
    public function store(Request $request)
    {
        try {
            // quién es el cliente 
            $user = $request->user();

            $client = DB::table('Client')
                ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->first();

            // si no esta logueado no puede pedir trabajo
            if (!$client) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los clientes registrados pueden solicitar tareas.'
                ], 403); // Prohibido
            }

           
            $taskId = DB::table('Tasks')->insertGetId([
                'client_id' => $client->id,
                'professional_id' => $request->professional_id, 
                'profession_id' => $request->profession_id,    
                'title' => $request->title,                    
                'description' => $request->description,        
                
                'task_state_id' => 1, // 1 = "solicited" 
                'token_qr' => Str::random(20), // Generamos un código QR aleatorio y único
                'creation_date' => now(), 
                // cambiar el null en bd
                'photo_1' => '', 
                'photo_2' => '',
                'video_1' => '',
                'video_2' => '',
                'accorded_date' => date('Y-m-d'), 
                'accorded_time' => '00:00:00', 
                'score_to_client' => 0,
                'review_to_professional' => '',
                'score_to_professional' => 0
            ]);

            return response()->json([
                'status' => 'success',
                'message' => '¡Trabajo solicitado con éxito al profesional!',
                'task_id' => $taskId
            ], 201); // Creado

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProfessionalTasks(Request $request){
        try {
            $user = $request->user();

            $professional = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->first();
            
            if (!$professional){
                return response()->json([
                    'status' => 'error',
                    'message' => 'a donde vas tu'
                ], 403);
            }

            $tasks = DB::table('Tasks')
                ->join('Task_states', 'Tasks.task_state_id', '=', 'Task_states.id')
                ->join('Client', 'Tasks.client_id', '=', 'Client.id') // unir con datos cliente
                ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
                ->join('Users', 'App_users.user_id', '=', 'Users.id')
                ->where('Tasks.professional_id', '=', $professional->id)
                ->select(
                    'Tasks.id as task_id',
                    'Tasks.title',
                    'Tasks.description',
                    'Tasks.creation_date',
                    'Task_states.name as status',
                    'Users.name as client_name',
                    'Users.surname as client_surname',
                    'App_users.city as client_city'
                )
                // las nuevas salen antes
                ->orderBy('Tasks.creation_date', 'desc')
                ->get();
                
            return response()->json([
                'status'=>'succes',
                'data'=>$tasks
            ],200);

        } catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=> 'Error al obtener las tareas: ' . $e->getMessage()
            ],500)
        }
    }

    public function updateStatus(Request $request, $id){
        try {
            $user = $request->user();
            //profesional logueado
            $professional = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->first();

            if (!$professional) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'a donde vas, solo los profesionales'
                ], 403);
            }

            $task = DB::table('Tasks')->where('id',$id)->first(); // buscar tarea en bd

            if(!$task){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tarea no encontrada.'
                ], 404);
            }

            // verificar que modifica la tarea que le pertenece
            if($task->professional_id !== $professional->id){
                return response()->json([
                    'status' => 'error',
                    'message' => 'a donde vas que esta no es tu tarea'
                ], 403);
            }

            $newStateId = $request->task_state_id; // nuevo estado 

            // actualiza bd con nuevo estado
            DB::table('Tasks')
            ->where('id',$id)
            ->update('task_state_id'=>$newStateId);

            return response()->json([
                'status' => 'success',
                'message' => '¡El estado de la tarea se ha actualizado correctamente!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la tarea: ' . $e->getMessage()
            ], 500);
        }

    }
}