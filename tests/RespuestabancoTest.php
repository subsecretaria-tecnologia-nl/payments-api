<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RespuestabancoTest extends TestCase {

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testRespuestabanco() {

        $response = $this->json("post", '/v1/respuestabanco', array(''));
        var_dump($response->response->getStatusCode());

        $this->assertEquals(
                401, $response->response->getStatusCode()
        );
    }

}
