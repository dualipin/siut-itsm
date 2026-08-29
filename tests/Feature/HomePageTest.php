<?php

test('the home page is rendered successfully', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertDontSee('className=');
    $response->assertSee('class="container mx-auto px-4 max-w-7xl pt-10"', false);
    $response->assertSee('class="btn btn-primary"', false);
});
