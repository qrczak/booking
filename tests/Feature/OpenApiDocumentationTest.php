<?php

namespace Tests\Feature;

use OpenApi\Generator;
use Psr\Log\NullLogger;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $generator = new Generator(new NullLogger);

        return json_decode($generator->generate([app_path()])->toJson(), true);
    }

    public function test_document_has_info_server_and_security_scheme(): void
    {
        $doc = $this->document();

        $this->assertSame('Booking API', $doc['info']['title']);
        $this->assertSame('1.0.0', $doc['info']['version']);
        $this->assertSame('https://booking.test', $doc['servers'][0]['url']);
        $this->assertArrayHasKey('sanctum', $doc['components']['securitySchemes']);
        $this->assertSame('http', $doc['components']['securitySchemes']['sanctum']['type']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['sanctum']['scheme']);
    }

    public function test_document_has_shared_schemas(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('ValidationError', $schemas);
        $this->assertArrayHasKey('ErrorMessage', $schemas);
        $this->assertArrayHasKey('AuthResponse', $schemas);
    }

    public function test_document_has_resource_schemas(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('User', $schemas);
        $this->assertArrayHasKey('Room', $schemas);
        $this->assertArrayHasKey('Booking', $schemas);

        $bookingProps = $schemas['Booking']['properties'];
        $this->assertArrayHasKey('room', $bookingProps);
        $this->assertArrayHasKey('status', $bookingProps);
        $this->assertSame(
            ['pending', 'confirmed', 'cancelled'],
            $bookingProps['status']['properties']['value']['enum']
        );
    }

    public function test_document_has_auth_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('post', $paths['/api/register']);
        $this->assertArrayHasKey('post', $paths['/api/login']);
        $this->assertArrayHasKey('post', $paths['/api/logout']);
        $this->assertSame(
            [['sanctum' => []]],
            $paths['/api/logout']['post']['security']
        );
    }

    public function test_document_has_rooms_and_translations_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('get', $paths['/api/rooms']);
        $this->assertSame(
            [['sanctum' => []]],
            $paths['/api/rooms']['get']['security']
        );
        $this->assertArrayHasKey('get', $paths['/api/translations']);
        $this->assertSame(
            'locale',
            $paths['/api/translations']['get']['parameters'][0]['name']
        );
    }

    public function test_document_has_bookings_paths(): void
    {
        $paths = $this->document()['paths'];

        $this->assertArrayHasKey('get', $paths['/api/bookings']);
        $this->assertArrayHasKey('post', $paths['/api/bookings']);
        $this->assertArrayHasKey('patch', $paths['/api/bookings/{booking}/cancel']);

        $cancel = $paths['/api/bookings/{booking}/cancel']['patch'];
        $this->assertSame('booking', $cancel['parameters'][0]['name']);
        $this->assertArrayHasKey('403', $cancel['responses']);
    }
}
