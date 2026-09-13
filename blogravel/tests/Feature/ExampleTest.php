<?php

test('guests are redirected from the platform root to admin login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirectToRoute('filament.admin.auth.login');
});
