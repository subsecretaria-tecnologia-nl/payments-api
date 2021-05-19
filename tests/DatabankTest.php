<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class DatabankTest extends TestCase {

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testDatabank() {

        $response = $this->json("post", '/v1/databank', array(''));
        var_dump($response->response->getStatusCode());

        $this->assertEquals(
                401, $response->response->getStatusCode()
        );
    }

}
