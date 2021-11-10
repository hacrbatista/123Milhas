<?php

namespace App\Http\Controllers;

use App\Services\FlightService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use phpDocumentor\Reflection\Types\Collection;

class FlightController extends Controller
{
    public function format() {
        $service = new FlightService();
        $return = $service->pegandoGruposTratados();
        return $return;
    }
}
