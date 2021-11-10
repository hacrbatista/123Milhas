<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlightService
{
    private $responseApi;

    public function __construct() {
        $this->responseApi = Http::get('http://prova.123milhas.net/api/flights');
    }

    /**
     * @return \Illuminate\Http\Client\Response
     */
    public function getResponseApi(): \Illuminate\Http\Client\Response
    {
        return $this->responseApi;
    }

    public function pegandoGruposTratados()
    {
        $response = $this->getResponseApi()->collect();

        // Ordenando desde o início
        $data = $this->ordenandoVoos($response);

        // Criando property direction para facilitar as regras
        $data = $this->criandoPropriedadeDirecao($data);

        // Agrupando os voos pelo fare (Tarifa)
        $data = $this->agrupandoVoosPeloFare($data);

        // Agrupando os voos pelo direction (outbound ou inbound)
        $data = $this->agrupandoVoosPelaDirecao($data);

        // Agrupando os voos pelo price (preço)
        $data = $this->agrupandoVoosPeloPreco($data);

        // Criando uma matrix de Ida e Volta, para criar todos os grupos possíveis
        $data = $this->criandoMatrixIdaVolta($data);

        // Unindo os grupos
        $data = $this->unindoGrupos($data);

        // Preparando o retorno
        $return = $this->preparandoRetorno($data);

        return $return;
    }

    private function ordenandoVoos($voos) {
        return $voos->sortBy('price')->sortBy('inbound')->sortBy('fare');
    }

    private function criandoPropriedadeDirecao($voos) {
        return $voos->map(function ($object) {
            $object['direction'] = ($object['outbound']) ? 'outbound' : 'inbound';
            return $object;
        });
    }

    private function agrupandoVoosPeloFare($voos) {
        return $voos->groupBy('fare');
    }

    private function agrupandoVoosPelaDirecao($voos) {
        $collect = collect();
        foreach($voos as $value) {
            $collect->push($value->groupBy('direction'));
        }
        return $collect;
    }

    private function agrupandoVoosPeloPreco($voos) {
        foreach($voos as $key => $value) {
            foreach ($value as $k => $v) {
                $voos[$key][$k] = $v->mapToGroups(function ($item, $key) {
                    return [$item['price'] => $item['id']];
                });
            }
        }

        return $voos;
    }

    private function criandoMatrixIdaVolta($voos) {
        $array = [];
        $collect = collect();
        foreach($voos as $key => $value) {
            foreach ($value as $k => $v) {
                $array[$k] = $v;
            }
            $collect->push($array['outbound']->crossJoin($array['inbound']));
        }

        return $collect;
    }

    private function unindoGrupos($voos) {
        $collect = collect();
        foreach($voos as $key => $value) {
            foreach ($value as $k => $v) {
                $collect->push($v);
            }
        }

        return $collect;
    }

    private function preparandoRetorno($voos) {
        $response = $this->getResponseApi()->collect();
        $array = [];
        $array['flights'] = $response;
        foreach ($voos as $key => $value) {
            $array['groups'][$key]['uniqueId'] = $key + 1;
            $array['groups'][$key]['totalPrice'] = $this->precoTotalGrupo($value, $response);
            $array['groups'][$key]['outbound']['id'] = $value[0]->toArray();
            $array['groups'][$key]['inbound']['id'] = $value[1]->toArray();
        }
        $array['totalGroups'] = $voos->count();
        $array['totalFlights'] = $response->count();
        $array['cheapestPrice'] = $this->precoGrupoMaisBarato($array['groups']);
        $array['cheapestGroup'] = $this->idGrupoMaisBarato($array['groups']);

        return $array;
    }

    private function precoTotalGrupo($grupo, $response) {
        $pv1 = $response->where('id', $grupo[0][0])->first()['price'];
        $pv2 = $response->where('id', $grupo[1][0])->first()['price'];
        return $pv1 + $pv2;
    }

    private function precoGrupoMaisBarato($grupos) {
        $grupo = $this->grupoMaisBarato($grupos);
        return $grupo['totalPrice'];
    }

    private function  idGrupoMaisBarato($grupos) {
        $grupo = $this->grupoMaisBarato($grupos);
        return $grupo['uniqueId'];
    }

    private function grupoMaisBarato($grupos) {
        usort($grupos, function($a, $b) {
            return $a['totalPrice'] <=> $b['totalPrice'];
        });

        return $grupos[0];
    }

}
