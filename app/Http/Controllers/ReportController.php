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
                    'Reportee.surname as reportee_surname'
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
