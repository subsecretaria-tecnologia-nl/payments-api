<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RespuestabancoTest extends TestCase {

    public $idTransaccion = "2147483647";

    protected function getTransaccion() {
        $datos = DB::table('oper_transacciones as A')
                ->select('A.id_transaccion_motor')
                ->orderByDesc('A.id_transaccion_motor')
                ->limit(1)
                ->get();
        $this->idTransaccion = $datos[0]->id_transaccion_motor;
    }

    public function testRespuestabancoSuccess() {
        $this->getTransaccion();

        $parametros = array(
            "indPago" => "1",
            "s_transm" => $this->idTransaccion
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(1, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestaBancoErrorTransaccion() {
        $parametros = array(
            "indPago" => "0",
            "s_transm" => $this->idTransaccion
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestaBancoNoParams() {
        $parametros = array();
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

}
