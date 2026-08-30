<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Unangemeldete Besucher werden zum Login geleitet, die Wurzel
     * leitet designgemaess auf das Dashboard um.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect();
        $this->get('/login')->assertStatus(200);
    }
}
