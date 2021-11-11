<?php

namespace App\Http\Controllers;

use App\Services\FlightService;

class FlightController extends Controller
{
    public function format() {
        $service = new FlightService();
        $return = $service->pegandoGruposTratados();
        return $return;
    }
}
