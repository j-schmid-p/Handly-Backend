<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// devuelve las tablas enteras de states y roles

class CatalogController extends Controller
{
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
