<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// JULIA : toda esta clase
// controlador único para todas las tablas de catálogo (estados, roles)
// el admin app las descarga en una sola llamada al loguearse y las guarda en cache local
class CatalogController extends Controller
{
    // GET /api/admin/catalogs
    // devuelve TODAS las tablas de lookup en un solo JSON:
    //   account_states, budget_states, report_states, roles, task_states
    public function index()
    {
        try {
            $data = [
                'account_states' => DB::table('Account_states')->orderBy('id')->get(),
                'budget_states'  => DB::table('Budget_states')->orderBy('id')->get(),
                'report_states'  => DB::table('Report_states')->orderBy('id')->get(),
                'roles'          => DB::table('Roles')->orderBy('id')->get(),
                'task_states'    => DB::table('Task_states')->orderBy('id')->get(),
            ];

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al obtener los catálogos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
