<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TranslationController extends Controller
{
    #[OA\Get(
        path: '/api/translations',
        tags: ['Translations'],
        summary: 'Tłumaczenia interfejsu dla danego locale',
        parameters: [
            new OA\Parameter(
                name: 'locale',
                in: 'query',
                required: false,
                description: 'Kod języka, np. pl/en. Domyślnie locale aplikacji.',
                schema: new OA\Schema(type: 'string', example: 'pl')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'locale', type: 'string', example: 'pl'),
                        new OA\Property(property: 'version', type: 'string', example: '1'),
                        new OA\Property(property: 'messages', type: 'object', description: 'Mapa: klucz tłumaczenia => tekst'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $locale = Translations::normalize(
            $request->string('locale')->toString() ?: app()->getLocale()
        );

        return response()->json([
            'locale' => $locale,
            'version' => Translations::version(),
            'messages' => Translations::messages($locale),
        ]);
    }
}
