<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class DatabankTest extends TestCase {

    public $idTransaccion = "2147483647";
    public $cuentaId = "2147483647";

    protected function getTransaccion($banco, $metodoPago = "") {

        $condiciones = ' AND B.banco_id = ' . $banco;
        if ($banco == "") {
            $condiciones = ' AND B.metodopago_id = ' . $metodoPago;
        }
        $sql = 'SELECT * FROM (
                                SELECT id_tipo_servicio, id_transaccion_motor,importe_tramite, SUM(importe_tramite) 
                                FROM oper_tramites A 
                                GROUP BY id_transaccion_motor 
                                ORDER BY A.id_transaccion_motor DESC LIMIT 1000
                                ) tbl
                            INNER JOIN oper_pagotramite A 
                            ON A.tramite_id= tbl.id_tipo_servicio AND A.estatus = 1
                            INNER JOIN oper_cuentasbanco B 
                            ON A.cuentasbanco_id = B.id ' . $condiciones . ' LIMIT 1';
        $datos = DB::select($sql);
        $this->idTransaccion = $datos[0]->id_transaccion_motor;
        $this->cuentaId = $datos[0]->cuentasbanco_id;
    }

    public function testDatabankCuentaNoPermitida() {
        $this->getTransaccion(17); //17 banco netpay
        $parametros = array(
            "folio" => $this->idTransaccion,
            "cuenta_id" => 999
        );
        $response = $this->json('POST', '/v1/databank', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertObjectHasAttribute('response', $VAR);
        $this->assertArrayHasKey('error', $VAR->response);
        $this->assertEquals(2, $VAR->response['error']);
        $this->assertArrayHasKey('mensaje', $VAR->response);
        $this->assertEquals('Cuenta no permitida para la transaccion', $VAR->response['mensaje']);
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testDatabankNetPay() {
        $this->getTransaccion(17); //17 banco netpay
        $parametros = array(
            "folio" => $this->idTransaccion,
            "cuenta_id" => $this->cuentaId
        );
        $response = $this->json('POST', '/v1/databank', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertObjectHasAttribute('response', $VAR);
        $this->assertArrayHasKey('error', $VAR->response);
        $this->assertEquals(0, $VAR->response['error']);
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertObjectHasAttribute('jwt', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testDatabankReferencia() {
        $variablesEnt = explode("|", getenv("FORMATO_RECIBO"));
        $urlFormatoPago = $variablesEnt[0];
        $this->getTransaccion("", 3); //banco vacio , metodo pago referencia (3)
        $parametros = array(
            "folio" => $this->idTransaccion,
            "cuenta_id" => $this->cuentaId
        );
        $response = $this->json('POST', '/v1/databank', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertObjectHasAttribute('response', $VAR);
        $this->assertArrayHasKey('error', $VAR->response);
        $this->assertEquals(0, $VAR->response['error']);
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertObjectHasAttribute('url_recibo', $VAR->response['datos']);
        $this->assertEquals($urlFormatoPago . $this->idTransaccion, $VAR->response['datos']->url_recibo);
        $this->assertObjectHasAttribute('estatus', $VAR->response['datos']);
        $this->assertEquals(2, $VAR->response['datos']->estatus);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

}
