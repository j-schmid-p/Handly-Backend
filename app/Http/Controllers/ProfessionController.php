<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfessionController extends Controller
{
    public function index(){
        try {
            $professions = DB::table('Professions')->get();

            return response()->json([
                'status'=>'success',
                'data'=>'$professions'
            ],200);
        } catch (\Exception $e){
            return response()->json([
                'status'=>'error',
                'message'=>'error al obtener los oficios: '.$e->getMessage()
            ],500);
        }
    }
}