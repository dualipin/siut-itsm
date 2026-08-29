<?php

test('the about page is rendered successfully', function () {
    $response = $this->get('/about');

    $response->assertStatus(200);
    $response->assertSee('Conoce tu Sindicato');
});
