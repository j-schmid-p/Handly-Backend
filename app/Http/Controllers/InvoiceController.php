<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller {
    public function store(Request $request, $taskId){
        try {
            $user = $request->user();

            // verificar que sea profesional
            $professional = DB::table('Professional')
                ->join('App_users', 'Professional.app_user_id', '=', 'App_users.id')
                ->where('App_users.user_id', '=', $user->id)
                ->first();

            if (!$professional) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'que haces tu queriendo hacer facturas'
                ], 403);
            }

            $task = DB::table('Tasks')->where('id', $taskId)->first(); // verificar que la tarea le pertenezca

            if (!$task || $task->professional_id !== $professional->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tarea no encontrada o no tienes permiso.'
                ], 404);
            }

            // calculos de dinero
            $total = $request->total_payment; // todo lo que paga el cliente
            $comission =$total * 0.10; // comision para handly, 10%
            $revenue = $total - $comission; // lo que se lleva el manitas

            DB::beginTransaction(); // reversible por si algo falla

            // insertar en bd
            $invoiceId = DB::table('Invoices')->insertGetId([
                'task_id' => $taskId, 
                'total_payment' => $total,
                'app_comission' => $comission,
                'professional_revenue' => $revenue,
                'payment_method' => $request->payment_method,
                'payment_date' => date('Y-m-d')
            ]);

            DB::table('Tasks')
            ->where('id',$taskId)
            ->update(['task_state_id'=>5]); // 5= finalizado

            DB::commit(); // si hasta aqui salio bien 

            return response()->json([
                'status' => 'success',
                'message' => '¡Factura generada y trabajo finalizado con éxito!',
                'invoice_id' => $invoiceId,
                'resume' => [
                    'total_pagado' => $total . '€',
                    'ganancia_profesional' => $revenue . '€',
                    'comision_app' => $comission . '€'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // si algo falla se deshace todo
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar la factura: ' . $e->getMessage()
            ], 500);
    }
}
}