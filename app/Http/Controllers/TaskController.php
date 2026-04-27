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
                ->select('Client.id as client_id')
                ->first();

            // si no esta logueado no puede pedir trabajo
            if (!$client) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo los clientes registrados pueden solicitar tareas.'
                ], 403); // Prohibido
            }

            $photo1 = null;
            if ($request->filled('photo_1')) {
                // Guardamos el texto Base64 tal cual llega
                $photo1 = $request->photo_1; 
            }

            $photo2 = null;
            if ($request->filled('photo_2')) {
                $photo2 = $request->photo_2;
            }

            $taskId = DB::table('Tasks')->insertGetId([
                'client_id' => $client->client_id,
                'professional_id' => $request->professional_id, 
                'profession_id' => $request->profession_id,    
                'title' => $request->title,                    
                'description' => $request->description,        
                'task_state_id' => 1, // 1 = "solicited" 
                'token_qr' => Str::random(20), // Generamos un código QR aleatorio y único
                'creation_date' => now(),
                'photo_1' => $photo1,
                'photo_2' => $photo2
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
                ->select('Professional.id as professional_id')
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
                ->where('Tasks.professional_id', '=', $professional->professional_id)
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
                ->get()
                // formateo de fecha d/m/y
                ->map(function ($task) {
                    if ($task->creation_date) {
                        $task->creation_date = \Carbon\Carbon::parse($task->creation_date)->format('d/m/Y');
                    }
                    if ($task->accorded_date) {
                        $task->accorded_date = \Carbon\Carbon::parse($task->accorded_date)->format('d/m/Y');
                    }
                    return $task;
                });
                
            return response()->json([
                'status'=>'succes',
                'data'=>$tasks
            ],200);

        } catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=> 'Error al obtener las tareas: ' . $e->getMessage()
            ],500);
        }
    }

    public function updateStatus(Request $request, $id){
        try {
            $user = $request->user();
            //profesional logueado
            $professional = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->select('Professional.id')
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
            ->update(['task_state_id' => $newStateId]);

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

    public function getClientTasks(Request $request){
        try {
            $user=$request->user();
            
            // buscamos al cliente por su token
            $client =DB::table('Client')
            ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
            ->where('App_users.user_id', '=', $user->id)
            ->select('Client.id')
            ->first();

            if (!$client) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Acceso denegado. No eres un cliente.'
                ], 403);
            }

            // buscamos tarea y pegamos presupuesto si es que ya tiene
            $tasks = DB::table('Tasks')
                ->leftJoin('Budgets', 'Tasks.id', '=', 'Budgets.job_id')
                ->where('Tasks.client_id', '=', $client->id)
                ->select(
                    'Tasks.id as task_id',
                    'Tasks.title',
                    'Tasks.description',
                    'Tasks.task_state_id',
                    'Tasks.creation_date as task_date',
                    'Budgets.id as budget_id',
                    'Budgets.agreed_price',
                    'Budgets.budget_state_id'
                )
                ->orderBy('Tasks.creation_date', 'desc')
                ->get()
                ->map(function ($task) {
                    if ($task->task_date) {
                        $task->task_date = \Carbon\Carbon::parse($task->task_date)->format('d/m/Y');
                    }
                    return $task;
                });

            return response()->json([
                'status' => 'success',
                'data' => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las tareas: ' . $e->getMessage()
            ], 500);
    }
    }

    public function getTaskDetails(Request $request, $id){
        try {
            // buscamos la tarea y cruzamos con los datos del cliente y del estado
            $task = DB::table('Tasks')
                ->join('Task_states', 'Tasks.task_state_id', '=', 'Task_states.id')
                ->join('Client', 'Tasks.client_id', '=', 'Client.id')
                ->join('App_users', 'Client.app_user_id', '=', 'App_users.id')
                ->join('Users', 'App_users.user_id', '=', 'Users.id')
                ->leftJoin('Budgets', 'Tasks.id', '=', 'Budgets.job_id') // leftJoin porque puede no tener presupuesto aún
                ->where('Tasks.id', '=', $id)
                ->select(
                    'Tasks.id as task_id',
                    'Tasks.title',
                    'Tasks.description',
                    'Tasks.creation_date',
                    'Task_states.name as status_name',
                    'Task_states.id as status_id',
                    'Users.name as client_name',
                    'Users.surname as client_surname',
                    'App_users.city as client_city'
                    'Budgets.id as budget_id',
                    'Budgets.agreed_price',
                    'Budgets.budget_state_id'
                )
                ->first();
            
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tarea no encontrada.'
                ], 404);
            }

            // formatear fecha
            if ($task->creation_date) {
                $task->creation_date = \Carbon\Carbon::parse($task->creation_date)->format('d/m/Y');
            }

            return response()->json([
                'status' => 'success',
                'data' => $task
            ], 200);

            } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los detalles de la tarea: ' . $e->getMessage()
            ], 500);
        
        }
               
    }

    // PARA ADMIN: Obtener todas las tareas del sistema
    public function getAllTasks(Request $request) {
        try {
            $tasks = DB::table('Tasks')
                ->join('Task_states', 'Tasks.task_state_id', '=', 'Task_states.id')
                ->join('Client', 'Tasks.client_id', '=', 'Client.id')
                // Unimos para sacar el nombre del cliente
                ->join('App_users as ClientAppUser', 'Client.app_user_id', '=', 'ClientAppUser.id')
                ->join('Users as ClientUser', 'ClientAppUser.user_id', '=', 'ClientUser.id')
                // Unimos (leftJoin por si no tiene) para sacar el nombre del profesional
                ->leftJoin('Professional', 'Tasks.professional_id', '=', 'Professional.id')
                ->leftJoin('App_users as ProfAppUser', 'Professional.app_user_id', '=', 'ProfAppUser.id')
                ->leftJoin('Users as ProfUser', 'ProfAppUser.user_id', '=', 'ProfUser.id')
                ->select(
                    'Tasks.id as task_id',
                    'Tasks.title',
                    'Task_states.name as status',
                    'Tasks.creation_date',
                    'ClientUser.name as client_name',
                    'ProfUser.name as professional_name'
                )
                ->orderBy('Tasks.id', 'desc')
                ->get()
                ->map(function ($task) {
                    if ($task->creation_date) {
                        $task->creation_date = \Carbon\Carbon::parse($task->creation_date)->format('d/m/Y');
                    }
                    // Si no hay profesional asignado, mandamos un texto por defecto
                    if (!$task->professional_name) {
                        $task->professional_name = 'Sin asignar';
                    }
                    return $task;
                });

            return response()->json([
                'status' => 'success',
                'data' => $tasks
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el listado global de tareas: ' . $e->getMessage()
            ], 500);
        }
    }
}