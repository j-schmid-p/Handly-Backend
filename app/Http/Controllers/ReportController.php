<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // crear una nueva denuncia
    public function store(Request $request)
    {
        // Validamos los datos que nos llegan
        $request->validate([
            'reporter_id' => 'required|integer',
            'reportee_id' => 'required|integer',
            'report_origin' => 'required|string',
            'cause' => 'required|string',
        ]);

        try {
            // Insertamos en la tabla Reports
            $id = DB::table('Reports')->insertGetId([
                'reporter_id' => $request->reporter_id,
                'reportee_id' => $request->reportee_id,
                'report_origin' => $request->report_origin,
                'cause' => $request->cause,
                'report_state_id' => 1
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Denuncia registrada correctamente.',
                'data' => ['report_id' => $id]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar la denuncia: ' . $e->getMessage()
            ], 500);
        }
    }

    // Ver TODAS las denuncias
    public function index()
    {
        try {
        //JULIA : cambiado lo siguiente:
            // Reports.reporter_id y reportee_id apuntan a App_users.id (no a Users.id),
            // por eso hay que pasar por App_users antes de llegar al nombre en Users.
            $reports = DB::table('Reports')
                ->leftJoin('App_users as ReporterApp', 'Reports.reporter_id', '=', 'ReporterApp.id')
                ->leftJoin('Users as Reporter', 'ReporterApp.user_id', '=', 'Reporter.id')
                ->leftJoin('App_users as ReporteeApp', 'Reports.reportee_id', '=', 'ReporteeApp.id')
                ->leftJoin('Users as Reportee', 'ReporteeApp.user_id', '=', 'Reportee.id')
                ->leftJoin('Report_states', 'Reports.report_state_id', '=', 'Report_states.id')
                ->select(
                    'Reports.id',
                    'Reports.report_origin',
                    'Reports.cause',
                    'Report_states.id as state_id',
                    'Report_states.name as state_name',
                    'Reports.reporter_id', //JULIA : cambiado esta linea
                    'Reporter.id as reporter_user_id', //JULIA : cambiado esta linea
                    'Reporter.name as reporter_name',
                    'Reporter.surname as reporter_surname',
                    'Reports.reportee_id', //JULIA : cambiado esta linea
                    'Reportee.id as reportee_user_id', //JULIA : cambiado esta linea
                    'Reportee.name as reportee_name',
                    'Reportee.surname as reportee_surname',
                    'Reporter.rol_id as reporter_rol_id', // JULIA
                    'Reportee.rol_id as reportee_rol_id', // JULIA
                )
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $reports
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las denuncias: ' . $e->getMessage()
            ], 500);
        }
    }


// JULIA : añadido este metodo
    // Ver todos los estados de denuncia (para poblar el dropdown en el admin)
    public function getReportStates()
    {
        try {
            $states = DB::table('Report_states')->orderBy('id')->get();

            return response()->json([
                'status' => 'success',
                'data' => $states
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los estados: ' . $e->getMessage()
            ], 500);
        }
    }

    // JULIA : añadido para el admin app
    // GET /api/admin/reports/{id}/context - devuelve los chats o las tareas entre
    // las dos partes de la denuncia. Los Reports no almacenan chat_id ni task_id,
    // así que buscamos por el par de usuarios.
    public function getContext($id)
    {
        try {
            // 1. cargar la denuncia y validar que existe
            $report = DB::table('Reports')->where('id', $id)->first();
            if (!$report) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Denuncia no encontrada.'
                ], 404);
            }

            // 2. encontrar Client.id y Professional.id de cada parte
            // reporter_id y reportee_id son App_users.id
            $reporterClient = DB::table('Client')->where('app_user_id', $report->reporter_id)->first();
            $reporterProfessional = DB::table('Professional')->where('app_user_id', $report->reporter_id)->first();
            $reporteeClient = DB::table('Client')->where('app_user_id', $report->reportee_id)->first();
            $reporteeProfessional = DB::table('Professional')->where('app_user_id', $report->reportee_id)->first();

            // 3. listar los pares (clientId, professionalId) posibles que conecten a las dos partes
            $pairs = [];
            if ($reporterClient && $reporteeProfessional) {
                $pairs[] = ['client_id' => $reporterClient->id, 'professional_id' => $reporteeProfessional->id];
            }
            if ($reporteeClient && $reporterProfessional) {
                $pairs[] = ['client_id' => $reporteeClient->id, 'professional_id' => $reporterProfessional->id];
            }

            $chats = [];
            $tasks = [];

            $origin = strtolower($report->report_origin ?: '');

            if (!empty($pairs)) {
                if ($origin === 'chat') {
                    // buscar chats que conecten a las dos partes
                    $chatsQuery = DB::table('Chats');
                    $chatsQuery->where(function ($q) use ($pairs) {
                        foreach ($pairs as $p) {
                            $q->orWhere(function ($qq) use ($p) {
                                $qq->where('Chats.client_id', $p['client_id'])
                                   ->where('Chats.professional_id', $p['professional_id']);
                            });
                        }
                    });
                    $chatRows = $chatsQuery->get();

                    foreach ($chatRows as $c) {
                        $messages = DB::table('Messages')
                            ->where('chat_id', $c->id)
                            ->orderBy('message_date')
                            ->get()
                            ->map(function ($m) {
                                if ($m->message_date) {
                                    $m->message_date = \Carbon\Carbon::parse($m->message_date)
                                        ->format('Y-m-d H:i:s');
                                }
                                return $m;
                            });

                        $chats[] = [
                            'id' => $c->id,
                            'task_id' => $c->task_id,
                            'client_id' => $c->client_id,
                            'professional_id' => $c->professional_id,
                            'messages' => $messages,
                        ];
                    }
                } elseif ($origin === 'task' || $origin === 'tarea') {
                    // buscar tareas que conecten a las dos partes
                    $taskQuery = DB::table('Tasks')
                        ->leftJoin('Task_states', 'Tasks.task_state_id', '=', 'Task_states.id');
                    $taskQuery->where(function ($q) use ($pairs) {
                        foreach ($pairs as $p) {
                            $q->orWhere(function ($qq) use ($p) {
                                $qq->where('Tasks.client_id', $p['client_id'])
                                   ->where('Tasks.professional_id', $p['professional_id']);
                            });
                        }
                    });
                    $taskRows = $taskQuery->select(
                        'Tasks.id',
                        'Tasks.title',
                        'Tasks.description',
                        'Tasks.task_state_id',
                        'Task_states.name as task_state_name',
                        'Tasks.creation_date',
                        'Tasks.client_id',
                        'Tasks.professional_id',
                        'Tasks.profession_id'
                    )->get()->map(function ($t) {
                        if ($t->creation_date) {
                            $t->creation_date = \Carbon\Carbon::parse($t->creation_date)
                                ->format('Y-m-d H:i:s');
                        }
                        return $t;
                    });

                    foreach ($taskRows as $t) {
                        $tasks[] = $t;
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'report_id' => $report->id,
                    'report_origin' => $report->report_origin,
                    'chats' => $chats,
                    'tasks' => $tasks,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el contexto: ' . $e->getMessage()
            ], 500);
        }
    }

    // Cambiar el estado de la denuncia
    public function updateStatus(Request $request, $id)
    {
        // Validamos que nos manden el nuevo ID de estado
        $request->validate([
            'report_state_id' => 'required|integer'
        ]);

        try {
            $updated = DB::table('Reports')->where('id', $id)->update([
                'report_state_id' => $request->report_state_id
            ]);

            if (!$updated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Denuncia no encontrada o el estado ya era ese.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de la denuncia actualizado correctamente.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la denuncia: ' . $e->getMessage()
            ], 500);
        }
    }
}
