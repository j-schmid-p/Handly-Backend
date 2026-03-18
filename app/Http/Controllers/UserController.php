<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user(); // obtenemos user

        $role = DB::table('Roles')->where('id', $user->rol_id)->first(); // buscamos el nombre del rol que tiene

        $user->role_name = $role ? $role->name : 'DESCONOCIDO';

        if (isset($user->birthdate)) {
            $user->birthdate = Carbon::parse($user->birthdate)->format('d/m/Y'); // formateo de fecha
        }

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }
}
