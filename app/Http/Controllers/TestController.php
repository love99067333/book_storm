<?php

namespace App\Http\Controllers;

use App\StoreOpenningTime;
use Illuminate\Http\Request;

class TestController extends Controller
{
    //
    //Here somethin just test code

    public function selecttime(){
        // $test = StoreOpenningTime::where(['start_time','>=' ,strtotime('2:30pm')])->get();
        // $test = StoreOpenningTime::where('start_time','>=' ,strtotime('2:30pm'))->get();
        // dd(strtotime('2:30pm'));
        // dd($test);
        
    }
}
