<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SimuladorController extends Controller
{
    private $dadosSimulador;
    private $simulacao = [];

    public function simular(Request $request)
    {
        $request->valor_emprestimo = $this->removeFormatRealMoneyStringToValue($request->valor_emprestimo);
        $this->carregarArquivoDadosSimulador()
             ->simularEmprestimo($request->valor_emprestimo)
             ->filtrarInstituicao($request->instituicoes)
             ->filtrarConvenio($request->convenios)
             ->filtrarParcelas($request->parcela);
        return \response()->json(json_encode($this->simulacao));
    }

    private function carregarArquivoDadosSimulador() : self
    {
        $this->dadosSimulador = json_decode(\File::get(storage_path("app/public/simulador/taxas_instituicoes.json")));
        return $this;
    }

    private function simularEmprestimo(float $valorEmprestimo) : self
    {
        foreach ($this->dadosSimulador as $dados) {
            $this->simulacao[$dados->instituicao][] = [
                "taxa"            => $dados->taxaJuros,
                "parcelas"        => $dados->parcelas,
                "valor_parcela"    => $this->calcularValorDaParcela($valorEmprestimo, $dados->coeficiente),
                "convenio"        => $dados->convenio,
            ];
        }
        return $this;
    }

    private function calcularValorDaParcela(float $valorEmprestimo, float $coeficiente) : float
    {
        return round($valorEmprestimo * $coeficiente, 2);
    }

    private function filtrarInstituicao(array $instituicoes) : self
    {
        if (\count($instituicoes))
        {
            $arrayAux = [];
            foreach ($instituicoes AS $key => $instituicao)
            {
                if (\array_key_exists($instituicao, $this->simulacao))
                {
                     $arrayAux[$instituicao] = $this->simulacao[$instituicao];
                }
            }
            $this->simulacao = $arrayAux;
        }
        return $this;
    }

    private function filtrarConvenio(array $convenios) : self
    {
        if (\count($convenios))
        {
            foreach ($this->simulacao as $key => $simulacao) {

                foreach ($simulacao as $skey => $s) {
                    if(!in_array($s['convenio'], $convenios)) {
                        unset($this->simulacao[$key][$skey]);
                    }
                }
            }
            $this->simulacao = array_filter($this->simulacao);
        }
        return $this;
    }

    private function filtrarParcelas(int $parcela = null) : self
    {
        if ($parcela) {
            foreach ($this->simulacao as $key => $simulacao) {

                foreach ($simulacao as $skey => $s) {

                    if($s['parcelas'] != $parcela) {
                        unset($this->simulacao[$key][$skey]);
                    }
                }
            }
            $this->simulacao = array_filter($this->simulacao);
        }
        return $this;
    }

    private function removeFormatRealMoneyStringToValue(string $value): float
    {
        if (is_float($value) || is_numeric($value)) {
            return $value;
        } else if (!is_string($value)) {
            return 0;
        }

        return (float) str_replace(['R$', ' ', '??', '.', ','], ['', '', '', '', '.'], $value);
    }
}
